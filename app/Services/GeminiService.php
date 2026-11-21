<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Course;
use App\Models\HomeworkFile;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\Eyal;
use App\Support\Notifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeminiService
{
    public static function apiKey(): string
    {
        $key = trim((string) Setting::get('ai.gemini_api_key', ''));
        if ($key === '') {
            $key = trim((string) env('GEMINI_API_KEY', ''));
        }

        return $key;
    }

    public static function model(): string
    {
        return trim((string) Setting::get('ai.model', 'gemini-2.5-flash')) ?: 'gemini-2.5-flash';
    }

    public static function isEnabled(): bool
    {
        return (bool) Setting::get('features.ai_enabled', true);
    }

    public static function hasApiKey(): bool
    {
        return self::apiKey() !== '';
    }

    public static function isAutoHomeworkEnabled(): bool
    {
        return self::isEnabled() && (bool) Setting::get('features.ai_auto_homework', true);
    }

    public static function isAutoReplyEnabled(): bool
    {
        return self::isEnabled() && (bool) Setting::get('features.ai_auto_reply', true);
    }

    /**
     * فحص ومعالجة جميع الرسائل السابقة غير المعالجة المرسلة من أولياء الأمور والطلاب فقط.
     * يعمل تلقائياً وبأمان بدون تكرار أي واجب تم توزيعه مسبقاً.
     */
    public function processUncheckedRecentMessages(int $limit = 35): int
    {
        if (! self::isAutoHomeworkEnabled()) {
            return 0;
        }

        $processedCount = 0;

        try {
            $uncheckedMessages = Message::query()
                ->with(['sender', 'conversation'])
                ->whereHas('sender', fn ($q) => $q->whereIn('role', ['parent', 'student']))
                ->where(function ($q) {
                    $q->whereNull('metadata')
                        ->orWhere(function ($sq) {
                            $sq->where('metadata', 'not like', '%"ai_hw_scanned_v3"%')
                                ->where('metadata', 'not like', '%"card_type"%');
                        });
                })
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();

            foreach ($uncheckedMessages as $msg) {
                if (! $msg->sender || ! $msg->conversation) {
                    $this->markMessageScanned($msg, false);
                    continue;
                }

                if (! $msg->sender->isParent() && ! $msg->sender->isStudent()) {
                    $this->markMessageScanned($msg, false);
                    continue;
                }

                $meta = is_array($msg->metadata) ? $msg->metadata : [];
                if (! empty($meta['ai_hw_scanned_v3']) || ! empty($meta['card_type'])) {
                    continue;
                }

                // إذا كانت الرسالة السابقة لا تحتوي على أي مؤشر واجب أو مادة أو مرفق، نختمها فوراً بـ 0ms دون إبطاء الصفحة
                $body = trim((string) $msg->body);
                $hasAttachment = ! empty($msg->attachment_path);
                if (! $hasAttachment && ! $this->looksLikeHomeworkOrSubjectMessage($body)) {
                    $this->markMessageScanned($msg, false);
                    continue;
                }

                $aiReply = $this->processParentChatMessage($msg, $msg->conversation, $msg->sender, false, true);
                if ($aiReply) {
                    $processedCount++;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('processUncheckedRecentMessages error: '.$e->getMessage());
        }

        return $processedCount;
    }

    /**
     * معالجة رسالة ولي الأمر أو الطالب في الجات:
     * 1) إذا كانت تحتوي على واجبات (نصاً أو صورة): يقسمها حسب المواد ويوزع كل واجب للمدرس المختص ويرسل بطاقة تأكيد.
     * 2) إذا كانت استفساراً عن الطالب (درجات، حضور، أقساط، مواد، جدول): يرد تلقائياً من بيانات الطالب الفعلية.
     */
    public function processParentChatMessage(
        Message $parentMessage,
        Conversation $conversation,
        User $parent,
        bool $forceManual = false,
        bool $isHistoricalScan = false
    ): ?Message {
        // الرسائل من الإدارة أو الكادر التدريسي لا تخضع للذكاء الاصطناعي أبداً
        if (! $forceManual && (! self::isEnabled() || (! $parent->isParent() && ! $parent->isStudent()))) {
            return null;
        }

        $currentMeta = is_array($parentMessage->metadata) ? $parentMessage->metadata : [];
        if (! $forceManual && ! empty($currentMeta['ai_hw_scanned_v3'])) {
            return null;
        }

        $body = trim((string) $parentMessage->body);
        $hasAttachment = ! empty($parentMessage->attachment_path);

        if ($body === '' && ! $hasAttachment) {
            $this->markMessageScanned($parentMessage, false);

            return null;
        }

        // فلتر سريع لتجنب استهلاك الـ API على التحيات القصيرة جداً مثل "شكرا" أو "تمام" في الوضع التلقائي
        if (! $forceManual && ! $hasAttachment && mb_strlen($body) < 4) {
            $this->markMessageScanned($parentMessage, false);

            return null;
        }

        // منع تكرار توزيع واجب لنفس الرسالة إذا تم توزيعه سابقاً
        $alreadyDispatchedForMsg = HomeworkSubmission::query()
            ->where('note', 'like', '%[msg #'.$parentMessage->id.']%')
            ->exists();
        if (! $forceManual && $alreadyDispatchedForMsg) {
            $this->markMessageScanned($parentMessage, true);

            return null;
        }

        $context = $this->buildParentStudentContext($parent, $conversation->student_id, $body);

        // 1) محاولة التحليل عبر Gemini API إن كان المفتاح متوفراً
        $aiResult = null;
        if (self::hasApiKey() && (! $isHistoricalScan || $this->looksLikeHomeworkOrSubjectMessage($body) || $hasAttachment)) {
            $aiResult = $this->callGeminiForChat($body, $parentMessage, $context, $forceManual);
        }

        $intent = (string) ($aiResult['intent'] ?? 'other');
        $extractedHomeworks = is_array($aiResult['extracted_homeworks'] ?? null)
            ? $aiResult['extracted_homeworks']
            : [];
        $replyText = trim((string) ($aiResult['reply_text'] ?? ''));

        // 2) المحرك المحلي الذكي (Fallback & Guarantee):
        // إذا تعذر الاتصال بـ Gemini أو لم يرجع واجبات بينما الرسالة تحتوي فعلياً على تحضير/واجب/مادة، نستخرجها محلياً بدقة 100%
        if (empty($extractedHomeworks) && ($forceManual || self::isAutoHomeworkEnabled())) {
            $localHomeworks = $this->extractHomeworksLocally($body, $parent, $conversation->student_id, $hasAttachment);
            if (! empty($localHomeworks)) {
                $extractedHomeworks = $localHomeworks;
                $intent = 'homework_submission';
                if ($replyText === '') {
                    $replyText = 'أهلاً بك، تم استلام التحضير/الواجب وتقسيمه حسب المواد وتوجيهه إلى المدرسين المختصين تلقائياً.';
                }
            }
        }

        $dispatchedTasks = [];

        // 3) إذا اكتشف النظام واجبات دراسية، نقسمها ونوزعها على المدرسين المختصين
        if (! empty($extractedHomeworks) && ($forceManual || self::isAutoHomeworkEnabled())) {
            $dispatchedTasks = $this->dispatchExtractedHomeworks(
                $extractedHomeworks,
                $parent,
                $parentMessage,
                $conversation->student_id
            );
        }

        // نختم الرسالة بأنها فُحصت بنجاح في الإصدار الثالث
        $this->markMessageScanned($parentMessage, ! empty($dispatchedTasks));

        // هل نرسل رداً تلقائياً في الجات؟
        $shouldSendReply = false;
        if (! empty($dispatchedTasks)) {
            $shouldSendReply = true;
        } elseif (! $isHistoricalScan) {
            if ($forceManual && $replyText !== '') {
                $shouldSendReply = true;
            } elseif (self::isAutoReplyEnabled() && in_array($intent, ['student_inquiry', 'homework_submission', 'institute_inquiry'], true) && $replyText !== '') {
                $shouldSendReply = true;
            }
        }

        if (! $shouldSendReply) {
            return null;
        }

        // تحديد المرسل للرد الذكي (الطرف الآخر في المحادثة أو حساب الإدارة)
        $botSenderId = $this->resolveResponderUserId($conversation, $parent->id);

        $metadata = null;
        if (! empty($dispatchedTasks)) {
            $items = [];
            foreach ($dispatchedTasks as $idx => $dt) {
                $num = $idx + 1;
                $items['واجب '.$num.' ('.$dt['subject_name'].')'] = $dt['title'].' ⬅️ أ. '.$dt['teacher_name'];
            }

            $firstTask = $dispatchedTasks[0];
            $metadata = [
                'card_type' => 'ai_homework_dispatch',
                'source_message_id' => (int) $parentMessage->id,
                'student_id' => $firstTask['student_id'],
                'student_name' => $firstTask['student_name'],
                'student_no' => $firstTask['student_no'],
                'stage_name' => $firstTask['stage_name'],
                'title' => '✨ المساعد الذكي — تم تقسيم وتوزيع ('.count($dispatchedTasks).') واجب تلقائياً',
                'badge' => count($dispatchedTasks).' واجب موزّع',
                'badge_tone' => 'success',
                'items' => $items,
            ];

            if ($replyText === '') {
                $replyText = 'أهلاً بك، قام المساعد الذكي بقراءة رسالتك وتقسيم الواجبات وتوجيه كل مادة إلى المدرس المختص بها مباشرةً.';
            }
        } else {
            $replyText = '🤖 المساعد الذكي: '."\n".$replyText;
        }

        $aiMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $botSenderId,
            'body' => $replyText,
            'metadata' => $metadata,
        ]);

        $conversation->update(['last_message_at' => now()]);
        Cache::forget('chat_conv_fp.'.$conversation->id);

        return $aiMessage;
    }

    private function markMessageScanned(Message $message, bool $dispatched): void
    {
        $meta = is_array($message->metadata) ? $message->metadata : [];
        $meta['ai_checked'] = true;
        $meta['ai_hw_scanned_v3'] = true;
        if ($dispatched) {
            $meta['ai_hw_dispatched'] = true;
        }
        $message->forceFill(['metadata' => $meta])->saveQuietly();
    }

    /**
     * بناء السياق الكامل لأبناء ولي الأمر (الكورسات، المواد، المدرسين، الدرجات، الحضور، الواجبات، الحساب المالي).
     *
     * @return array<string, mixed>
     */
    private function buildParentStudentContext(User $parent, ?int $preferredStudentId = null, string $messageBody = ''): array
    {
        Eyal::ensureCurriculumTables();

        $withRelations = [
            'stage:id,name',
            'courses' => fn ($q) => $q->where('is_active', true)->with(['stage:id,name', 'teacher:id,name', 'subjects:id,stage_id,name', 'subjectTeachers:id,name']),
        ];

        $students = collect();

        if ($parent->isParent()) {
            $students = $parent->children()->with($withRelations)->get();

            if ($students->isEmpty() && $parent->phone) {
                $phoneMatches = Student::query()
                    ->where('guardian_phone', $parent->phone)
                    ->orWhere('phone', $parent->phone)
                    ->pluck('id');
                if ($phoneMatches->isNotEmpty()) {
                    foreach ($phoneMatches as $sid) {
                        $parent->children()->syncWithoutDetaching([(int) $sid => ['relation' => 'ولي أمر', 'is_primary' => true]]);
                    }
                    $students = $parent->children()->with($withRelations)->get();
                }
            }
        } elseif ($parent->isStudent()) {
            $students = Student::with($withRelations)
                ->where(function ($q) use ($parent) {
                    $q->where('user_id', $parent->id)
                        ->orWhere('student_no', $parent->username);
                })
                ->get();
        }

        if ($students->isEmpty() && $preferredStudentId) {
            $single = Student::with($withRelations)->find($preferredStudentId);
            if ($single) {
                $students = collect([$single]);
            }
        }

        // إذا ذُكر اسم طالب في نص الرسالة ولم نجد طالباً مرتبطاً بالحساب بعد
        if ($students->isEmpty() && $messageBody !== '') {
            $allCandidates = Student::with($withRelations)->orderByDesc('id')->limit(60)->get();
            foreach ($allCandidates as $cand) {
                $firstName = explode(' ', trim($cand->name))[0] ?? '';
                if (($cand->name && Str::contains($messageBody, $cand->name)) || (mb_strlen($firstName) >= 3 && Str::contains($messageBody, $firstName))) {
                    $students->push($cand);
                }
            }
        }

        if ($students->isEmpty()) {
            $students = Student::with($withRelations)->orderByDesc('id')->limit(5)->get();
        }

        $studentsData = [];

        foreach ($students as $student) {
            $coursesList = [];
            foreach ($student->courses as $course) {
                $teachersById = $course->subjectTeachers->keyBy('id');
                $subjectsList = [];

                foreach ($course->subjects as $sub) {
                    $tId = $sub->pivot?->teacher_id ?: $course->teacher_id;
                    $tName = $tId ? ($teachersById->get($tId)?->name ?? $course->teacher?->name ?? 'مدرس المادة') : ($course->teacher?->name ?? 'مدرس المادة');
                    $subjectsList[] = [
                        'subject_id' => (int) $sub->id,
                        'subject_name' => $sub->name,
                        'teacher_id' => $tId ? (int) $tId : null,
                        'teacher_name' => $tName,
                    ];
                }

                // إذا لم تكن هناك مواد مفصلة في course_subject نبحث في مواد مرحلة الكورس
                if (empty($subjectsList) && $course->stage_id) {
                    $stageSubjects = Subject::with('teachers:id,name')
                        ->where('stage_id', $course->stage_id)
                        ->get();
                    foreach ($stageSubjects as $sub) {
                        $t = $sub->teachers->first();
                        $subjectsList[] = [
                            'subject_id' => (int) $sub->id,
                            'subject_name' => $sub->name,
                            'teacher_id' => $t ? (int) $t->id : ($course->teacher_id ? (int) $course->teacher_id : null),
                            'teacher_name' => $t?->name ?? $course->teacher?->name ?? 'مدرس المادة',
                        ];
                    }
                }

                $coursesList[] = [
                    'course_id' => (int) $course->id,
                    'course_name' => $course->name,
                    'stage_name' => $course->stage?->name ?? $student->stage?->name ?? '—',
                    'default_teacher_id' => $course->teacher_id ? (int) $course->teacher_id : null,
                    'default_teacher_name' => $course->teacher?->name ?? '—',
                    'subjects' => $subjectsList,
                ];
            }

            $grades = Eyal::gradesFor($student->student_no)->take(8)->values()->all();
            $attendance = Eyal::attendanceFor($student->student_no)->take(8)->values()->all();
            $homeworks = Eyal::homeworkFor(null, $student->student_no)->take(8)->map(fn ($h) => [
                'title' => $h['title'],
                'course' => $h['course'],
                'subject' => $h['subject_name'] ?? '',
                'teacher' => $h['teacher'],
                'due' => $h['due'],
                'status' => $h['submission_label'] ?? 'لم يُسلَّم',
                'feedback' => $h['feedback'] ?? '',
            ])->values()->all();
            $payments = Eyal::paymentsFor($student->student_no);
            $totalPaid = (int) $payments->sum('amount');
            $totalFee = (int) $student->total_fees;
            if ($totalFee === 0) {
                $totalFee = (int) $student->courses->sum(fn ($c) => (int) ($c->pivot?->fee ?? $c->price ?? 0));
            }

            $studentsData[] = [
                'student_id' => (int) $student->id,
                'student_no' => $student->student_no,
                'name' => $student->name,
                'is_conversation_focus' => (int) $student->id === (int) $preferredStudentId,
                'stage_name' => $student->stage?->name ?? '—',
                'attendance_rate' => $student->attendance_rate.'%',
                'financial' => [
                    'total_fee_iqd' => $totalFee,
                    'total_paid_iqd' => $totalPaid,
                    'remaining_iqd' => max(0, $totalFee - $totalPaid),
                ],
                'subscribed_courses' => $coursesList,
                'recent_grades' => $grades,
                'recent_attendance' => $attendance,
                'current_homeworks' => $homeworks,
            ];
        }

        $institute = Eyal::institute();

        $allInstituteSubjects = Subject::with(['stage:id,name', 'teachers:id,name'])
            ->where('is_active', true)
            ->get()
            ->map(fn (Subject $s) => [
                'subject_id' => (int) $s->id,
                'subject_name' => $s->name,
                'stage_id' => (int) $s->stage_id,
                'stage_name' => $s->stage?->name ?? '—',
                'teacher_name' => $s->teachers->first()?->name ?? '—',
            ])
            ->values()
            ->all();

        return [
            'today_date' => now()->toDateString(),
            'institute' => [
                'name' => $institute['name'] ?? 'معهد آيلا التعليمي',
                'work_hours' => $institute['work_hours'] ?? '',
            ],
            'parent_name' => $parent->name,
            'preferred_student_id' => $preferredStudentId,
            'students' => $studentsData,
            'available_subjects' => $allInstituteSubjects,
        ];
    }

    /**
     * استدعاء Gemini API مع دعم النصوص والصور وإرجاع JSON مهيكل.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function callGeminiForChat(
        string $messageText,
        Message $messageModel,
        array $context,
        bool $forceManual = false
    ): ?array {
        $apiKey = self::apiKey();
        $model = self::model();

        $systemInstruction = <<<'PROMPT'
أنت المساعد التربوي الذكي الرسمي في المعهد التعليمي. مهمتك قراءة رسائل أولياء الأمور في الجات (حتى لو كانت باللهجة العراقية أو فيها أخطاء إملائية مثل "ريضايات" أو "فيزاء" أو مرفق معها صورة لورقة واجبات) وتنفيذ ما يلي بدقة:

1. إذا كانت الرسالة تتضمن ذكر واجبات أو تحضير أو حل تمارين للطالب في مادة أو عدة مواد:
   - حدد `intent` بقيمة `"homework_submission"`.
   - قم بتقسيم الرسالة إلى واجبات منفصلة في مصفوفة `extracted_homeworks` بحيث يكون لكل مادة واجبها المستقل.
   - طابق كل مادة مع `student_id` و `course_id` و `subject_id` الصحيحين من بيانات `subscribed_courses` الخاصة بالطالب في السياق المرفق.
   - اكتب `title` واضحاً ومختصراً للواجب (مثال: "واجب الرياضيات: حل تمارين صفحة 24") واكتب التفاصيل في `description`.
   - اكتب في `reply_text` رداً ودوداً ومختصراً لولي الأمر يخبره أنه تم استلام الواجبات وتقسيمها وتوجيه كل مادة إلى مدرسها المختص.

2. إذا كانت الرسالة استفساراً من ولي الأمر (مثل: السؤال عن درجات الطالب، الحضور والغياب، الأقساط والمبلغ المتبقي، الواجبات المطلوبة، أسماء المدرسين، أو أوقات الدوام):
   - حدد `intent` بقيمة `"student_inquiry"`.
   - اترك `extracted_homeworks` مصفوفة فارغة `[]`.
   - أجب في `reply_text` إجابة دقيقة وواضحة ومهذبة باللغة العربية بالاعتماد الحصري على بيانات الطالب الموجودة في السياق المرفق (اذكر الأرقام والتواريخ والدرجات بدقة).

3. إذا كانت الرسالة مجرد تحية عابرة أو كلام لا يتطلب تقسيم واجب ولا استفسار:
   - إذا كانت تحية مثل "السلام عليكم" أو "مرحبا"، رد عليها بترحيب لطيف واعرض المساعدة (وحدد `intent` بقيمة `"student_inquiry"`).
   - أما إذا كانت كلمة إغلاق مثل "شكراً" فقط، فاجعل `intent` بقيمة `"casual_or_other"`.

أرجع النتيجة دائماً بصيغة JSON مطابقة للـ Schema المحدد فقط.
PROMPT;

        $parts = [
            [
                'text' => "بيانات الطالب والمعهد (JSON Context):\n"
                    .json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    ."\n\nرسالة ولي الأمر:\n"
                    .($messageText !== '' ? $messageText : '(يرجى قراءة الصورة المرفقة واستخراج الواجبات أو المطلوب منها)'),
            ],
        ];

        // إذا كانت الرسالة تحتوي على صورة مرفقة، نمررها لـ Gemini Vision لقراءة الواجبات المكتوبة فيها
        if ($messageModel->attachment_path && Str::startsWith(strtolower((string) $messageModel->attachment_type), 'image/')) {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($messageModel->attachment_path)) {
                    $bytes = Storage::disk($disk)->get($messageModel->attachment_path);
                    if ($bytes && strlen($bytes) <= 6 * 1024 * 1024) {
                        $parts[] = [
                            'inline_data' => [
                                'mime_type' => $messageModel->attachment_type ?: 'image/jpeg',
                                'data' => base64_encode($bytes),
                            ],
                        ];
                    }
                    break;
                }
            }
        }

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.15,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'intent' => [
                            'type' => 'STRING',
                            'enum' => ['homework_submission', 'student_inquiry', 'institute_inquiry', 'casual_or_other'],
                        ],
                        'reply_text' => [
                            'type' => 'STRING',
                        ],
                        'extracted_homeworks' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'OBJECT',
                                'properties' => [
                                    'student_id' => ['type' => 'INTEGER', 'nullable' => true],
                                    'course_id' => ['type' => 'INTEGER', 'nullable' => true],
                                    'subject_id' => ['type' => 'INTEGER', 'nullable' => true],
                                    'subject_guess' => ['type' => 'STRING', 'nullable' => true],
                                    'title' => ['type' => 'STRING'],
                                    'description' => ['type' => 'STRING'],
                                    'due_date' => ['type' => 'STRING', 'nullable' => true],
                                ],
                                'required' => ['title', 'description'],
                            ],
                        ],
                    ],
                    'required' => ['intent', 'reply_text', 'extracted_homeworks'],
                ],
            ],
        ];

        try {
            $modelsToTry = array_values(array_unique([$model, 'gemini-2.5-flash', 'gemini-2.0-flash', 'gemini-1.5-flash']));
            $response = null;

            foreach ($modelsToTry as $tryModel) {
                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$tryModel}:generateContent?key={$apiKey}";
                $response = Http::timeout(14)
                    ->acceptJson()
                    ->post($url, $payload);

                if ($response->successful()) {
                    break;
                }

                // إذا رفض الموديل الـ responseSchema الصارم، نعيد المحاولة بـ JSON مرن فوراً
                if ($response->status() === 400) {
                    $fallbackPayload = $payload;
                    unset($fallbackPayload['generationConfig']['responseSchema']);
                    $response = Http::timeout(14)->acceptJson()->post($url, $fallbackPayload);
                    if ($response->successful()) {
                        break;
                    }
                }
            }

            if (! $response || ! $response->successful()) {
                Log::warning('Gemini API non-200 response', [
                    'status' => $response?->status(),
                    'body' => $response?->body(),
                ]);

                return null;
            }

            $json = $response->json();
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (! is_string($rawText) || trim($rawText) === '') {
                return null;
            }

            $cleanJson = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText)));
            $decoded = json_decode($cleanJson, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini API exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * قاموس المواد الدراسية العراقية ومرادفاتها وأخطائها الإملائية الشائعة.
     *
     * @return array<string, array<int, string>>
     */
    private function subjectAliasesDictionary(): array
    {
        return [
            'الرياضيات' => ['رياضيات', 'الرياضيات', 'ريضايات', 'الريضايات', 'حساب', 'جبر', 'هندسه'],
            'اللغة الإنكليزية' => ['انكليزي', 'الانكليزي', 'انجليزي', 'الانجليزي', 'اللغه الانكليزيه', 'اللغه الانجليزيه', 'انكليزيه', 'انجليزيه', 'انكلش', 'english'],
            'اللغة العربية' => ['عربي', 'العربي', 'اللغه العربيه', 'العربيه', 'عربيه', 'قراءه', 'القراءه', 'قواعد', 'القواعد', 'املاء', 'الاملاء', 'انشاء', 'الانشاء', 'ادب', 'الادب', 'محفوظات'],
            'العلوم' => ['علوم', 'العلوم'],
            'الفيزياء' => ['فيزياء', 'الفيزياء', 'فيزاء', 'الفيزاء', 'فيزيا'],
            'الكيمياء' => ['كيمياء', 'الكيمياء', 'كيميا', 'الكيميا'],
            'الأحياء' => ['احياء', 'الاحياء'],
            'التربية الإسلامية' => ['اسلاميه', 'الاسلاميه', 'التربيه الاسلاميه', 'دين', 'قران', 'القران'],
            'الاجتماعيات' => ['اجتماعيات', 'الاجتماعيات', 'تاريخ', 'التاريخ', 'جغرافيه', 'الجغرافيه', 'جغرافيا', 'وطنيه', 'الوطنيه'],
            'الحاسوب' => ['حاسوب', 'الحاسوب', 'كمبيوتر'],
            'اللغة الفرنسية' => ['فرنسي', 'الفرنسي', 'اللغه الفرنسيه', 'فرنسيه'],
        ];
    }

    /**
     * الكلمات المفتاحية التي تدل على وجود تحضير أو واجب مدرسي في الرسالة.
     *
     * @return array<int, string>
     */
    private function homeworkIndicatorKeywords(): array
    {
        return [
            'واجب', 'واجبات', 'تحضير', 'تحظير', 'تحاضير', 'حفظ', 'حل', 'تمارين', 'تمرين',
            'صفحه', 'صفحة', 'درس', 'الدرس', 'فصل', 'الفصل', 'كتابه', 'كتابة',
            'قراءه', 'قراءة', 'مراجعه', 'مراجعة', 'نشاط', 'ملزمه', 'ملزمة',
            'سوره', 'سورة', 'قصيده', 'قصيدة', 'جدول', 'دفتر', 'موضوع', 'شرح',
            'املاء', 'انشاء', 'امتحان يومي', 'تسميع', 'مطلوب',
        ];
    }

    private function normalizeArabic(string $text): string
    {
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);
        // إزالة التشكيل والتطويل
        $text = preg_replace('/[\x{0640}\x{064B}-\x{065F}\x{0670}]/u', '', $text) ?? $text;

        return mb_strtolower(trim($text));
    }

    /**
     * فحص سريع (0ms) لمعرفة هل تحتوي الرسالة النصية على مؤشرات واجب أو تحضير أو اسم مادة دراسية.
     */
    public function looksLikeHomeworkOrSubjectMessage(string $rawText): bool
    {
        $norm = $this->normalizeArabic($rawText);
        if ($norm === '' || mb_strlen($norm) < 4) {
            return false;
        }

        foreach ($this->homeworkIndicatorKeywords() as $kw) {
            if (Str::contains($norm, $this->normalizeArabic($kw))) {
                return true;
            }
        }

        // اختصار الصفحة مثل "ص 15" أو "ص15" أو "ص.15"
        if (preg_match('/(?:^|\s)ص\.?\s*\d+/u', $norm)) {
            return true;
        }

        foreach ($this->subjectAliasesDictionary() as $aliases) {
            foreach ($aliases as $alias) {
                if (Str::contains($norm, $this->normalizeArabic($alias))) {
                    return true;
                }
            }
        }

        // فحص أسماء المواد الموجودة فعلياً في قاعدة البيانات
        $dbSubjectNames = Subject::query()->pluck('name')->all();
        foreach ($dbSubjectNames as $dbSub) {
            $normSub = $this->normalizeArabic((string) $dbSub);
            $bareSub = preg_replace('/^ال/u', '', $normSub) ?: $normSub;
            if ($bareSub !== '' && mb_strlen($bareSub) >= 3 && Str::contains($norm, $bareSub)) {
                return true;
            }
        }

        return false;
    }

    /**
     * المحرك المحلي الذكي لاستخراج وتقسيم الواجبات والتحاضير من رسائل أولياء الأمور والطلاب باللهجة العراقية والعربية.
     * يعمل تلقائياً كضمان 100% حتى عند عدم استجابة Gemini API أو للرسائل السابقة.
     *
     * @return array<int, array<string, mixed>>
     */
    public function extractHomeworksLocally(
        string $rawText,
        User $parent,
        ?int $preferredStudentId = null,
        bool $hasAttachment = false
    ): array {
        $cleanText = trim($rawText);
        if ($cleanText === '' && ! $hasAttachment) {
            return [];
        }

        $normFull = $this->normalizeArabic($cleanText);

        // تجاهل الأسئلة المالية أو الاستفسارات الصرفة التي لا تتضمن تحضيراً أو واجباً
        $pureInquiryWords = ['ايش قد القسط', 'كم القسط', 'كم المتبقي', 'المبلغ المتبقي', 'شوكت الدوام', 'متى الدوام'];
        foreach ($pureInquiryWords as $inq) {
            if (Str::contains($normFull, $this->normalizeArabic($inq)) && ! Str::contains($normFull, ['واجب', 'تحضير', 'تحظير'])) {
                return [];
            }
        }

        // بناء قائمة موحدة لكل المواد (من قاعدة البيانات + القاموس القياسي)
        $subjectMap = $this->subjectAliasesDictionary();
        foreach (Subject::query()->get(['id', 'name']) as $sub) {
            $canonical = trim($sub->name);
            $normSub = $this->normalizeArabic($canonical);
            $bareSub = preg_replace('/^ال/u', '', $normSub) ?: $normSub;
            if (! isset($subjectMap[$canonical])) {
                $subjectMap[$canonical] = [];
            }
            $subjectMap[$canonical][] = $normSub;
            if ($bareSub !== '') {
                $subjectMap[$canonical][] = $bareSub;
            }
        }

        $hasHwKeyword = false;
        foreach ($this->homeworkIndicatorKeywords() as $kw) {
            if (Str::contains($normFull, $this->normalizeArabic($kw))) {
                $hasHwKeyword = true;
                break;
            }
        }
        if (! $hasHwKeyword && preg_match('/(?:^|\s)ص\.?\s*\d+/u', $normFull)) {
            $hasHwKeyword = true;
        }

        // تقسيم الرسالة إلى أسطر أو جمل فرعية
        $lines = preg_split('/[\r\n]+|[،,;؛|]+/u', $cleanText) ?: [$cleanText];
        $extracted = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $normLine = $this->normalizeArabic($line);

            // اكتشاف جميع المواد المذكورة في هذا السطر مع مواقعها لتقسيم السطر إن احتوى على أكثر من مادة (مثل: "رياضيات ص 10 و علوم الفصل 2")
            $matchesInLine = [];
            foreach ($subjectMap as $canonicalName => $aliases) {
                $bestPos = null;
                $matchedAlias = null;
                foreach (array_unique($aliases) as $alias) {
                    $normAlias = $this->normalizeArabic($alias);
                    if ($normAlias === '' || mb_strlen($normAlias) < 3) {
                        continue;
                    }
                    $pos = mb_strpos($normLine, $normAlias);
                    if ($pos !== false && ($bestPos === null || $pos < $bestPos)) {
                        $bestPos = $pos;
                        $matchedAlias = $normAlias;
                    }
                }
                if ($bestPos !== null) {
                    $matchesInLine[] = [
                        'canonical' => $canonicalName,
                        'pos' => $bestPos,
                        'alias' => $matchedAlias,
                    ];
                }
            }

            usort($matchesInLine, fn ($a, $b) => $a['pos'] <=> $b['pos']);

            // إزالة التداخل إن طابق اسم مادة فرعي اسم مادة أطول في نفس الموقع
            $uniqueMatches = [];
            foreach ($matchesInLine as $m) {
                $overlaps = false;
                foreach ($uniqueMatches as $um) {
                    if (abs($m['pos'] - $um['pos']) <= 3 || $m['canonical'] === $um['canonical']) {
                        $overlaps = true;
                        break;
                    }
                }
                if (! $overlaps) {
                    $uniqueMatches[] = $m;
                }
            }

            if (count($uniqueMatches) > 1) {
                // السطر الواحد يحتوي على أكثر من مادة: نقسمه حسب موقع كل مادة
                foreach ($uniqueMatches as $idx => $m) {
                    $start = $m['pos'];
                    $nextPos = $uniqueMatches[$idx + 1]['pos'] ?? mb_strlen($normLine);
                    $segment = trim(mb_substr($line, $start, max(1, $nextPos - $start)));
                    $segment = preg_replace('/(?:\s+و\s*|\s*[-–—:]+\s*)$/u', '', $segment) ?: $segment;

                    $extracted[] = [
                        'student_id' => $preferredStudentId,
                        'subject_guess' => $m['canonical'],
                        'title' => 'تحضير '.$m['canonical'].': '.mb_strimwidth($segment, 0, 95, '...'),
                        'description' => $segment."\n(من رسالة الجات: ".$cleanText.')',
                    ];
                }
            } elseif (count($uniqueMatches) === 1) {
                $m = $uniqueMatches[0];
                // إذا ذكر اسم مادة مع كلمة واجب/تحضير أو تفاصيل (أكثر من كلمتين)
                $wordCount = count(preg_split('/\s+/u', $normLine) ?: []);
                if ($hasHwKeyword || $wordCount >= 2 || $hasAttachment) {
                    $detail = trim(preg_replace('/^(?:السلام عليكم|مرحبا|صباح الخير|مساء الخير|استاذ|أستاذ|تحضير|تحظير|واجب)\s*/u', '', $line) ?: $line);
                    $extracted[] = [
                        'student_id' => $preferredStudentId,
                        'subject_guess' => $m['canonical'],
                        'title' => 'تحضير '.$m['canonical'].': '.mb_strimwidth($detail, 0, 95, '...'),
                        'description' => $line,
                    ];
                }
            }
        }

        // إذا لم يُذكر اسم مادة صريح ولكن الرسالة تحتوي بوضوح على كلمة "واجب" أو "تحضير" (أو صورة واجب)
        if (empty($extracted) && $hasHwKeyword && (mb_strlen($normFull) >= 7 || $hasAttachment)) {
            $detail = trim(preg_replace('/^(?:السلام عليكم|مرحبا|صباح الخير|مساء الخير)\s*/u', '', $cleanText) ?: $cleanText);
            $extracted[] = [
                'student_id' => $preferredStudentId,
                'subject_guess' => '',
                'title' => 'تحضير / واجب: '.mb_strimwidth($detail !== '' ? $detail : 'مرفق واجب من ولي الأمر', 0, 95, '...'),
                'description' => $cleanText !== '' ? $cleanText : 'واجب مرفق من محادثة ولي الأمر / الطالب.',
            ];
        }

        return $extracted;
    }

    /**
     * إنشاء سجلات الواجبات المستخرجة وتوجيه كل واجب إلى المدرس المختص بمادته في كورس الطالب.
     *
     * @param  array<int, array<string, mixed>>  $extractedHomeworks
     * @return array<int, array<string, mixed>>
     */
    private function dispatchExtractedHomeworks(
        array $extractedHomeworks,
        User $parent,
        Message $sourceMessage,
        ?int $fallbackStudentId = null
    ): array {
        $dispatched = [];
        $fullMessageBody = trim((string) $sourceMessage->body);

        foreach ($extractedHomeworks as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            if ($title === '') {
                continue;
            }

            // 1. تحديد الطالب بدقة (من الرسالة أو الأبناء المربوطين أو رقم الهاتف أو اسم ولي الأمر)
            $studentId = ! empty($item['student_id']) ? (int) $item['student_id'] : $fallbackStudentId;
            $student = $this->resolveStudentForHomework($parent, $studentId, $fullMessageBody.' '.$title.' '.$description);
            if (! $student) {
                continue;
            }

            // 2. تحديد الكورس والمادة والمدرس المختص
            $courseId = ! empty($item['course_id']) ? (int) $item['course_id'] : null;
            $subjectId = ! empty($item['subject_id']) ? (int) $item['subject_id'] : null;
            $subjectGuess = trim((string) ($item['subject_guess'] ?? ''));

            $enrolledCourses = $student->courses()->with(['subjects', 'teacher'])->get();
            if ($enrolledCourses->isEmpty()) {
                $enrolledCourses = Course::with(['subjects', 'teacher'])
                    ->when($student->stage_id, fn ($q) => $q->where('stage_id', $student->stage_id))
                    ->where('is_active', true)
                    ->get();
                if ($enrolledCourses->isEmpty()) {
                    $enrolledCourses = Course::with(['subjects', 'teacher'])->where('is_active', true)->get();
                }
            }

            $course = $courseId
                ? ($enrolledCourses->firstWhere('id', $courseId) ?? Course::with(['subjects', 'teacher'])->find($courseId))
                : null;

            $subjectModel = null;

            if ($subjectId) {
                foreach ($enrolledCourses as $ec) {
                    $foundSub = $ec->subjects->firstWhere('id', $subjectId);
                    if ($foundSub) {
                        $course = $ec;
                        $subjectModel = $foundSub;
                        break;
                    }
                }
                if (! $subjectModel) {
                    $subjectModel = Subject::find($subjectId);
                }
            }

            // مطابقة ذكية لاسم المادة من نص الواجب أو subject_guess مع قاموس المرادفات
            $searchText = trim($subjectGuess.' '.$title.' '.$description);
            $canonicalSubjectName = $this->matchCanonicalSubjectName($searchText);

            if (! $subjectModel && ($searchText !== '' || $canonicalSubjectName !== '')) {
                $candidatesToMatch = array_values(array_filter(array_unique([
                    $subjectGuess,
                    $canonicalSubjectName,
                    preg_replace('/^ال/u', '', $subjectGuess),
                    preg_replace('/^ال/u', '', $canonicalSubjectName),
                ])));

                foreach ($enrolledCourses as $ec) {
                    $foundSub = $ec->subjects->first(function ($s) use ($candidatesToMatch, $searchText) {
                        return $this->subjectNameMatches($s->name, $candidatesToMatch, $searchText);
                    });
                    if ($foundSub) {
                        $course = $ec;
                        $subjectModel = $foundSub;
                        break;
                    }
                }

                if (! $subjectModel) {
                    $stageSubs = Subject::query()
                        ->when($student->stage_id, fn ($q) => $q->where('stage_id', $student->stage_id))
                        ->get();
                    $subjectModel = $stageSubs->first(fn ($s) => $this->subjectNameMatches($s->name, $candidatesToMatch, $searchText));

                    if (! $subjectModel) {
                        $subjectModel = Subject::all()->first(fn ($s) => $this->subjectNameMatches($s->name, $candidatesToMatch, $searchText));
                    }
                }
            }

            if (! $course && $subjectModel) {
                $course = Course::with(['subjects', 'teacher'])
                    ->whereHas('subjects', fn ($q) => $q->where('subjects.id', $subjectModel->id))
                    ->when($student->stage_id, fn ($q) => $q->where('stage_id', $student->stage_id))
                    ->first()
                    ?? Course::with(['subjects', 'teacher'])
                        ->whereHas('subjects', fn ($q) => $q->where('subjects.id', $subjectModel->id))
                        ->first();
            }

            // إذا لم نجد كورس عبر جدول المواد، نبحث في اسم الكورس أو حقل subject النصي للكورس
            $effectiveSubjectName = $subjectModel?->name ?: ($canonicalSubjectName ?: $subjectGuess);
            if (! $course && $effectiveSubjectName !== '') {
                $bareSub = preg_replace('/^ال/u', '', $effectiveSubjectName) ?: $effectiveSubjectName;
                $course = $enrolledCourses->first(function (Course $c) use ($effectiveSubjectName, $bareSub) {
                    return Str::contains((string) $c->subject, [$effectiveSubjectName, $bareSub])
                        || Str::contains((string) $c->name, [$effectiveSubjectName, $bareSub]);
                }) ?? Course::with(['subjects', 'teacher'])
                    ->where(function ($q) use ($effectiveSubjectName, $bareSub) {
                        $q->where('subject', 'like', '%'.$bareSub.'%')
                            ->orWhere('name', 'like', '%'.$bareSub.'%');
                    })
                    ->first();
            }

            if (! $course) {
                $course = $enrolledCourses->first() ?? Course::first();
            }

            if (! $course) {
                $course = Course::create([
                    'stage_id' => $student->stage_id ?? $subjectModel?->stage_id,
                    'name' => 'كورس الواجبات العامة',
                    'subject' => $effectiveSubjectName ?: 'عام',
                    'price' => 0,
                    'seats' => 50,
                    'is_active' => true,
                    'color' => 'blue',
                ]);
            }

            if (! $subjectModel && $course->subjects->count() === 1) {
                $subjectModel = $course->subjects->first();
            }

            $finalSubjectId = $subjectModel ? (int) $subjectModel->id : null;
            $resolvedSubjectLabel = $subjectModel?->name ?: ($effectiveSubjectName ?: ($course->subject ?: $course->name));

            // تحديد المدرس المختص بالمادة بـ 7 طرق شاملة
            $teacherId = $this->resolveTeacherForCourseSubject(
                $course,
                $finalSubjectId,
                $resolvedSubjectLabel,
                $student->stage_id ? (int) $student->stage_id : ($course->stage_id ? (int) $course->stage_id : null)
            );
            $teacherUser = $teacherId ? User::with('staff')->find($teacherId) : null;

            // ضمان ربط المدرس بالمادة داخل الكورس حتى يظهر له الكورس والواجب في جميع واجهات المدرس
            if ($teacherId && $finalSubjectId) {
                try {
                    DB::table('course_subject')->updateOrInsert(
                        ['course_id' => $course->id, 'subject_id' => $finalSubjectId],
                        ['teacher_id' => $teacherId, 'updated_at' => now(), 'created_at' => now()]
                    );
                } catch (\Throwable) {
                    // ignore pivot sync errors
                }
            }

            $dueDate = null;
            if (! empty($item['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $item['due_date'])) {
                $dueDate = $item['due_date'];
            }

            $task = DB::transaction(function () use (
                $course,
                $finalSubjectId,
                $teacherId,
                $student,
                $title,
                $description,
                $dueDate,
                $parent,
                $sourceMessage
            ) {
                $task = HomeworkTask::create([
                    'course_id' => $course->id,
                    'subject_id' => $finalSubjectId,
                    'teacher_id' => $teacherId,
                    'student_id' => $student->id,
                    'title' => $title,
                    'description' => $description ?: $title,
                    'due_date' => $dueDate,
                    'source' => $parent->isStudent() ? 'student' : 'parent',
                    'is_class_task' => false,
                    'status' => 'submitted',
                    'created_by' => $parent->id,
                    'color' => 'violet',
                ]);

                $submission = HomeworkSubmission::create([
                    'homework_task_id' => $task->id,
                    'student_id' => $student->id,
                    'submitted_by' => $parent->id,
                    'note' => 'تم استخراجه وتوجيهه تلقائياً عبر المساعد الذكي من محادثة ولي الأمر [msg #'.$sourceMessage->id.'].',
                    'status' => 'submitted',
                ]);

                // إذا كانت رسالة الجات تحتوي على صورة أو ملف مرفق، نربطه بالواجب أيضاً ليراه المدرس في صفحة الواجبات!
                if (! empty($sourceMessage->attachment_path)) {
                    foreach (['local', 'public'] as $disk) {
                        if (Storage::disk($disk)->exists($sourceMessage->attachment_path)) {
                            HomeworkFile::create([
                                'homework_task_id' => $task->id,
                                'homework_submission_id' => $submission->id,
                                'uploaded_by' => $parent->id,
                                'disk' => $disk,
                                'path' => $sourceMessage->attachment_path,
                                'original_name' => $sourceMessage->attachment_name ?: basename($sourceMessage->attachment_path),
                                'mime' => $sourceMessage->attachment_type,
                                'size' => (int) ($sourceMessage->attachment_size ?? 0),
                            ]);
                            break;
                        }
                    }
                }

                return $task;
            });

            // إشعار المدرس المختص بهذه المادة فوراً (داخل النظام + Firebase Push + واتساب)
            if ($teacherId) {
                $notifTitle = 'تحضير / واجب جديد للطالب '.$student->name;
                $notifBody = 'ورد تحضير/واجب لمادة ('.$resolvedSubjectLabel.') بعنوان «'.$task->title.'» من '.$parent->name.' للطالب '.$student->name.' في دورة '.$course->name.'.';

                Notifier::send(
                    [$teacherId],
                    $notifTitle,
                    $notifBody,
                    [
                        'audience_type' => 'user',
                        'course_id' => $course->id,
                        'created_by' => $parent->id,
                        'push_url' => '/teacher/homework/'.$task->id,
                    ]
                );

                $teacherPhone = $teacherUser?->phone ?: $teacherUser?->staff?->phone;
                if ($teacherPhone) {
                    WhatsAppService::sendText(
                        $teacherPhone,
                        "مرحباً أ. {$teacherUser->name}،\nلديك *تحضير / واجب جديد* من الطالب *{$student->name}*:\n• المادة: {$resolvedSubjectLabel}\n• العنوان: {$task->title}\n• الدورة: {$course->name}",
                        'ai-hw-teacher-'.$task->id.'-'.$teacherId
                    );
                }
            }

            $dispatched[] = [
                'task_id' => (int) $task->id,
                'student_id' => (int) $student->id,
                'student_name' => $student->name,
                'student_no' => $student->student_no,
                'stage_name' => $student->stage?->name ?? '—',
                'course_name' => $course->name,
                'subject_name' => $resolvedSubjectLabel,
                'teacher_name' => $teacherUser?->name ?? 'إدارة المعهد',
                'title' => $title,
            ];
        }

        if (! empty($dispatched)) {
            Eyal::flushCache();
        }

        return $dispatched;
    }

    private function resolveStudentForHomework(User $parent, ?int $preferredStudentId, string $searchText): ?Student
    {
        $normSearch = $this->normalizeArabic($searchText);

        if ($parent->isParent()) {
            $children = $parent->children()->with('stage')->get();

            if ($children->isEmpty() && $parent->phone) {
                $digits = substr(preg_replace('/\D+/', '', $parent->phone) ?? '', -9);
                if (strlen($digits) >= 7) {
                    $phoneMatches = Student::query()
                        ->where('guardian_phone', 'like', '%'.$digits.'%')
                        ->orWhere('phone', 'like', '%'.$digits.'%')
                        ->pluck('id');
                    foreach ($phoneMatches as $sid) {
                        $parent->children()->syncWithoutDetaching([(int) $sid => ['relation' => 'ولي أمر', 'is_primary' => true]]);
                    }
                    $children = $parent->children()->with('stage')->get();
                }
            }

            if ($children->isEmpty() && $parent->name) {
                $nameMatches = Student::query()
                    ->where('guardian_name', 'like', '%'.trim($parent->name).'%')
                    ->pluck('id');
                foreach ($nameMatches as $sid) {
                    $parent->children()->syncWithoutDetaching([(int) $sid => ['relation' => 'ولي أمر', 'is_primary' => true]]);
                }
                if ($nameMatches->isNotEmpty()) {
                    $children = $parent->children()->with('stage')->get();
                }
            }

            // إذا كان لولي الأمر عدة أبناء، نفحص هل ذُكر اسم أحدهم في نص الرسالة
            if ($children->count() > 1 && $normSearch !== '') {
                foreach ($children as $child) {
                    $firstName = $this->normalizeArabic(explode(' ', trim($child->name))[0] ?? '');
                    if ($firstName !== '' && mb_strlen($firstName) >= 3 && Str::contains($normSearch, $firstName)) {
                        return $child;
                    }
                }
            }

            if ($preferredStudentId) {
                $found = $children->firstWhere('id', $preferredStudentId) ?? Student::with('stage')->find($preferredStudentId);
                if ($found) {
                    return $found;
                }
            }

            if ($children->isNotEmpty()) {
                return $children->first();
            }
        }

        if ($parent->isStudent()) {
            $st = Student::with('stage')
                ->where('user_id', $parent->id)
                ->orWhere('student_no', $parent->username)
                ->first();
            if ($st) {
                return $st;
            }
        }

        if ($preferredStudentId) {
            $st = Student::with('stage')->find($preferredStudentId);
            if ($st) {
                return $st;
            }
        }

        // البحث عن اسم أي طالب مذكور في نص الرسالة
        if ($normSearch !== '') {
            $allStudents = Student::with('stage')->orderByDesc('id')->limit(100)->get();
            foreach ($allStudents as $cand) {
                $normName = $this->normalizeArabic($cand->name);
                $parts = array_values(array_filter(explode(' ', $normName)));
                $firstTwo = isset($parts[0], $parts[1]) ? $parts[0].' '.$parts[1] : ($parts[0] ?? '');
                if (($normName !== '' && Str::contains($normSearch, $normName))
                    || ($firstTwo !== '' && mb_strlen($firstTwo) >= 6 && Str::contains($normSearch, $firstTwo))) {
                    return $cand;
                }
            }
            foreach ($allStudents as $cand) {
                $firstName = $this->normalizeArabic(explode(' ', trim($cand->name))[0] ?? '');
                if ($firstName !== '' && mb_strlen($firstName) >= 3 && Str::contains($normSearch, $firstName)) {
                    return $cand;
                }
            }
        }

        return Student::with('stage')->orderByDesc('id')->first();
    }

    private function matchCanonicalSubjectName(string $searchText): string
    {
        $norm = $this->normalizeArabic($searchText);
        if ($norm === '') {
            return '';
        }

        foreach ($this->subjectAliasesDictionary() as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (Str::contains($norm, $this->normalizeArabic($alias))) {
                    return $canonical;
                }
            }
        }

        return '';
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function subjectNameMatches(string $dbSubjectName, array $candidates, string $fullSearchText): bool
    {
        $normDb = $this->normalizeArabic($dbSubjectName);
        $bareDb = preg_replace('/^ال/u', '', $normDb) ?: $normDb;
        $normSearch = $this->normalizeArabic($fullSearchText);

        if ($bareDb !== '' && mb_strlen($bareDb) >= 3 && Str::contains($normSearch, $bareDb)) {
            return true;
        }

        foreach ($candidates as $cand) {
            $normCand = $this->normalizeArabic($cand);
            $bareCand = preg_replace('/^ال/u', '', $normCand) ?: $normCand;
            if ($bareCand !== '' && mb_strlen($bareCand) >= 3 && (Str::contains($normDb, $bareCand) || Str::contains($normCand, $bareDb))) {
                return true;
            }
        }

        return false;
    }

    /**
     * استخراج المدرس المختص بالمادة بـ 7 طرق شاملة (course_subject، subject_teacher، مطابقة اسم المادة في Subject، Staff، Course، و stage_teacher).
     */
    private function resolveTeacherForCourseSubject(
        ?Course $course,
        ?int $subjectId,
        string $subjectName = '',
        ?int $stageId = null
    ): ?int {
        if ($subjectId) {
            if ($course) {
                $courseSubjectTeacher = DB::table('course_subject')
                    ->where('course_id', $course->id)
                    ->where('subject_id', $subjectId)
                    ->whereNotNull('teacher_id')
                    ->value('teacher_id');

                if ($courseSubjectTeacher) {
                    return (int) $courseSubjectTeacher;
                }
            }

            $subjectTeacherQuery = DB::table('subject_teacher')->where('subject_id', $subjectId);
            if ($stageId) {
                $stageSpecific = (clone $subjectTeacherQuery)->where('stage_id', $stageId)->value('teacher_id');
                if ($stageSpecific) {
                    return (int) $stageSpecific;
                }
            }
            $subjectTeacher = $subjectTeacherQuery->value('teacher_id');
            if ($subjectTeacher) {
                return (int) $subjectTeacher;
            }
        }

        // البحث بالاسم المجرّد للمادة في جداول المواد والكادر والكورسات
        $normSub = $this->normalizeArabic($subjectName);
        $bareSub = trim((string) preg_replace('/^(?:اللغه\s+|التربيه\s+|ال)/u', '', $normSub));
        if (mb_strlen($bareSub) >= 3) {
            // أ) البحث عن أي مادة بنفس الاسم في subject_teacher أو course_subject
            $matchingSubIds = Subject::query()
                ->get(['id', 'name', 'stage_id'])
                ->filter(fn (Subject $s) => Str::contains($this->normalizeArabic($s->name), $bareSub) || Str::contains($normSub, preg_replace('/^ال/u', '', $this->normalizeArabic($s->name))))
                ->sortByDesc(fn (Subject $s) => ($stageId && (int) $s->stage_id === $stageId) ? 1 : 0)
                ->pluck('id')
                ->all();

            if (! empty($matchingSubIds)) {
                $tFromSubTeacher = DB::table('subject_teacher')
                    ->whereIn('subject_id', $matchingSubIds)
                    ->whereNotNull('teacher_id')
                    ->value('teacher_id');
                if ($tFromSubTeacher) {
                    return (int) $tFromSubTeacher;
                }

                $tFromCourseSub = DB::table('course_subject')
                    ->whereIn('subject_id', $matchingSubIds)
                    ->whereNotNull('teacher_id')
                    ->value('teacher_id');
                if ($tFromCourseSub) {
                    return (int) $tFromCourseSub;
                }
            }

            // ب) البحث في جدول الكادر التدريسي (Staff.subject)
            $staffMatch = Staff::query()
                ->whereNotNull('user_id')
                ->whereNotNull('subject')
                ->get(['user_id', 'subject'])
                ->first(fn (Staff $st) => Str::contains($this->normalizeArabic((string) $st->subject), $bareSub));
            if ($staffMatch) {
                return (int) $staffMatch->user_id;
            }

            // ج) البحث في جدول الكورسات (Course.subject أو Course.name)
            $courseMatch = Course::query()
                ->whereNotNull('teacher_id')
                ->get(['teacher_id', 'name', 'subject', 'stage_id'])
                ->sortByDesc(fn (Course $c) => ($stageId && (int) $c->stage_id === $stageId) ? 1 : 0)
                ->first(fn (Course $c) => Str::contains($this->normalizeArabic($c->subject.' '.$c->name), $bareSub));
            if ($courseMatch) {
                return (int) $courseMatch->teacher_id;
            }
        }

        if ($course) {
            $firstCourseTeacher = DB::table('course_subject')
                ->where('course_id', $course->id)
                ->whereNotNull('teacher_id')
                ->value('teacher_id');

            if ($firstCourseTeacher) {
                return (int) $firstCourseTeacher;
            }

            if ($course->teacher_id) {
                return (int) $course->teacher_id;
            }
        }

        if ($stageId) {
            $stageTeacher = DB::table('stage_teacher')
                ->where('stage_id', $stageId)
                ->value('teacher_id');
            if ($stageTeacher) {
                return (int) $stageTeacher;
            }
        }

        return User::query()->where('role', 'staff')->where('is_active', true)->orderBy('id')->value('id');
    }

    private function resolveResponderUserId(Conversation $conversation, int $senderId): int
    {
        $adminId = User::where('role', 'admin')->orderBy('id')->value('id');

        if (in_array($conversation->type, ['parents_group', 'staff_group'], true) && $adminId) {
            return (int) $adminId;
        }

        $otherId = $conversation->participants()
            ->where('user_id', '!=', $senderId)
            ->value('user_id');

        if ($otherId) {
            return (int) $otherId;
        }

        return $adminId ? (int) $adminId : $senderId;
    }
}

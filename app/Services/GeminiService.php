<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Course;
use App\Models\HomeworkFile;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\Eyal;
use App\Support\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeminiService
{
    public static function apiKey(): string
    {
        return trim((string) Setting::get('ai.gemini_api_key', ''));
    }

    public static function model(): string
    {
        return trim((string) Setting::get('ai.model', 'gemini-2.5-flash')) ?: 'gemini-2.5-flash';
    }

    public static function isEnabled(): bool
    {
        return (bool) Setting::get('features.ai_enabled', true) && self::apiKey() !== '';
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
     * معالجة رسالة ولي الأمر في الجات:
     * 1) إذا كانت تحتوي على واجبات (نصاً أو صورة): يقسمها حسب المواد ويوزع كل واجب للمدرس المختص ويرسل بطاقة تأكيد.
     * 2) إذا كانت استفساراً عن الطالب (درجات، حضور، أقساط، مواد، جدول): يرد تلقائياً من بيانات الطالب الفعلية.
     */
    public function processParentChatMessage(
        Message $parentMessage,
        Conversation $conversation,
        User $parent,
        bool $forceManual = false
    ): ?Message {
        if (! $forceManual && ! self::isEnabled()) {
            return null;
        }

        $apiKey = self::apiKey();
        if ($apiKey === '') {
            return null;
        }

        $body = trim((string) $parentMessage->body);
        $hasAttachment = ! empty($parentMessage->attachment_path);

        if ($body === '' && ! $hasAttachment) {
            return null;
        }

        // فلتر سريع لتجنب استهلاك الـ API على التحيات القصيرة جداً مثل "شكرا" أو "تمام" في الوضع التلقائي
        if (! $forceManual && ! $hasAttachment && mb_strlen($body) < 4) {
            return null;
        }

        $context = $this->buildParentStudentContext($parent, $conversation->student_id);
        if (empty($context['students'])) {
            if (! $forceManual) {
                return null;
            }
        }

        $aiResult = $this->callGeminiForChat($body, $parentMessage, $context, $forceManual);
        if (! is_array($aiResult)) {
            return null;
        }

        $intent = (string) ($aiResult['intent'] ?? 'other');
        $extractedHomeworks = is_array($aiResult['extracted_homeworks'] ?? null)
            ? $aiResult['extracted_homeworks']
            : [];
        $replyText = trim((string) ($aiResult['reply_text'] ?? ''));

        $dispatchedTasks = [];

        // 1. إذا اكتشف الذكاء الاصطناعي واجبات دراسية، نقسمها ونوزعها على المدرسين المختصين
        if (! empty($extractedHomeworks) && ($forceManual || self::isAutoHomeworkEnabled())) {
            $dispatchedTasks = $this->dispatchExtractedHomeworks(
                $extractedHomeworks,
                $parent,
                $parentMessage,
                $conversation->student_id
            );
        }

        // هل نرسل رداً تلقائياً في الجات؟
        $shouldSendReply = false;
        if (! empty($dispatchedTasks)) {
            $shouldSendReply = true;
        } elseif ($forceManual && $replyText !== '') {
            $shouldSendReply = true;
        } elseif (self::isAutoReplyEnabled() && in_array($intent, ['student_inquiry', 'homework_submission', 'institute_inquiry'], true) && $replyText !== '') {
            $shouldSendReply = true;
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

        return $aiMessage;
    }

    /**
     * بناء السياق الكامل لأبناء ولي الأمر (الكورسات، المواد، المدرسين، الدرجات، الحضور، الواجبات، الحساب المالي).
     *
     * @return array<string, mixed>
     */
    private function buildParentStudentContext(User $parent, ?int $preferredStudentId = null): array
    {
        Eyal::ensureCurriculumTables();

        $childrenQuery = $parent->isParent()
            ? $parent->children()
            : Student::query()->when($preferredStudentId, fn ($q) => $q->where('id', $preferredStudentId))->limit(5);

        $students = $childrenQuery
            ->with([
                'stage:id,name',
                'courses' => fn ($q) => $q->where('is_active', true)->with(['stage:id,name', 'teacher:id,name', 'subjects:id,stage_id,name', 'subjectTeachers:id,name']),
            ])
            ->get();

        if ($students->isEmpty() && $preferredStudentId) {
            $single = Student::with([
                'stage:id,name',
                'courses' => fn ($q) => $q->with(['stage:id,name', 'teacher:id,name', 'subjects:id,stage_id,name', 'subjectTeachers:id,name']),
            ])->find($preferredStudentId);
            if ($single) {
                $students = collect([$single]);
            }
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

        return [
            'today_date' => now()->toDateString(),
            'institute' => [
                'name' => $institute['name'] ?? 'معهد آيلا التعليمي',
                'work_hours' => $institute['work_hours'] ?? '',
                'phone' => $institute['phone'] ?? '',
            ],
            'parent_name' => $parent->name,
            'preferred_student_id' => $preferredStudentId,
            'students' => $studentsData,
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
                                    'student_id' => ['type' => 'INTEGER'],
                                    'course_id' => ['type' => 'INTEGER'],
                                    'subject_id' => ['type' => 'INTEGER'],
                                    'subject_guess' => ['type' => 'STRING'],
                                    'title' => ['type' => 'STRING'],
                                    'description' => ['type' => 'STRING'],
                                    'due_date' => ['type' => 'STRING'],
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
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::timeout(18)
                ->acceptJson()
                ->post($url, $payload);

            if (! $response->successful()) {
                Log::warning('Gemini API non-200 response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $json = $response->json();
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if (! is_string($rawText) || trim($rawText) === '') {
                return null;
            }

            $decoded = json_decode($rawText, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('Gemini API exception: '.$e->getMessage());

            return null;
        }
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

        foreach ($extractedHomeworks as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            if ($title === '') {
                continue;
            }

            // 1. تحديد الطالب
            $studentId = ! empty($item['student_id']) ? (int) $item['student_id'] : $fallbackStudentId;
            $student = null;
            if ($studentId) {
                $student = $parent->isParent()
                    ? $parent->children()->with('stage')->where('students.id', $studentId)->first()
                    : Student::with('stage')->find($studentId);
            }
            if (! $student && $parent->isParent()) {
                $student = $parent->children()->with('stage')->first();
            }
            if (! $student) {
                continue;
            }

            // 2. تحديد الكورس والمادة والمدرس المختص
            $courseId = ! empty($item['course_id']) ? (int) $item['course_id'] : null;
            $subjectId = ! empty($item['subject_id']) ? (int) $item['subject_id'] : null;
            $subjectGuess = trim((string) ($item['subject_guess'] ?? ''));

            $enrolledCourses = $student->courses()->with(['subjects', 'teacher'])->get();
            if ($enrolledCourses->isEmpty()) {
                continue;
            }

            $course = $courseId
                ? ($enrolledCourses->firstWhere('id', $courseId) ?? $enrolledCourses->first())
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

            // مطابقة ذكية لاسم المادة إن لم يرسل الموديل رقم المادة الدقيق
            if (! $subjectModel && $subjectGuess !== '') {
                $bareGuess = preg_replace('/^ال/u', '', $subjectGuess);
                foreach ($enrolledCourses as $ec) {
                    $foundSub = $ec->subjects->first(function ($s) use ($subjectGuess, $bareGuess) {
                        return Str::contains($s->name, [$subjectGuess, $bareGuess])
                            || Str::contains($subjectGuess, $s->name);
                    });
                    if ($foundSub) {
                        $course = $ec;
                        $subjectModel = $foundSub;
                        break;
                    }
                }
            }

            if (! $course) {
                $course = $enrolledCourses->first();
            }

            if (! $subjectModel && $course->subjects->count() === 1) {
                $subjectModel = $course->subjects->first();
            }

            $finalSubjectId = $subjectModel ? (int) $subjectModel->id : null;
            $teacherId = $this->resolveTeacherForCourseSubject($course, $finalSubjectId);
            $teacherUser = $teacherId ? User::find($teacherId) : null;

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
                    'source' => 'parent',
                    'is_class_task' => false,
                    'status' => 'submitted',
                    'created_by' => $parent->id,
                    'color' => 'violet',
                ]);

                $submission = HomeworkSubmission::create([
                    'homework_task_id' => $task->id,
                    'student_id' => $student->id,
                    'submitted_by' => $parent->id,
                    'note' => 'تم استخراجه وتوجيهه تلقائياً عبر المساعد الذكي من محادثة ولي الأمر.',
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
                $subName = $subjectModel?->name ?? $course->subject ?? $course->name;
                $notifTitle = 'تحضير / واجب جديد للطالب '.$student->name;
                $notifBody = 'ورد تحضير/واجب لمادة ('.$subName.') بعنوان «'.$task->title.'» من '.$parent->name.' للطالب '.$student->name.' في دورة '.$course->name.'.';

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
                        "مرحباً أ. {$teacherUser->name}،\nلديك *تحضير / واجب جديد* من الطالب *{$student->name}*:\n• المادة: {$subName}\n• العنوان: {$task->title}\n• الدورة: {$course->name}",
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
                'subject_name' => $subjectModel?->name ?? ($course->subject ?: $course->name),
                'teacher_name' => $teacherUser?->name ?? 'إدارة المعهد',
                'title' => $title,
            ];
        }

        if (! empty($dispatched)) {
            Eyal::flushCache();
        }

        return $dispatched;
    }

    private function resolveTeacherForCourseSubject(Course $course, ?int $subjectId): ?int
    {
        if ($subjectId) {
            $courseSubjectTeacher = DB::table('course_subject')
                ->where('course_id', $course->id)
                ->where('subject_id', $subjectId)
                ->whereNotNull('teacher_id')
                ->value('teacher_id');

            if ($courseSubjectTeacher) {
                return (int) $courseSubjectTeacher;
            }

            $subjectTeacher = DB::table('subject_teacher')
                ->where('subject_id', $subjectId)
                ->value('teacher_id');

            if ($subjectTeacher) {
                return (int) $subjectTeacher;
            }
        }

        $firstCourseTeacher = DB::table('course_subject')
            ->where('course_id', $course->id)
            ->whereNotNull('teacher_id')
            ->value('teacher_id');

        if ($firstCourseTeacher) {
            return (int) $firstCourseTeacher;
        }

        return $course->teacher_id ? (int) $course->teacher_id : null;
    }

    private function resolveResponderUserId(Conversation $conversation, int $senderId): int
    {
        $otherId = $conversation->participants()
            ->where('user_id', '!=', $senderId)
            ->value('user_id');

        if ($otherId) {
            return (int) $otherId;
        }

        $adminId = User::where('role', 'admin')->value('id');

        return $adminId ? (int) $adminId : $senderId;
    }
}

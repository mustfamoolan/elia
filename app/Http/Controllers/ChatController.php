<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Course;
use App\Models\Grade;
use App\Models\HomeworkTask;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * عرض صفحة المحادثات الرئيسية.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if(!$user || $user->isStudent(), 403, 'خدمة المحادثات مخصصة لأولياء الأمور والكادر التدريسي وإدارة المعهد فقط.');

        $institute = Eyal::institute();
        $chatEnabled = (bool) ($institute['chat_enabled'] ?? true);

        // جلب المحادثات الخاصة بالمستخدم مع أحدث رسالة والمشاركين
        $conversations = $user->conversations()
            ->with(['users', 'student.stage', 'latestMessage.sender', 'participants'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Conversation $conv) use ($user) {
                $other = $conv->otherUser($user->id);
                return [
                    'id' => $conv->id,
                    'type' => $conv->type,
                    'title' => $conv->titleFor($user),
                    'other_user_id' => $other?->id,
                    'other_user_name' => $other?->name ?? 'مستخدم',
                    'other_user_role' => $other?->roleLabel() ?? '',
                    'student_name' => $conv->student?->name,
                    'student_id' => $conv->student_id,
                    'unread_count' => $conv->unreadCountFor($user->id),
                    'last_message' => $conv->latestMessage?->body ?: ($conv->latestMessage?->hasCard() ? '📎 مشاركة بطاقة بيانات' : ($conv->latestMessage?->attachment_name ? '📎 ملف مرفق' : '')),
                    'last_message_time' => $conv->latestMessage?->created_at?->diffForHumans() ?? '',
                    'is_closed' => (bool) $conv->is_closed,
                ];
            });

        // جهات الاتصال المتاحة لبدء محادثة جديدة حسب الصلاحيات
        $contacts = $this->getAvailableContacts($user);

        // قائمة الأبناء المتاحين لولي الأمر لمشاركة بطاقاتهم
        $myChildren = $user->isParent() ? Eyal::childrenForParent($user->id) : collect();

        // المحادثة المحددة إن وجدت
        $activeConversationId = (int) $request->query('conversation_id');
        $targetUserId = (int) $request->query('user_id');
        $targetStudentId = (int) $request->query('student_id');

        // إذا تم طلب فتح شات مع مستخدم محدد مباشرة
        if (!$activeConversationId && $targetUserId) {
            $existing = $this->findExistingConversation($user->id, $targetUserId, $targetStudentId ?: null);
            if ($existing) {
                $activeConversationId = $existing->id;
            }
        }

        if (!$activeConversationId && $conversations->isNotEmpty()) {
            $activeConversationId = $conversations->first()['id'];
        }

        // اختيار القالب المناسب حسب نوع المستخدم
        $layout = match ($user->role) {
            'admin' => 'layouts.admin',
            'staff' => 'layouts.teacher',
            default => 'layouts.public',
        };

        return view('chat.index', [
            'user' => $user,
            'conversations' => $conversations,
            'contacts' => $contacts,
            'myChildren' => $myChildren,
            'activeConversationId' => $activeConversationId,
            'chatEnabled' => $chatEnabled,
            'layout' => $layout,
            'institute' => $institute,
        ]);
    }

    /**
     * استرجاع رسائل محادثة معينة.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isParticipant($conversation, $user->id), 403);

        $afterId = (int) $request->query('after', 0);

        $query = $conversation->messages()
            ->with('sender')
            ->where('id', '>', $afterId)
            ->orderBy('id', 'asc');

        // إذا كان الاستعلام الأول للمحادثة ولم يُحدد after، نجلب آخر 60 رسالة
        if ($afterId === 0) {
            $query->limit(60);
        }

        $messages = $query->get()->map(function (Message $msg) use ($user) {
            return $this->formatMessage($msg, $user->id);
        });

        // تحديث تاريخ آخر قراءة
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'messages' => $messages,
            'is_closed' => (bool) $conversation->is_closed,
        ]);
    }

    /**
     * إرسال رسالة جديدة (نص أو ملف أو بطاقة بيانات طالب).
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isParticipant($conversation, $user->id), 403);
        abort_if($conversation->is_closed, 422, 'تم إغلاق هذه المحادثة.');

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:10240'], // 10MB
            'card_type' => ['nullable', 'string', 'in:attendance,grade,homework,overview'],
            'student_id' => ['nullable', 'exists:students,id'],
            'item_id' => ['nullable', 'integer'],
        ]);

        if (empty($validated['body']) && !$request->hasFile('file') && empty($validated['card_type'])) {
            return response()->json(['message' => 'الرسالة لا يمكن أن تكون فارغة.'], 422);
        }

        // بناء بطاقة البيانات إن طُلبت
        $metadata = null;
        if (!empty($validated['card_type']) && !empty($validated['student_id'])) {
            $student = Student::with('stage')->find($validated['student_id']);
            if ($student) {
                $metadata = $this->buildCardMetadata($validated['card_type'], $student, $validated['item_id'] ?? null);
            }
        }

        // معالجة الملف المرفق
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attachmentName = $file->getClientOriginalName();
            $attachmentType = $file->getClientMimeType();
            $attachmentSize = $file->getSize();
            $attachmentPath = $file->store('chat_attachments', 'public');
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['body'] ?? null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_size' => $attachmentSize,
            'metadata' => $metadata,
        ]);

        // تحديث وقت المحادثة
        $conversation->update(['last_message_at' => now()]);

        // تحديث قراءة المرسل
        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => $this->formatMessage($message->load('sender'), $user->id),
        ]);
    }

    /**
     * إنشاء محادثة جديدة أو العثور على محادثة قائمة.
     */
    public function start(Request $request)
    {
        $user = $request->user();
        abort_if(!$user || $user->isStudent(), 403);

        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'initial_message' => ['nullable', 'string', 'max:3000'],
        ]);

        $recipient = User::findOrFail($validated['recipient_id']);
        $studentId = $validated['student_id'] ?? null;

        // التحقق من صلاحية التواصل بين الطرفين وفق قواعد العمل
        $this->validateChatPermission($user, $recipient);

        $type = $this->determineConversationType($user, $recipient);

        $conversation = $this->findExistingConversation($user->id, $recipient->id, $studentId);

        if (!$conversation) {
            $title = null;
            if ($studentId) {
                $st = Student::find($studentId);
                $title = 'محادثة حول: ' . ($st?->name ?? 'طالب');
            }

            $conversation = Conversation::create([
                'type' => $type,
                'title' => $title,
                'student_id' => $studentId,
                'creator_id' => $user->id,
                'last_message_at' => now(),
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'last_read_at' => now(),
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $recipient->id,
                'last_read_at' => null,
            ]);
        }

        if (!empty($validated['initial_message'])) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $validated['initial_message'],
            ]);
            $conversation->update(['last_message_at' => now()]);
        }

        return redirect()->route('chat.index', ['conversation_id' => $conversation->id]);
    }

    /**
     * تجهيز معاينة بطاقة بيانات طالب قبل الإرسال.
     */
    public function previewCard(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'card_type' => ['required', 'in:attendance,grade,homework,overview'],
            'item_id' => ['nullable', 'integer'],
        ]);

        $student = Student::with('stage')->findOrFail($validated['student_id']);

        // التحقق أن المستخدم مخول برؤية بيانات هذا الطالب
        if ($user->isParent()) {
            abort_unless($user->children()->where('students.id', $student->id)->exists(), 403);
        }

        $card = $this->buildCardMetadata($validated['card_type'], $student, $validated['item_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'card' => $card,
        ]);
    }

    /* -------------------------------------------------------------------------
     | الدوال المساعدة الداخلية
     | ------------------------------------------------------------------------- */

    private function isParticipant(Conversation $conversation, int $userId): bool
    {
        return $conversation->participants()->where('user_id', $userId)->exists();
    }

    private function findExistingConversation(int $user1Id, int $user2Id, ?int $studentId = null): ?Conversation
    {
        $query = Conversation::whereHas('participants', fn($q) => $q->where('user_id', $user1Id))
            ->whereHas('participants', fn($q) => $q->where('user_id', $user2Id));

        if ($studentId) {
            $withStudent = (clone $query)->where('student_id', $studentId)->first();
            if ($withStudent) {
                return $withStudent;
            }
        }

        return $query->first();
    }

    private function determineConversationType(User $u1, User $u2): string
    {
        $roles = [$u1->role, $u2->role];
        if (in_array('admin', $roles, true) && in_array('parent', $roles, true)) {
            return 'parent_admin';
        }
        if (in_array('staff', $roles, true) && in_array('parent', $roles, true)) {
            return 'parent_teacher';
        }
        if (in_array('admin', $roles, true) && in_array('staff', $roles, true)) {
            return 'staff_admin';
        }
        return 'general';
    }

    private function validateChatPermission(User $sender, User $recipient): void
    {
        $institute = Eyal::institute();
        abort_unless($institute['chat_enabled'] ?? true, 403, 'نظام المحادثات متوقف حالياً.');

        // منع تواصل الطلاب منعاً باتاً
        abort_if($sender->isStudent() || $recipient->isStudent(), 403, 'غير مصرح للطلاب باستخدام المحادثات.');

        // منع تواصل أولياء الأمور مع بعضهم
        if ($sender->isParent() && $recipient->isParent()) {
            abort(403, 'غير مصرح بالتواصل بين أولياء الأمور.');
        }

        // ولي أمر مع إدارة
        if (($sender->isParent() && $recipient->isAdmin()) || ($sender->isAdmin() && $recipient->isParent())) {
            abort_unless($institute['chat_parent_admin_enabled'] ?? true, 403, 'المحادثات بين أولياء الأمور والإدارة معطلة حالياً.');
            return;
        }

        // ولي أمر مع مدرس
        if (($sender->isParent() && $recipient->isStaff()) || ($sender->isStaff() && $recipient->isParent())) {
            abort_unless($institute['chat_parent_teacher_enabled'] ?? true, 403, 'المحادثات بين أولياء الأمور والمدرسين معطلة حالياً.');
            return;
        }

        // مدرس مع إدارة
        if (($sender->isStaff() && $recipient->isAdmin()) || ($sender->isAdmin() && $recipient->isStaff())) {
            abort_unless($institute['chat_staff_admin_enabled'] ?? true, 403, 'المحادثات الداخلية بين الكادر والإدارة معطلة حالياً.');
            return;
        }

        // الإدارة مسموح لها مع الجميع ما عدا الطلاب
        if ($sender->isAdmin()) {
            return;
        }

        abort(403, 'قناة التواصل هذه غير مصرح بها.');
    }

    private function getAvailableContacts(User $user)
    {
        $institute = Eyal::institute();

        if ($user->isAdmin()) {
            // الإدارة تتواصل مع كافة أولياء الأمور والمدرسين
            return User::whereIn('role', ['parent', 'staff'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn(User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role,
                    'role_label' => $u->roleLabel(),
                    'phone' => $u->phone,
                ]);
        }

        if ($user->isStaff()) {
            // المدرس يتواصل مع الإدارة + أولياء أمور طلابه
            $contacts = collect();

            if ($institute['chat_staff_admin_enabled'] ?? true) {
                $admin = User::where('role', 'admin')->first();
                if ($admin) {
                    $contacts->push([
                        'id' => $admin->id,
                        'name' => $admin->name . ' (إدارة المعهد)',
                        'role' => 'admin',
                        'role_label' => 'إدارة المعهد',
                        'phone' => $admin->phone,
                    ]);
                }
            }

            if ($institute['chat_parent_teacher_enabled'] ?? true) {
                // البحث عن أولياء أمور الطلاب المسجلين في دورات هذا المدرس
                $teacherCourseIds = Course::where('teacher_id', $user->id)->pluck('id');
                $studentIds = \Illuminate\Support\Facades\DB::table('course_student')
                    ->whereIn('course_id', $teacherCourseIds)
                    ->pluck('student_id');

                $parentUsers = User::where('role', 'parent')
                    ->whereHas('children', fn($q) => $q->whereIn('students.id', $studentIds))
                    ->get();

                foreach ($parentUsers as $pu) {
                    $contacts->push([
                        'id' => $pu->id,
                        'name' => $pu->name,
                        'role' => 'parent',
                        'role_label' => 'ولي أمر',
                        'phone' => $pu->phone,
                    ]);
                }
            }

            return $contacts;
        }

        if ($user->isParent()) {
            $contacts = collect();

            // الإدارة
            if ($institute['chat_parent_admin_enabled'] ?? true) {
                $admin = User::where('role', 'admin')->first();
                if ($admin) {
                    $contacts->push([
                        'id' => $admin->id,
                        'name' => $admin->name . ' (إدارة المعهد)',
                        'role' => 'admin',
                        'role_label' => 'إدارة المعهد',
                        'phone' => $admin->phone,
                    ]);
                }
            }

            // مدرسي الأبناء
            if ($institute['chat_parent_teacher_enabled'] ?? true) {
                $childIds = $user->children()->pluck('students.id');
                $courseIds = \Illuminate\Support\Facades\DB::table('course_student')
                    ->whereIn('student_id', $childIds)
                    ->pluck('course_id');

                $teacherIds = Course::whereIn('id', $courseIds)
                    ->whereNotNull('teacher_id')
                    ->pluck('teacher_id')
                    ->unique();

                $teachers = User::whereIn('id', $teacherIds)->where('is_active', true)->get();

                // إذا لم يكن هناك دورات مرتبطة بعد، نظهر المعلمين النشطين المتاحين
                if ($teachers->isEmpty()) {
                    $teachers = User::where('role', 'staff')->where('is_active', true)->get();
                }

                foreach ($teachers as $t) {
                    $contacts->push([
                        'id' => $t->id,
                        'name' => 'أ. ' . $t->name,
                        'role' => 'staff',
                        'role_label' => 'كادر تدريسي',
                        'phone' => $t->phone,
                    ]);
                }
            }

            return $contacts;
        }

        return collect();
    }

    private function buildCardMetadata(string $type, Student $student, ?int $itemId = null): array
    {
        $base = [
            'card_type' => $type,
            'student_id' => $student->id,
            'student_name' => $student->name,
            'student_no' => $student->student_no,
            'stage_name' => $student->stage?->name ?? 'غير محدد',
        ];

        if ($type === 'attendance') {
            $attendanceRecords = Eyal::attendanceFor($student->student_no);
            $rate = $student->attendance_rate;
            $presents = $attendanceRecords->where('status', 'present')->count();
            $absents = $attendanceRecords->where('status', 'absent')->count();
            $lates = $attendanceRecords->where('status', 'late')->count();

            return array_merge($base, [
                'title' => 'بطاقة سجل الحضور والغياب',
                'badge' => $rate . '% نسبة حضور',
                'badge_tone' => $rate >= 80 ? 'success' : 'danger',
                'items' => [
                    'حضور' => $presents . ' يوم',
                    'غياب' => $absents . ' يوم',
                    'تأخر' => $lates . ' يوم',
                    'آخر تسجيل' => $attendanceRecords->first()['date'] ?? 'لا يوجد',
                ],
            ]);
        }

        if ($type === 'grade') {
            $grades = Eyal::gradesFor($student->student_no);
            $targetGrade = $itemId ? $grades->firstWhere('id', $itemId) : $grades->first();

            if ($targetGrade) {
                return array_merge($base, [
                    'title' => 'بطاقة نتيجة امتحان: ' . $targetGrade['exam'],
                    'badge' => $targetGrade['score'] . ' / ' . $targetGrade['max'],
                    'badge_tone' => ($targetGrade['score'] / max(1, $targetGrade['max'])) >= 0.5 ? 'success' : 'danger',
                    'items' => [
                        'المادة / الدورة' => $targetGrade['course'],
                        'الدرجة' => $targetGrade['score'] . ' من ' . $targetGrade['max'],
                        'التاريخ' => Carbon::parse($targetGrade['date'])->format('Y/m/d'),
                    ],
                ]);
            }

            return array_merge($base, [
                'title' => 'بطاقة الدرجات والامتحانات',
                'badge' => $grades->count() . ' امتحان',
                'badge_tone' => 'primary',
                'items' => [
                    'عدد الامتحانات' => $grades->count(),
                    'آخر امتحان' => $grades->first()['exam'] ?? 'لا يوجد',
                ],
            ]);
        }

        if ($type === 'homework') {
            $homeworks = Eyal::homeworkFor(null, $student->student_no);
            $targetHw = $itemId ? $homeworks->firstWhere('id', $itemId) : $homeworks->first();

            if ($targetHw) {
                return array_merge($base, [
                    'title' => 'استعلام عن واجب: ' . $targetHw['title'],
                    'badge' => $targetHw['submission_label'] ?? 'لم يُسلَّم',
                    'badge_tone' => !empty($targetHw['submission_status']) ? 'success' : 'warning',
                    'items' => [
                        'الدورة' => $targetHw['course'],
                        'المدرس' => $targetHw['teacher'],
                        'حالة التسليم' => $targetHw['submission_label'] ?? 'بانتظار التسليم',
                        'ملاحظة المدرس' => $targetHw['feedback'] ?? 'لا توجد ملاحظات بعد',
                    ],
                ]);
            }

            return array_merge($base, [
                'title' => 'بطاقة الواجبات الدراسية',
                'badge' => $homeworks->count() . ' واجب',
                'badge_tone' => 'primary',
                'items' => [
                    'إجمالي الواجبات' => $homeworks->count(),
                ],
            ]);
        }

        // Overview
        return array_merge($base, [
            'title' => 'بطاقة تعريفية بالطالب',
            'badge' => 'طالب منتظم',
            'badge_tone' => 'primary',
            'items' => [
                'رقم الطالب' => $student->student_no,
                'المرحلة' => $student->stage?->name ?? '—',
                'نسبة الحضور' => $student->attendance_rate . '%',
            ],
        ]);
    }

    private function formatMessage(Message $msg, int $currentUserId): array
    {
        return [
            'id' => $msg->id,
            'sender_id' => $msg->sender_id,
            'sender_name' => $msg->sender?->name ?? 'مستخدم',
            'sender_role' => $msg->sender?->roleLabel() ?? '',
            'is_me' => $msg->sender_id === $currentUserId,
            'body' => $msg->body,
            'time' => $msg->created_at?->format('H:i') ?? '',
            'date' => $msg->created_at?->format('Y/m/d') ?? '',
            'has_card' => $msg->hasCard(),
            'card' => $msg->cardData(),
            'attachment' => $msg->attachment_path ? [
                'url' => Storage::disk('public')->url($msg->attachment_path),
                'name' => $msg->attachment_name,
                'type' => $msg->attachment_type,
                'is_image' => Str::startsWith($msg->attachment_type ?? '', 'image/'),
            ] : null,
        ];
    }
}


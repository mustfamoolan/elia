<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Course;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use App\Services\FcmService;
use App\Services\GeminiService;
use App\Support\Eyal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * عرض صفحة المحادثات الرئيسية مع التحميل المسبق الفوري (Zero-Latency Hydration).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        abort_if(! $user, 403, 'يرجى تسجيل الدخول للوصول إلى المحادثات.');

        Eyal::ensureCurriculumTables();
        Eyal::touchUserPresence((int) $user->id);
        $institute = Eyal::institute();
        $chatEnabled = (bool) ($institute['chat_enabled'] ?? true);

        // معالجة أي رسائل سابقة غير معالجة من أولياء الأمور أو الطلاب تلقائياً بالذكاء الاصطناعي (مرة واحدة فقط)
        $this->processUncheckedRecentMessages();

        $conversations = $this->loadUserConversations($user, $institute);

        // جهات الاتصال المتاحة لبدء محادثة جديدة حسب الصلاحيات (مع الكاش)
        $contacts = collect(Eyal::remember('chat_contacts.'.$user->id, 600, fn () => $this->getAvailableContacts($user)->all()));

        // قائمة الأبناء المتاحين لولي الأمر (أو طلاب المدرس/المعهد المشتركين فعلياً) لمشاركة بطاقاتهم
        $myChildren = collect(Eyal::remember('chat_children.'.$user->id, 600, function () use ($user): array {
            if ($user->isParent()) {
                return Student::with('stage:id,name')
                    ->whereHas('parents', fn ($q) => $q->where('users.id', $user->id))
                    ->orderBy('id')
                    ->get(['id', 'student_no', 'name', 'stage_id'])
                    ->map(fn (Student $s) => [
                        'id' => (string) $s->id,
                        'student_no' => $s->student_no,
                        'name' => $s->name,
                        'stage_name' => $s->stage?->name ?? '—',
                    ])
                    ->values()
                    ->all();
            }

            if ($user->isStudent()) {
                return Student::with('stage:id,name')
                    ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('student_no', $user->username))
                    ->get(['id', 'student_no', 'name', 'stage_id'])
                    ->map(fn (Student $s) => [
                        'id' => (string) $s->id,
                        'student_no' => $s->student_no,
                        'name' => $s->name,
                        'stage_name' => $s->stage?->name ?? '—',
                    ])
                    ->values()
                    ->all();
            }

            if ($user->isStaff()) {
                $teacherCourseIds = Course::query()
                    ->where('teacher_id', $user->id)
                    ->orWhereHas('subjects', fn ($q) => $q->where('course_subject.teacher_id', $user->id))
                    ->pluck('id');

                return Student::with('stage:id,name')
                    ->whereHas('courses', fn ($q) => $q->whereIn('courses.id', $teacherCourseIds))
                    ->orderBy('name')
                    ->get(['id', 'student_no', 'name', 'stage_id'])
                    ->map(fn (Student $s) => [
                        'id' => (string) $s->id,
                        'student_no' => $s->student_no,
                        'name' => $s->name,
                        'stage_name' => $s->stage?->name ?? '—',
                    ])
                    ->values()
                    ->all();
            }

            return Student::with('stage:id,name')
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'student_no', 'name', 'stage_id'])
                ->map(fn (Student $s) => [
                    'id' => (string) $s->id,
                    'student_no' => $s->student_no,
                    'name' => $s->name,
                    'stage_name' => $s->stage?->name ?? '—',
                ])
                ->values()
                ->all();
        }));

        // المحادثة المحددة إن وجدت
        $explicitConvId = $request->query('conversation_id');
        $showListOnMobile = $request->boolean('list') || ! $explicitConvId;
        $activeConversationId = (int) $explicitConvId;
        $targetUserId = (int) $request->query('user_id');
        $targetStudentId = (int) $request->query('student_id');

        if (! $activeConversationId && $targetUserId) {
            $existing = $this->findExistingConversation($user->id, $targetUserId, $targetStudentId ?: null);
            if ($existing && $conversations->contains('id', $existing->id)) {
                $activeConversationId = $existing->id;
                $showListOnMobile = false;
            }
        }

        if ($activeConversationId && ! $conversations->contains('id', $activeConversationId)) {
            $activeConversationId = 0;
        }

        if (! $activeConversationId && $conversations->isNotEmpty()) {
            $activeConversationId = (int) $conversations->first()['id'];
        }

        $activeConv = $activeConversationId ? $conversations->firstWhere('id', $activeConversationId) : null;

        // التحميل المسبق لرسائل المحادثة النشطة لتظهر في اللحظة صفر (0ms) بدون انتظار
        $initialMessages = [];
        $initialFingerprint = ['last_id' => 0, 'del_v' => 1];

        if ($activeConversationId && $activeConv) {
            $convModel = Conversation::find($activeConversationId);
            if ($convModel) {
                $initialMessages = $this->fetchRecentMessages($convModel, $user, 60);
                $initialFingerprint = $this->getConversationFingerprint($convModel);

                ConversationParticipant::where('conversation_id', $convModel->id)
                    ->where('user_id', $user->id)
                    ->update(['last_read_at' => now()]);
            }
        }

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
            'activeConv' => $activeConv,
            'initialMessages' => $initialMessages,
            'initialFingerprint' => $initialFingerprint,
            'showListOnMobile' => $showListOnMobile,
            'chatEnabled' => $chatEnabled,
            'layout' => $layout,
            'institute' => $institute,
        ]);
    }

    /**
     * استرجاع رسائل محادثة معينة أو التبديل الفوري بين المحادثات مع كاش البصمة الذكي (Smart Fingerprint Cache).
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isParticipant($conversation, $user->id), 403);
        Eyal::touchUserPresence((int) $user->id);

        $institute = Eyal::institute();
        if ($user->isParent() && $conversation->type === 'parent_teacher' && ! ($institute['chat_parent_teacher_enabled'] ?? true)) {
            abort(403, 'تم إيقاف محادثات المدرسين من قبل الإدارة.');
        }
        if ($conversation->type === 'parents_group') {
            abort_if(! $user->isAdmin() && ! ($institute['chat_parents_group_enabled'] ?? true), 403, 'الكروب العام غير متاح حالياً.');
        }
        if ($conversation->type === 'staff_group') {
            abort_if(($user->isParent() || $user->isStudent()) || (! $user->isAdmin() && ! ($institute['chat_staff_group_enabled'] ?? true)), 403, 'كروب الكادر التدريسي غير متاح.');
        }

        $afterId = (int) $request->query('after', 0);
        $clientDelV = (int) $request->query('del_v', 0);
        $includeSidebar = $request->boolean('sidebar', false);

        if (in_array($conversation->type, ['parents_group', 'staff_group'], true)) {
            $otherPresence = ['is_online' => true, 'label' => 'مجموعة مشتركة', 'last_seen_ts' => null];
        } else {
            $otherUser = $conversation->users()
                ->where('users.id', '!=', $user->id)
                ->first(['users.id', 'users.last_login_at']);
            $otherPresence = Eyal::getUserPresence($otherUser);
        }

        $fp = $this->getConversationFingerprint($conversation);

        // المسار السريع من الكاش (0 استعلامات رسائل إذا لم يتغير شيء في المحادثة!)
        if ($afterId > 0 && $afterId >= $fp['last_id'] && $clientDelV === $fp['del_v']) {
            $payload = [
                'status' => 'success',
                'unchanged' => true,
                'messages' => [],
                'del_v' => $fp['del_v'],
                'last_id' => $fp['last_id'],
                'is_closed' => (bool) $conversation->is_closed,
                'other_presence' => $otherPresence,
            ];

            if ($includeSidebar) {
                $payload['conversations'] = $this->loadUserConversations($user, $institute)->values()->all();
            }

            return response()->json($payload);
        }

        if ($afterId === 0) {
            $messages = $this->fetchRecentMessages($conversation, $user, 60);
        } else {
            $messages = $conversation->messages()
                ->with('sender:id,name,role')
                ->where('id', '>', $afterId)
                ->orderBy('id', 'asc')
                ->get()
                ->map(fn (Message $msg) => $this->formatMessage($msg, $user))
                ->values()
                ->all();
        }

        $existingIds = null;
        if ($clientDelV !== $fp['del_v']) {
            $existingIds = $conversation->messages()->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }

        if ($afterId === 0 || ! empty($messages)) {
            ConversationParticipant::where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->update(['last_read_at' => now()]);
        }

        $payload = [
            'status' => 'success',
            'unchanged' => false,
            'messages' => $messages,
            'existing_ids' => $existingIds,
            'del_v' => $fp['del_v'],
            'last_id' => $fp['last_id'],
            'is_closed' => (bool) $conversation->is_closed,
            'other_presence' => $otherPresence,
        ];

        if ($includeSidebar || ! empty($messages) || $afterId === 0) {
            $payload['conversations'] = $this->loadUserConversations($user, $institute)->values()->all();
        }

        return response()->json($payload);
    }

    /**
     * إرسال رسالة جديدة (نص أو ملف أو بطاقة بيانات طالب).
     */
    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->isParticipant($conversation, $user->id), 403);
        abort_if($conversation->is_closed, 422, 'تم إغلاق هذه المحادثة.');

        $institute = Eyal::institute();
        if ($conversation->type === 'parents_group') {
            abort_if(! $user->isAdmin() && ! ($institute['chat_parents_group_enabled'] ?? true), 403, 'الكروب العام مغلق أو غير متاح.');
        }
        if ($conversation->type === 'staff_group') {
            abort_if(($user->isParent() || $user->isStudent()) || (! $user->isAdmin() && ! ($institute['chat_staff_group_enabled'] ?? true)), 403, 'كروب الكادر التدريسي مغلق أو غير متاح.');
        }
        if (! $user->isAdmin()) {
            abort_unless($institute['chat_enabled'] ?? true, 403, 'نظام المحادثات متوقف حالياً.');
            if ($conversation->type === 'parent_teacher') {
                abort_unless($institute['chat_parent_teacher_enabled'] ?? true, 403, 'المحادثات مع المدرسين متوقفة حالياً من قبل الإدارة.');
            }
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'file' => ['nullable', 'file', 'max:10240'],
            'card_type' => ['nullable', 'string', 'in:attendance,grade,homework,overview'],
            'student_id' => ['nullable', 'exists:students,id'],
            'item_id' => ['nullable', 'integer'],
        ]);

        if (empty($validated['body']) && ! $request->hasFile('file') && empty($validated['card_type'])) {
            return response()->json(['message' => 'الرسالة لا يمكن أن تكون فارغة.'], 422);
        }

        $metadata = null;
        if (! empty($validated['card_type']) && ! empty($validated['student_id'])) {
            $student = Student::with('stage')->find($validated['student_id']);
            if ($student) {
                $metadata = $this->buildCardMetadata($validated['card_type'], $student, $validated['item_id'] ?? null);
            }
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attachmentName = $file->getClientOriginalName();
            $attachmentType = $file->getMimeType() ?: $file->getClientMimeType();
            $attachmentSize = $file->getSize();
            $attachmentPath = $file->store('chat_attachments', 'local');
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

        $conversation->update(['last_message_at' => now()]);

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        $fp = $this->bumpConversationFingerprint($conversation, (int) $message->id, false);

        // إرسال إشعار Push فوري للطرف الآخر في المحادثة
        $recipientIds = ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id')
            ->all();
        if (! empty($recipientIds)) {
            $previewText = trim((string) ($message->body ?? ''));
            if ($previewText === '' && $message->attachment_name) {
                $previewText = '📎 مرفق: '.$message->attachment_name;
            } elseif ($previewText === '' && $message->metadata) {
                $previewText = '📋 بطاقة متابعة طالب';
            }
            FcmService::sendToUsers(
                $recipientIds,
                'رسالة جديدة من '.$user->name,
                mb_strimwidth($previewText, 0, 120, '...'),
                '/chat?c='.$conversation->id,
                ['type' => 'chat', 'conversation_id' => (string) $conversation->id, 'tag' => 'chat-'.$conversation->id]
            );
        }

        $aiReplyFormatted = null;
        // الذكاء الاصطناعي يعمل حصرياً على رسائل أولياء الأمور والطلاب (ولا يعمل أبداً على رسائل الإدارة أو الكادر)
        $shouldRunAi = ($user->isParent() || $user->isStudent()) && GeminiService::isEnabled();
        if ($shouldRunAi) {
            try {
                $aiReply = app(GeminiService::class)->processParentChatMessage($message, $conversation, $user, false);
                if ($aiReply) {
                    $fp = $this->bumpConversationFingerprint($conversation, (int) $aiReply->id, false);
                    $aiReplyFormatted = $this->formatMessage($aiReply->load('sender:id,name,role'), $user);
                }
            } catch (\Throwable) {
                // ignore AI errors so user message send never fails
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => $this->formatMessage($message->load('sender:id,name,role'), $user),
            'ai_message' => $aiReplyFormatted,
            'del_v' => $fp['del_v'],
            'last_id' => $fp['last_id'],
        ]);
    }

    /**
     * تحليل رسالة معينة بالذكاء الاصطناعي يدوياً (لتقسيم وتوزيع الواجبات أو توليد رد ذكي فوري).
     */
    public function processWithAi(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();
        $conversation = $message->conversation;

        abort_unless($user && $conversation && ($this->isParticipant($conversation, $user->id) || $user->isAdmin()), 403);

        if (GeminiService::apiKey() === '') {
            return response()->json([
                'message' => 'يرجى إدخال مفتاح Gemini API أولاً من إعدادات الإدارة لتفعيل المساعد الذكي.',
            ], 422);
        }

        $parentUser = $message->sender;
        if (! $parentUser || ! $parentUser->isParent()) {
            $parentUser = $conversation->users()->where('role', 'parent')->first() ?? $user;
        }

        $aiReply = app(GeminiService::class)->processParentChatMessage($message, $conversation, $parentUser, true);

        if (! $aiReply) {
            return response()->json([
                'message' => 'تعذر تحليل الرسالة حالياً أو لم يُعثر على واجب/استفسار واضح فيها.',
            ], 422);
        }

        $fp = $this->bumpConversationFingerprint($conversation, (int) $aiReply->id, false);

        return response()->json([
            'status' => 'success',
            'ai_message' => $this->formatMessage($aiReply->load('sender:id,name,role'), $user),
            'del_v' => $fp['del_v'],
            'last_id' => $fp['last_id'],
        ]);
    }

    /**
     * حذف رسالة من الطرفين (للمرسل أو لإدارة المعهد).
     */
    public function destroyMessage(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();
        $conversation = $message->conversation;

        abort_unless($conversation && ($this->isParticipant($conversation, $user->id) || $user->isAdmin()), 403);
        abort_unless((int) $message->sender_id === (int) $user->id || $user->isAdmin(), 403, 'لا يمكنك حذف هذه الرسالة.');

        $deletedId = (int) $message->id;

        if ($message->attachment_path) {
            foreach (['local', 'public'] as $disk) {
                if (Storage::disk($disk)->exists($message->attachment_path)) {
                    Storage::disk($disk)->delete($message->attachment_path);
                }
            }
        }

        $message->delete();

        $latestMsg = $conversation->messages()->latest('id')->first(['id', 'created_at']);
        $conversation->update(['last_message_at' => $latestMsg?->created_at]);

        $fp = $this->bumpConversationFingerprint($conversation, (int) ($latestMsg?->id ?? 0), true);

        return response()->json([
            'status' => 'success',
            'deleted_id' => $deletedId,
            'del_v' => $fp['del_v'],
            'last_id' => $fp['last_id'],
        ]);
    }

    /**
     * عرض أو تحميل مرفق رسالة بشكل آمن ومتوافق مع Shared Cloud.
     */
    public function attachment(Request $request, Message $message)
    {
        $user = $request->user();
        $conversation = $message->conversation;

        abort_unless($user && $conversation && ($this->isParticipant($conversation, $user->id) || $user->isAdmin()), 403);
        abort_unless(! empty($message->attachment_path), 404);

        $disk = Storage::disk('local')->exists($message->attachment_path)
            ? 'local'
            : (Storage::disk('public')->exists($message->attachment_path) ? 'public' : null);

        abort_unless($disk !== null, 404);

        $name = $message->attachment_name ?: basename($message->attachment_path);

        if ($request->boolean('inline')) {
            $headers = ['Cache-Control' => 'private, max-age=86400'];
            if ($message->attachment_type) {
                $headers['Content-Type'] = $message->attachment_type;
            }

            return Storage::disk($disk)->response($message->attachment_path, $name, $headers);
        }

        return Storage::disk($disk)->download($message->attachment_path, $name);
    }

    /**
     * إنشاء محادثة جديدة أو العثور على محادثة قائمة.
     */
    public function start(Request $request)
    {
        $user = $request->user();
        abort_if(! $user, 403);

        if (! $request->filled('recipient_id') && $request->filled('target_user_id')) {
            $request->merge(['recipient_id' => $request->input('target_user_id')]);
        }

        $validated = $request->validate([
            'recipient_id' => ['required', 'exists:users,id'],
            'student_id' => ['nullable', 'exists:students,id'],
            'initial_message' => ['nullable', 'string', 'max:3000'],
        ]);

        $recipient = User::findOrFail($validated['recipient_id']);
        $studentId = $validated['student_id'] ?? null;

        $this->validateChatPermission($user, $recipient);

        $type = $this->determineConversationType($user, $recipient);

        $conversation = $this->findExistingConversation($user->id, $recipient->id, $studentId);

        if (! $conversation) {
            $title = null;
            if ($studentId) {
                $st = Student::find($studentId);
                $title = 'محادثة حول: '.($st?->name ?? 'طالب');
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

        if (! empty($validated['initial_message'])) {
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $validated['initial_message'],
            ]);
            $conversation->update(['last_message_at' => now()]);
            $this->bumpConversationFingerprint($conversation, (int) $msg->id, false);
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

        if ($user->isParent()) {
            abort_unless($user->children()->where('students.id', $student->id)->exists(), 403);
        }

        $card = $this->buildCardMetadata($validated['card_type'], $student, $validated['item_id'] ?? null);

        return response()->json([
            'status' => 'success',
            'card' => $card,
        ]);
    }

    /**
     * فحص ومعالجة الرسائل السابقة من أولياء الأمور والطلاب التي لم تُفحص بالـ AI بعد (مرة واحدة فقط).
     */
    private function processUncheckedRecentMessages(): void
    {
        if (! GeminiService::isEnabled()) {
            return;
        }

        try {
            $recentMessages = Message::query()
                ->with(['sender', 'conversation'])
                ->whereHas('sender', fn ($q) => $q->whereIn('role', ['parent', 'student']))
                ->where('created_at', '>=', now()->subHours(48))
                ->orderBy('id')
                ->limit(15)
                ->get();

            foreach ($recentMessages as $msg) {
                $meta = is_array($msg->metadata) ? $msg->metadata : [];
                if (! empty($meta['ai_checked']) || ! empty($meta['card_type'])) {
                    continue;
                }

                if (! $msg->sender || ! $msg->conversation) {
                    continue;
                }

                $aiReply = app(GeminiService::class)->processParentChatMessage($msg, $msg->conversation, $msg->sender, false);
                if ($aiReply) {
                    $this->bumpConversationFingerprint($msg->conversation, (int) $aiReply->id, false);
                }
            }
        } catch (\Throwable) {
            // ignore background scan errors
        }
    }

    /* -------------------------------------------------------------------------
     | الدوال المساعدة الداخلية
     | ------------------------------------------------------------------------- */

    /**
     * جلب قائمة محادثات المستخدم مع مراعاة إعدادات ظهور المدرسين لأولياء الأمور.
     *
     * @param  array<string, mixed>  $institute
     * @return Collection<int, array<string, mixed>>
     */
    private function loadUserConversations(User $user, array $institute): Collection
    {
        $this->ensureGroupConversationsForUser($user, $institute);

        $parentTeacherEnabled = (bool) ($institute['chat_parent_teacher_enabled'] ?? true);
        $parentAdminEnabled = (bool) ($institute['chat_parent_admin_enabled'] ?? true);
        $staffAdminEnabled = (bool) ($institute['chat_staff_admin_enabled'] ?? true);
        $parentsGroupEnabled = (bool) ($institute['chat_parents_group_enabled'] ?? true);
        $staffGroupEnabled = (bool) ($institute['chat_staff_group_enabled'] ?? true);

        return $user->conversations()
            ->when($user->isParent() || $user->isStudent(), fn ($q) => $q->where('conversations.type', '!=', 'staff_group'))
            ->when(! $user->isAdmin() && ! $parentsGroupEnabled, fn ($q) => $q->where('conversations.type', '!=', 'parents_group'))
            ->when($user->isStaff() && ! $staffGroupEnabled, fn ($q) => $q->where('conversations.type', '!=', 'staff_group'))
            ->when(($user->isParent() || $user->isStudent()) && ! $parentTeacherEnabled, fn ($q) => $q->where('conversations.type', '!=', 'parent_teacher'))
            ->when(($user->isParent() || $user->isStudent()) && ! $parentAdminEnabled, fn ($q) => $q->where('conversations.type', '!=', 'parent_admin'))
            ->when($user->isStaff() && ! $parentTeacherEnabled, fn ($q) => $q->where('conversations.type', '!=', 'parent_teacher'))
            ->when($user->isStaff() && ! $staffAdminEnabled, fn ($q) => $q->where('conversations.type', '!=', 'staff_admin'))
            ->with(['users:id,name,role,last_login_at', 'student:id,name,stage_id', 'student.stage:id,name', 'latestMessage.sender:id,name,role', 'participants'])
            ->withCount([
                'messages as unread_messages_count' => function ($q) use ($user) {
                    $q->where('sender_id', '!=', $user->id)
                        ->whereRaw(
                            'messages.created_at > COALESCE((SELECT cp.last_read_at FROM conversation_participants cp WHERE cp.conversation_id = messages.conversation_id AND cp.user_id = ? LIMIT 1), ?)',
                            [$user->id, '1970-01-01 00:00:00']
                        );
                },
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Conversation $conv) use ($user) {
                $isGroup = in_array($conv->type, ['parents_group', 'staff_group'], true);
                if ($isGroup) {
                    $title = $conv->titleFor($user);

                    return [
                        'id' => (int) $conv->id,
                        'type' => $conv->type,
                        'title' => $title,
                        'other_user_id' => null,
                        'other_user_name' => $title,
                        'other_user_role' => $conv->type === 'parents_group' ? 'كروب أولياء الأمور' : 'كروب الكادر',
                        'is_online' => true,
                        'presence_label' => 'مجموعة مشتركة',
                        'student_name' => null,
                        'student_id' => null,
                        'unread_count' => (int) ($conv->unread_messages_count ?? 0),
                        'last_message' => $conv->latestMessage?->body ?: ($conv->latestMessage?->hasCard() ? '📎 مشاركة بطاقة بيانات' : ($conv->latestMessage?->attachment_name ? '📎 ملف مرفق' : '')),
                        'last_message_time' => $conv->latestMessage?->created_at?->diffForHumans() ?? '',
                        'is_closed' => (bool) $conv->is_closed,
                    ];
                }

                $other = $conv->otherUser($user->id);
                $presence = Eyal::getUserPresence($other);

                return [
                    'id' => (int) $conv->id,
                    'type' => $conv->type,
                    'title' => $conv->titleFor($user),
                    'other_user_id' => $other?->id,
                    'other_user_name' => $other?->name ?? 'مستخدم',
                    'other_user_role' => $other?->roleLabel() ?? '',
                    'is_online' => (bool) $presence['is_online'],
                    'presence_label' => (string) $presence['label'],
                    'student_name' => $conv->student?->name,
                    'student_id' => $conv->student_id,
                    'unread_count' => (int) ($conv->unread_messages_count ?? 0),
                    'last_message' => $conv->latestMessage?->body ?: ($conv->latestMessage?->hasCard() ? '📎 مشاركة بطاقة بيانات' : ($conv->latestMessage?->attachment_name ? '📎 ملف مرفق' : '')),
                    'last_message_time' => $conv->latestMessage?->created_at?->diffForHumans() ?? '',
                    'is_closed' => (bool) $conv->is_closed,
                ];
            });
    }

    /**
     * ضمان انضمام المستخدم إلى الكروب الخاص بفئته فقط (عزل تام بين كروب الأولياء وكروب الكادر).
     *
     * @param  array<string, mixed>  $institute
     */
    private function ensureGroupConversationsForUser(User $user, array $institute): void
    {
        $parentsGroupEnabled = (bool) ($institute['chat_parents_group_enabled'] ?? true);
        $staffGroupEnabled = (bool) ($institute['chat_staff_group_enabled'] ?? true);

        // الكروب العام يشمل جميع المستخدمين (أولياء الأمور + الطلاب + الكادر + الإدارة)
        if ($parentsGroupEnabled || $user->isAdmin()) {
            $pg = Conversation::firstOrCreate(
                ['type' => 'parents_group'],
                ['title' => 'كروب أولياء الأمور والطلاب والكادر', 'creator_id' => $user->id]
            );
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $pg->id,
                'user_id' => $user->id,
            ]);
        }

        // كروب الكادر التدريسي خاص بالمدرسين والإدارة فقط
        if (($user->isStaff() || $user->isAdmin()) && ($staffGroupEnabled || $user->isAdmin())) {
            $sg = Conversation::firstOrCreate(
                ['type' => 'staff_group'],
                ['title' => 'كروب الكادر التدريسي والإدارة', 'creator_id' => $user->id]
            );
            ConversationParticipant::firstOrCreate([
                'conversation_id' => $sg->id,
                'user_id' => $user->id,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRecentMessages(Conversation $conversation, User $user, int $limit = 60): array
    {
        return $conversation->messages()
            ->with('sender:id,name,role')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (Message $msg) => $this->formatMessage($msg, $user))
            ->all();
    }

    /**
     * @return array{last_id: int, del_v: int}
     */
    private function getConversationFingerprint(Conversation $conversation): array
    {
        $key = 'chat_conv_fp.'.$conversation->id;
        $cached = Cache::get($key);

        if (is_array($cached) && isset($cached['last_id'], $cached['del_v'])) {
            return [
                'last_id' => (int) $cached['last_id'],
                'del_v' => (int) $cached['del_v'],
            ];
        }

        $lastId = (int) ($conversation->messages()->max('id') ?? 0);
        $fp = ['last_id' => $lastId, 'del_v' => 1];
        Cache::put($key, $fp, 86400);

        return $fp;
    }

    /**
     * @return array{last_id: int, del_v: int}
     */
    private function bumpConversationFingerprint(Conversation $conversation, int $lastId, bool $isDelete): array
    {
        $current = $this->getConversationFingerprint($conversation);
        $next = [
            'last_id' => $isDelete ? $lastId : max($current['last_id'], $lastId),
            'del_v' => $isDelete ? ($current['del_v'] + 1) : $current['del_v'],
        ];
        Cache::put('chat_conv_fp.'.$conversation->id, $next, 86400);

        return $next;
    }

    private function isParticipant(Conversation $conversation, int $userId): bool
    {
        return $conversation->participants()->where('user_id', $userId)->exists();
    }

    private function findExistingConversation(int $user1Id, int $user2Id, ?int $studentId = null): ?Conversation
    {
        $query = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $user1Id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user2Id));

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

        if (($sender->isParent() || $sender->isStudent()) && ($recipient->isParent() || $recipient->isStudent())) {
            abort(403, 'غير مصرح بالتواصل الخاص بين أولياء الأمور أو الطلاب.');
        }

        if ((($sender->isParent() || $sender->isStudent()) && $recipient->isAdmin()) || ($sender->isAdmin() && ($recipient->isParent() || $recipient->isStudent()))) {
            abort_unless($institute['chat_parent_admin_enabled'] ?? true, 403, 'المحادثات مع الإدارة معطلة حالياً.');

            return;
        }

        if (($sender->isParent() && $recipient->isStaff()) || ($sender->isStaff() && $recipient->isParent())) {
            abort_unless($institute['chat_parent_teacher_enabled'] ?? true, 403, 'المحادثات بين أولياء الأمور والمدرسين معطلة حالياً.');

            return;
        }

        if (($sender->isStaff() && $recipient->isAdmin()) || ($sender->isAdmin() && $recipient->isStaff())) {
            abort_unless($institute['chat_staff_admin_enabled'] ?? true, 403, 'المحادثات الداخلية بين الكادر والإدارة معطلة حالياً.');

            return;
        }

        if ($sender->isAdmin()) {
            return;
        }

        abort(403, 'قناة التواصل هذه غير مصرح بها.');
    }

    private function getAvailableContacts(User $user): Collection
    {
        $institute = Eyal::institute();

        if ($user->isAdmin()) {
            return User::whereIn('role', ['parent', 'staff'])
                ->where('is_active', true)
                ->with('staff')
                ->orderBy('role')
                ->orderBy('name')
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name.($u->staff?->subject ? ' ('.$u->staff->subject.')' : ''),
                    'role' => $u->role,
                    'role_label' => $u->roleLabel(),
                    'phone' => null,
                ]);
        }

        if ($user->isStaff()) {
            $contacts = collect();

            if ($institute['chat_staff_admin_enabled'] ?? true) {
                $admins = User::where('role', 'admin')->where('is_active', true)->get();
                foreach ($admins as $admin) {
                    $contacts->push([
                        'id' => $admin->id,
                        'name' => $admin->name.' (إدارة المعهد)',
                        'role' => 'admin',
                        'role_label' => 'إدارة المعهد',
                        'phone' => null,
                    ]);
                }
            }

            if ($institute['chat_parent_teacher_enabled'] ?? true) {
                $teacherCourseIds = Course::query()
                    ->where('teacher_id', $user->id)
                    ->orWhereHas('subjects', fn ($q) => $q->where('course_subject.teacher_id', $user->id))
                    ->pluck('id');

                $studentIds = DB::table('course_student')
                    ->whereIn('course_id', $teacherCourseIds)
                    ->pluck('student_id');

                $parentUsers = User::where('role', 'parent')
                    ->where('is_active', true)
                    ->whereHas('children', fn ($q) => $q->whereIn('students.id', $studentIds))
                    ->orderBy('name')
                    ->get();

                foreach ($parentUsers as $pu) {
                    $contacts->push([
                        'id' => $pu->id,
                        'name' => $pu->name,
                        'role' => 'parent',
                        'role_label' => 'ولي أمر',
                        'phone' => null,
                    ]);
                }
            }

            return $contacts;
        }

        if ($user->isParent() || $user->isStudent()) {
            $contacts = collect();

            // 1. إدارة المعهد
            if ($institute['chat_parent_admin_enabled'] ?? true) {
                $admins = User::where('role', 'admin')->where('is_active', true)->get();
                foreach ($admins as $admin) {
                    $contacts->push([
                        'id' => $admin->id,
                        'name' => $admin->name.' (إدارة المعهد)',
                        'role' => 'admin',
                        'role_label' => 'إدارة المعهد',
                        'phone' => null,
                    ]);
                }
            }

            if ($user->isStudent()) {
                return $contacts;
            }

            // 2. مدرسو المواد المشترك بها الأبناء (فقط إذا فعلت الإدارة خيار إظهار المدرسين للأهل!)
            if ($institute['chat_parent_teacher_enabled'] ?? true) {
                $childIds = $user->children()->pluck('students.id');
                $courseIds = DB::table('course_student')
                    ->whereIn('student_id', $childIds)
                    ->pluck('course_id');

                $courseSubjectRows = DB::table('course_subject')
                    ->join('subjects', 'subjects.id', '=', 'course_subject.subject_id')
                    ->whereIn('course_subject.course_id', $courseIds)
                    ->whereNotNull('course_subject.teacher_id')
                    ->get(['course_subject.teacher_id', 'subjects.name as subject_name']);

                $subjectsByTeacher = $courseSubjectRows
                    ->groupBy('teacher_id')
                    ->map(fn ($rows) => $rows->pluck('subject_name')->unique()->implode('، '));

                $directCourseTeachers = Course::whereIn('id', $courseIds)
                    ->whereNotNull('teacher_id')
                    ->pluck('teacher_id');

                $teacherIds = $subjectsByTeacher->keys()
                    ->merge($directCourseTeachers)
                    ->filter()
                    ->unique()
                    ->values();

                $teachers = User::whereIn('id', $teacherIds)
                    ->where('role', 'staff')
                    ->where('is_active', true)
                    ->with('staff')
                    ->orderBy('name')
                    ->get();

                foreach ($teachers as $t) {
                    $subLabel = $subjectsByTeacher->get($t->id) ?: ($t->staff?->subject ?: 'مدرس المادة');
                    $contacts->push([
                        'id' => $t->id,
                        'name' => 'أ. '.$t->name.' — '.$subLabel,
                        'role' => 'staff',
                        'role_label' => 'مدرس '.$subLabel,
                        'phone' => null,
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
            $excused = $attendanceRecords->where('status', 'excused')->count();

            return array_merge($base, [
                'title' => 'بطاقة سجل الحضور والغياب',
                'badge' => $rate.'% نسبة حضور',
                'badge_tone' => $rate >= 80 ? 'success' : 'danger',
                'items' => [
                    'حضور' => $presents.' يوم',
                    'تأخر' => $lates.' يوم',
                    'مجاز' => $excused.' يوم',
                    'غياب' => $absents.' يوم',
                    'آخر تسجيل' => $attendanceRecords->first()['date'] ?? 'لا يوجد',
                ],
            ]);
        }

        if ($type === 'grade') {
            $grades = Eyal::gradesFor($student->student_no);
            $targetGrade = $itemId ? $grades->firstWhere('id', $itemId) : $grades->first();

            if ($targetGrade) {
                return array_merge($base, [
                    'title' => 'بطاقة نتيجة امتحان: '.$targetGrade['exam'],
                    'badge' => $targetGrade['score'].' / '.$targetGrade['max'],
                    'badge_tone' => ($targetGrade['score'] / max(1, $targetGrade['max'])) >= 0.5 ? 'success' : 'danger',
                    'items' => [
                        'المادة / الكورس' => $targetGrade['course'],
                        'الدرجة' => $targetGrade['score'].' من '.$targetGrade['max'],
                        'التاريخ' => Carbon::parse($targetGrade['date'])->format('Y/m/d'),
                    ],
                ]);
            }

            return array_merge($base, [
                'title' => 'بطاقة الدرجات والامتحانات',
                'badge' => $grades->count().' امتحان',
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
                    'title' => 'استعلام عن واجب: '.$targetHw['title'],
                    'badge' => $targetHw['submission_label'] ?? 'لم يُسلَّم',
                    'badge_tone' => ! empty($targetHw['submission_status']) ? 'success' : 'warning',
                    'items' => [
                        'الكورس' => $targetHw['course'],
                        'المادة' => $targetHw['subject_name'] ?: 'عام',
                        'المدرس' => $targetHw['teacher'],
                        'حالة التسليم' => $targetHw['submission_label'] ?? 'بانتظار التسليم',
                        'ملاحظة المدرس' => $targetHw['feedback'] ?? 'لا توجد ملاحظات بعد',
                    ],
                ]);
            }

            return array_merge($base, [
                'title' => 'بطاقة الواجبات الدراسية',
                'badge' => $homeworks->count().' واجب',
                'badge_tone' => 'primary',
                'items' => [
                    'إجمالي الواجبات' => $homeworks->count(),
                ],
            ]);
        }

        return array_merge($base, [
            'title' => 'بطاقة تعريفية بالطالب',
            'badge' => 'طالب منتظم',
            'badge_tone' => 'primary',
            'items' => [
                'رقم الطالب' => $student->student_no,
                'المرحلة' => $student->stage?->name ?? '—',
                'نسبة الحضور' => $student->attendance_rate.'%',
            ],
        ]);
    }

    private function formatMessage(Message $msg, User|int $currentUser): array
    {
        $currentUserId = $currentUser instanceof User ? (int) $currentUser->id : (int) $currentUser;
        $isAdmin = $currentUser instanceof User ? $currentUser->isAdmin() : false;
        $isMe = (int) $msg->sender_id === $currentUserId;

        $isImage = false;
        if ($msg->attachment_path) {
            if (Str::startsWith(strtolower((string) $msg->attachment_type), 'image/')) {
                $isImage = true;
            } else {
                $ext = strtolower(pathinfo((string) ($msg->attachment_name ?: $msg->attachment_path), PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
            }
        }

        return [
            'id' => (int) $msg->id,
            'sender_id' => (int) $msg->sender_id,
            'sender_name' => $msg->sender?->name ?? 'مستخدم',
            'sender_role' => $msg->sender?->roleLabel() ?? '',
            'is_me' => $isMe,
            'can_delete' => $isMe || $isAdmin,
            'body' => $msg->body,
            'time' => $msg->created_at?->format('H:i') ?? '',
            'date' => $msg->created_at?->format('Y/m/d') ?? '',
            'has_card' => $msg->hasCard(),
            'card' => $msg->cardData(),
            'attachment' => $msg->attachment_path ? [
                'url' => route('chat.attachment', ['message' => $msg->id, 'inline' => $isImage ? 1 : 0]),
                'download_url' => route('chat.attachment', ['message' => $msg->id]),
                'name' => $msg->attachment_name ?: 'ملف مرفق',
                'type' => $msg->attachment_type,
                'is_image' => $isImage,
            ] : null,
        ];
    }
}

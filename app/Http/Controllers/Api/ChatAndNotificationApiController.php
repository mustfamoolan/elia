<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ChatController;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\NotificationRecipient;
use App\Models\Student;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\Eyal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * API المحادثات والكروبات والإشعارات المشتركة (Chat & Notifications API)
 * المسار الأساسي: /api/v1/chat/... و /api/v1/notifications/...
 * يخدم جميع الفئات (ولي الأمر، الطالب، الكادر التدريسي، والإدارة) مع نفس قواعد العزل والذكاء الاصطناعي.
 * ============================================================================
 */
class ChatAndNotificationApiController extends Controller
{
    /**
     * جلب قائمة المحادثات والكروبات المتاحة للمستخدم + جهات الاتصال + الأبناء
     * GET /api/v1/chat/conversations
     */
    public function conversations(Request $request): JsonResponse
    {
        $user = $request->user();
        Eyal::ensureCurriculumTables();
        Eyal::touchUserPresence((int) $user->id);

        if (Cache::add('eyal.gemini.bg_scan_lock', 1, 45)) {
            dispatch(function (): void {
                try {
                    app(GeminiService::class)->processUncheckedRecentMessages(25);
                } catch (\Throwable) {
                    // ignore background scan errors
                }
            })->afterResponse();
        }

        // نستفيد من ChatController لضمان تطابق 100% في الكروبات وجهات الاتصال وإخفاء أرقام الكادر
        $webChat = app(ChatController::class);
        $reflection = new \ReflectionClass($webChat);

        $institute = Eyal::institute();

        $loadConvMethod = $reflection->getMethod('loadUserConversations');
        $loadConvMethod->setAccessible(true);
        $conversations = $loadConvMethod->invoke($webChat, $user, $institute);

        $contactsMethod = $reflection->getMethod('getAvailableContacts');
        $contactsMethod->setAccessible(true);
        $contacts = $contactsMethod->invoke($webChat, $user);

        return response()->json([
            'status' => 'success',
            'chat_enabled' => (bool) ($institute['chat_enabled'] ?? true),
            'unread_total' => $user->totalUnreadChatsCount(),
            'conversations' => $conversations->values()->all(),
            'contacts' => $contacts->values()->all(),
        ]);
    }

    /**
     * جلب رسائل محادثة أو كروب محدد (مع دعم المزامنة اللحظية ?after=ID&del_v=V)
     * GET /api/v1/chat/conversations/{conversation}/messages
     */
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        return app(ChatController::class)->poll($request, $conversation);
    }

    /**
     * إرسال رسالة نصية أو صورة/ملف أو بطاقة طالب في المحادثة أو الكروب
     * (يفعّل Gemini AI & Vision تلقائياً إذا كان المرسل ولي أمر أو طالب!)
     * POST /api/v1/chat/conversations/{conversation}/messages
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return app(ChatController::class)->send($request, $conversation);
    }

    /**
     * بدء محادثة جديدة أو فتح محادثة قائمة مع جهة اتصال
     * POST /api/v1/chat/conversations/start
     */
    public function startConversation(Request $request): JsonResponse
    {
        $user = $request->user();

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

        $webChat = app(ChatController::class);
        $reflection = new \ReflectionClass($webChat);

        $validatePerm = $reflection->getMethod('validateChatPermission');
        $validatePerm->setAccessible(true);
        $validatePerm->invoke($webChat, $user, $recipient);

        $detType = $reflection->getMethod('determineConversationType');
        $detType->setAccessible(true);
        $type = $detType->invoke($webChat, $user, $recipient);

        $findExisting = $reflection->getMethod('findExistingConversation');
        $findExisting->setAccessible(true);
        /** @var Conversation|null $conversation */
        $conversation = $findExisting->invoke($webChat, $user->id, $recipient->id, $studentId);

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

            if (($user->isParent() || $user->isStudent()) && GeminiService::isEnabled()) {
                try {
                    app(GeminiService::class)->processParentChatMessage($msg, $conversation, $user, false);
                } catch (\Throwable) {
                    // ignore AI errors
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'conversation_id' => (int) $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->titleFor($user),
        ], 201);
    }

    /**
     * حذف رسالة من المحادثة
     * DELETE /api/v1/chat/messages/{message}
     */
    public function destroyMessage(Request $request, Message $message): JsonResponse
    {
        return app(ChatController::class)->destroyMessage($request, $message);
    }

    /**
     * معاينة بطاقة طالب قبل إرسالها في المحادثة
     * POST /api/v1/chat/preview-card
     */
    public function previewCard(Request $request): JsonResponse
    {
        return app(ChatController::class)->previewCard($request);
    }

    /**
     * جلب قائمة الإشعارات الخاصة بالمستخدم الحالي
     * GET /api/v1/notifications
     */
    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $userArr = $user->toEyalArray();
        $notifications = Eyal::notificationsFor($userArr);

        return response()->json([
            'status' => 'success',
            'unread_count' => Eyal::unreadNotificationsCount($userArr),
            'notifications' => $notifications->values()->all(),
        ]);
    }

    /**
     * نبض مزامنة خفيف وفوري للإشعارات والرسائل الجديدة بدون تحميل ثقيل
     * GET /api/v1/sync/pulse
     */
    public function pulse(Request $request): JsonResponse
    {
        $user = $request->user();
        $userArr = $user->toEyalArray();
        Eyal::touchUserPresence((int) $user->id);

        $institute = Eyal::institute();
        $notifications = Eyal::notificationsFor($userArr);
        $unreadNotifications = Eyal::unreadNotificationsCount($userArr);
        $latestNotification = $notifications->first();

        $unreadChats = $user->totalUnreadChatsCount();
        $latestChatRow = DB::table('conversation_participants as cp')
            ->join('conversations as c', 'c.id', '=', 'cp.conversation_id')
            ->join('messages as m', 'm.conversation_id', '=', 'c.id')
            ->join('users as sender', 'sender.id', '=', 'm.sender_id')
            ->where('cp.user_id', $user->id)
            ->orderByDesc('m.id')
            ->select([
                'm.id as message_id',
                'm.conversation_id',
                'm.sender_id',
                'sender.name as sender_name',
                'm.body',
                'm.attachment_name',
                'm.created_at',
                'c.title as conversation_title',
                'c.type as conversation_type',
            ])
            ->first();

        return response()->json([
            'status' => 'success',
            'chat_enabled' => (bool) ($institute['chat_enabled'] ?? true),
            'unread_notifications' => (int) $unreadNotifications,
            'unread_chats' => (int) $unreadChats,
            'latest_notification' => $latestNotification ? [
                'id' => (string) ($latestNotification['id'] ?? ''),
                'title' => (string) ($latestNotification['title'] ?? ''),
                'body' => (string) ($latestNotification['body'] ?? ''),
                'date' => (string) ($latestNotification['date'] ?? ''),
                'read' => (bool) ($latestNotification['read'] ?? false),
                'color' => (string) ($latestNotification['color'] ?? 'blue'),
            ] : null,
            'latest_chat' => $latestChatRow ? [
                'message_id' => (int) $latestChatRow->message_id,
                'conversation_id' => (int) $latestChatRow->conversation_id,
                'sender_id' => (int) $latestChatRow->sender_id,
                'sender_name' => (string) $latestChatRow->sender_name,
                'is_mine' => (int) $latestChatRow->sender_id === (int) $user->id,
                'preview' => trim((string) $latestChatRow->body) !== ''
                    ? (string) $latestChatRow->body
                    : (! empty($latestChatRow->attachment_name) ? '📎 مرفق جديد' : 'رسالة جديدة'),
                'conversation_title' => (string) ($latestChatRow->conversation_title ?? ''),
            ] : null,
            'server_ts' => now()->timestamp,
        ]);
    }

    /**
     * تعليم إشعار واحد كمقروء (يشمل إشعارات ولي الأمر والأبناء المرتبطين)
     * POST /api/v1/notifications/{notificationId}/read
     */
    public function markNotificationRead(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();
        $userArr = $user->toEyalArray();
        $userIds = Eyal::notificationUserIds($userArr);

        NotificationRecipient::query()
            ->whereIn('user_id', $userIds)
            ->where('notification_id', (int) $notificationId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'unread_count' => Eyal::unreadNotificationsCount($userArr),
            'message' => 'تم تعليم الإشعار كمقروء.',
        ]);
    }

    /**
     * تعليم جميع إشعارات المستخدم كمقروءة (يشمل إشعارات ولي الأمر والأبناء المرتبطين)
     * POST /api/v1/notifications/read-all
     */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $userIds = Eyal::notificationUserIds($user->toEyalArray());

        NotificationRecipient::query()
            ->whereIn('user_id', $userIds)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Eyal::flushCache();

        return response()->json([
            'status' => 'success',
            'unread_count' => 0,
            'message' => 'تم تعليم جميع الإشعارات كمقروءة.',
        ]);
    }
}

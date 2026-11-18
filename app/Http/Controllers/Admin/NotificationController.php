<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationRecipient;
use App\Models\Student;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\FcmService;
use App\Support\Eyal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index()
    {
        return view('admin.notifications', [
            'notifications' => Eyal::notificationsFor(session('eyal_user')),
            'students' => Eyal::students(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['required', 'string', 'max:30'],
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'title.required' => 'يرجى إدخال عنوان الإشعار.',
            'body.required' => 'يرجى إدخال نص الإشعار.',
        ]);

        $recipientIds = [];

        DB::transaction(function () use ($validated, $request, &$recipientIds) {
            $audience = $validated['audience'];

            $attributes = [
                'title' => $validated['title'],
                'body' => $validated['body'],
                'created_by' => $request->user()->id,
            ];

            if ($audience === 'students' || $audience === 'parents') {
                $role = $audience === 'students' ? 'student' : 'parent';
                $attributes['audience_type'] = 'role';
                $attributes['audience_role'] = $role;
                $recipients = User::query()
                    ->where('role', $role)
                    ->where('is_active', true)
                    ->pluck('id');
            } elseif ($audience === 'all') {
                $attributes['audience_type'] = 'all';
                $recipients = User::query()->where('is_active', true)->pluck('id');
            } else {
                $student = Student::query()->with('parents:id')->where('student_no', $audience)->first();

                $attributes['audience_type'] = 'user';
                $attributes['user_id'] = $student?->user_id;
                $recipients = collect($student?->user_id ? [$student->user_id] : [])
                    ->merge($student?->parents?->pluck('id') ?? collect())
                    ->unique()
                    ->values();
            }

            $notification = SystemNotification::create($attributes);

            foreach ($recipients as $userId) {
                NotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'user_id' => $userId,
                ]);
            }

            $recipientIds = $recipients->all();
        });

        if (! empty($recipientIds)) {
            FcmService::sendToUsers($recipientIds, $validated['title'], $validated['body'], '/notifications', [
                'type' => 'admin_notification',
                'tag' => 'admin-notif',
            ]);
        }

        return back()->with('success', 'تم إرسال الإشعار.');
    }

    public function update(Request $request, SystemNotification $notification)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'audience' => ['required', 'string', 'max:30'],
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'title.required' => 'يرجى إدخال عنوان الإشعار.',
            'body.required' => 'يرجى إدخال نص الإشعار.',
        ]);

        DB::transaction(function () use ($validated, $notification) {
            $audience = $validated['audience'];
            $attributes = [
                'title' => $validated['title'],
                'body' => $validated['body'],
                'audience_role' => null,
                'user_id' => null,
            ];

            if ($audience === 'students' || $audience === 'parents') {
                $role = $audience === 'students' ? 'student' : 'parent';
                $attributes['audience_type'] = 'role';
                $attributes['audience_role'] = $role;
            } elseif ($audience === 'all') {
                $attributes['audience_type'] = 'all';
            } else {
                $student = Student::query()->where('student_no', $audience)->first();
                $attributes['audience_type'] = 'user';
                $attributes['user_id'] = $student?->user_id;
            }

            $notification->update($attributes);
        });

        return back()->with('success', 'تم تعديل الإشعار بنجاح.');
    }

    public function destroy(SystemNotification $notification)
    {
        $notification->delete();

        return back()->with('success', 'تم حذف الإشعار.');
    }
}

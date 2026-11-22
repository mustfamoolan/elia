<?php

use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\ChatAndNotificationApiController;
use App\Http\Controllers\Api\ParentPortalApiController;
use App\Http\Controllers\Api\PublicPortalApiController;
use App\Http\Controllers\Api\StudentPortalApiController;
use App\Http\Controllers\Api\TeacherPortalApiController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeworkPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 1. API المصادقة والدخول (Auth API)
    | المسار: /api/v1/auth/...
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthApiController::class, 'login'])->name('login');

        Route::middleware('api.auth')->group(function () {
            Route::get('/me', [AuthApiController::class, 'me'])->name('me');
            Route::post('/logout', [AuthApiController::class, 'logout'])->name('logout');
            Route::post('/push-token', [AuthApiController::class, 'updatePushToken'])->name('push_token');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | 2. API الواجهات العامة للمعهد (Public Portal API)
    | المسار: /api/v1/public/...
    |--------------------------------------------------------------------------
    */
    Route::prefix('public')->name('public.')->group(function () {
        Route::get('/bootstrap', [PublicPortalApiController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/stages', [PublicPortalApiController::class, 'stages'])->name('stages');
        Route::get('/courses', [PublicPortalApiController::class, 'courses'])->name('courses');
        Route::get('/offers', [PublicPortalApiController::class, 'offers'])->name('offers');
        Route::get('/schedule', [PublicPortalApiController::class, 'schedule'])->name('schedule');
        Route::get('/announcements', [PublicPortalApiController::class, 'announcements'])->name('announcements');
        Route::get('/staff', [PublicPortalApiController::class, 'staff'])->name('staff');
        Route::get('/results', [PublicPortalApiController::class, 'results'])->name('results');
        Route::post('/register-student', [PublicPortalApiController::class, 'registerStudent'])
            ->middleware('throttle:10,1')
            ->name('register_student');
    });

    /*
    |--------------------------------------------------------------------------
    | 3. API أولياء الأمور (Parent Portal API)
    | المسار: /api/v1/parent/...
    | مخصص لحسابات أولياء الأمور فقط (role:parent)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['api.auth', 'role:parent'])
        ->prefix('parent')
        ->name('parent.')
        ->group(function () {
            Route::get('/dashboard', [ParentPortalApiController::class, 'dashboard'])->name('dashboard');
            Route::get('/children', [ParentPortalApiController::class, 'children'])->name('children');
            Route::get('/homework', [ParentPortalApiController::class, 'homework'])->name('homework');
            Route::post('/homework/upload', [ParentPortalApiController::class, 'uploadHomework'])->name('homework.upload');
            Route::post('/homework/{task}/submit', [ParentPortalApiController::class, 'submitHomework'])->name('homework.submit');
            Route::get('/grades', [ParentPortalApiController::class, 'grades'])->name('grades');
            Route::get('/attendance', [ParentPortalApiController::class, 'attendance'])->name('attendance');
            Route::get('/payments', [ParentPortalApiController::class, 'payments'])->name('payments');
            Route::post('/register-child', [ParentPortalApiController::class, 'registerChild'])->name('register_child');
        });

    /*
    |--------------------------------------------------------------------------
    | 4. API الطالب (Student Portal API)
    | المسار: /api/v1/student/...
    | مخصص لحسابات الطلاب (وأولياء الأمور عند استعراض ملف الطالب)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['api.auth', 'role:student,parent'])
        ->prefix('student')
        ->name('student.')
        ->group(function () {
            Route::get('/dashboard', [StudentPortalApiController::class, 'dashboard'])->name('dashboard');
            Route::get('/courses', [StudentPortalApiController::class, 'courses'])->name('courses');
            Route::get('/homework', [StudentPortalApiController::class, 'homework'])->name('homework');
            Route::post('/homework/upload', [StudentPortalApiController::class, 'uploadHomework'])->name('homework.upload');
            Route::post('/homework/{task}/submit', [StudentPortalApiController::class, 'submitHomework'])->name('homework.submit');
            Route::get('/grades', [StudentPortalApiController::class, 'grades'])->name('grades');
            Route::get('/attendance', [StudentPortalApiController::class, 'attendance'])->name('attendance');
            Route::get('/payments', [StudentPortalApiController::class, 'payments'])->name('payments');
            Route::get('/schedule', [StudentPortalApiController::class, 'schedule'])->name('schedule');
        });

    /*
    |--------------------------------------------------------------------------
    | 5. API الكادر التدريسي (Teacher Portal API)
    | المسار: /api/v1/teacher/...
    | مخصص لحسابات المدرسين والكادر التدريسي فقط (role:staff)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['api.auth', 'role:staff'])
        ->prefix('teacher')
        ->name('teacher.')
        ->group(function () {
            Route::get('/dashboard', [TeacherPortalApiController::class, 'dashboard'])->name('dashboard');
            Route::get('/courses', [TeacherPortalApiController::class, 'courses'])->name('courses');
            Route::get('/courses/{course}', [TeacherPortalApiController::class, 'courseShow'])->name('courses.show');
            Route::get('/students', [TeacherPortalApiController::class, 'students'])->name('students');

            Route::get('/attendance', [TeacherPortalApiController::class, 'attendance'])->name('attendance');
            Route::post('/attendance', [TeacherPortalApiController::class, 'attendanceStore'])->name('attendance.store');

            Route::get('/grades', [TeacherPortalApiController::class, 'grades'])->name('grades');
            Route::post('/grades', [TeacherPortalApiController::class, 'gradesStore'])->name('grades.store');
            Route::delete('/grades/{grade}', [TeacherPortalApiController::class, 'gradesDestroy'])->name('grades.destroy');

            Route::get('/homework', [TeacherPortalApiController::class, 'homework'])->name('homework');
            Route::post('/homework', [TeacherPortalApiController::class, 'homeworkStore'])->name('homework.store');
            Route::get('/homework/{task}', [TeacherPortalApiController::class, 'homeworkShow'])->name('homework.show');
            Route::put('/homework/{task}', [TeacherPortalApiController::class, 'homeworkUpdate'])->name('homework.update');
            Route::delete('/homework/{task}', [TeacherPortalApiController::class, 'homeworkDestroy'])->name('homework.destroy');
            Route::post('/homework/{task}/submissions/{submission}/review', [TeacherPortalApiController::class, 'reviewSubmission'])->name('homework.review');

            Route::get('/schedule', [TeacherPortalApiController::class, 'schedule'])->name('schedule');
            Route::get('/profile', [TeacherPortalApiController::class, 'profile'])->name('profile');
            Route::put('/profile', [TeacherPortalApiController::class, 'profileUpdate'])->name('profile.update');
        });

    /*
    |--------------------------------------------------------------------------
    | 6. API المحادثات والكروبات والإشعارات والملفات المشتركة (Chat & Notifications API)
    | المسار: /api/v1/chat/... و /api/v1/notifications/...
    |--------------------------------------------------------------------------
    */
    Route::middleware('api.auth')->group(function () {
        Route::prefix('chat')->name('chat.')->group(function () {
            Route::get('/conversations', [ChatAndNotificationApiController::class, 'conversations'])->name('conversations');
            Route::post('/conversations/start', [ChatAndNotificationApiController::class, 'startConversation'])->name('start');
            Route::get('/conversations/{conversation}/messages', [ChatAndNotificationApiController::class, 'messages'])->name('messages');
            Route::post('/conversations/{conversation}/messages', [ChatAndNotificationApiController::class, 'sendMessage'])->name('send');
            Route::delete('/messages/{message}', [ChatAndNotificationApiController::class, 'destroyMessage'])->name('messages.destroy');
            Route::post('/preview-card', [ChatAndNotificationApiController::class, 'previewCard'])->name('preview_card');
            Route::get('/attachments/{message}', [ChatController::class, 'attachment'])->name('attachment');
        });

        Route::get('/homework/files/{file}', [HomeworkPortalController::class, 'download'])->name('homework.file');

        Route::get('/notifications', [ChatAndNotificationApiController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [ChatAndNotificationApiController::class, 'markAllNotificationsRead'])->name('notifications.read_all');
    });
});

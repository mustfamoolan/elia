<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GradeController;
use App\Http\Controllers\Admin\HomeworkController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OfferController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StageController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeworkPortalController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TeacherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| واجهة الموقع العامة
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/stages', [PageController::class, 'stages'])->name('stages');
Route::get('/courses', [PageController::class, 'courses'])->name('courses');
Route::get('/offers', [PageController::class, 'offers'])->name('offers');
Route::get('/schedule', [PageController::class, 'schedule'])->name('schedule');
Route::get('/announcements', [PageController::class, 'announcements'])->name('announcements');
Route::get('/results', [PageController::class, 'results'])->name('results');
Route::get('/homework', [PageController::class, 'homework'])->name('homework');
Route::get('/parent', [PageController::class, 'parentPage'])->name('parent');
Route::get('/student', [PageController::class, 'studentPage'])->name('student');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/location', [PageController::class, 'location'])->name('location');
Route::get('/staff', [PageController::class, 'staff'])->name('staff');
Route::get('/notifications', [PageController::class, 'notifications'])->name('notifications');
Route::get('/register', [PageController::class, 'register'])->name('register');
Route::post('/register', [PageController::class, 'storeRegistration'])
    ->middleware('throttle:10,1')
    ->name('register.store');

/*
|--------------------------------------------------------------------------
| رفع الواجبات وتنزيل الملفات (الطلاب وأولياء الأمور)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::post('/homework/{task}/submit', [HomeworkPortalController::class, 'submit'])->name('homework.submit');
    Route::post('/parent/homework', [HomeworkPortalController::class, 'parentUpload'])->name('parent.homework.store');
    Route::get('/homework/files/{file}', [HomeworkPortalController::class, 'download'])->name('homework.download');
});

/*
|--------------------------------------------------------------------------
| الدخول والخروج
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| لوحة الإدارة
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [DashboardController::class, 'reports'])->name('reports');

    Route::get('/students', [StudentController::class, 'index'])->name('students');
    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    Route::get('/registrations', [RegistrationController::class, 'index'])->name('registrations');
    Route::patch('/registrations/{registration}/{status}', [RegistrationController::class, 'updateStatus'])
        ->whereIn('status', ['accepted', 'rejected'])
        ->name('registrations.status');
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy'])->name('registrations.destroy');

    Route::get('/stages', [StageController::class, 'index'])->name('stages');
    Route::post('/stages', [StageController::class, 'store'])->name('stages.store');
    Route::put('/stages/{stage}', [StageController::class, 'update'])->name('stages.update');
    Route::post('/stages/{stage}/toggle', [StageController::class, 'toggle'])->name('stages.toggle');
    Route::delete('/stages/{stage}', [StageController::class, 'destroy'])->name('stages.destroy');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    Route::post('/staff/toggle-visibility', [StaffController::class, 'toggleVisibility'])->name('staff.toggle');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');

    Route::get('/grades', [GradeController::class, 'index'])->name('grades');
    Route::post('/grades', [GradeController::class, 'store'])->name('grades.store');
    Route::put('/grades/{grade}', [GradeController::class, 'update'])->name('grades.update');
    Route::delete('/grades/{grade}', [GradeController::class, 'destroy'])->name('grades.destroy');

    Route::get('/homework', [HomeworkController::class, 'index'])->name('homework');
    Route::post('/homework', [HomeworkController::class, 'store'])->name('homework.store');
    Route::put('/homework/{homework}', [HomeworkController::class, 'update'])->name('homework.update');
    Route::delete('/homework/{homework}', [HomeworkController::class, 'destroy'])->name('homework.destroy');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::post('/announcements/{announcement}/toggle', [AnnouncementController::class, 'toggle'])->name('announcements.toggle');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::get('/offers', [OfferController::class, 'index'])->name('offers');
    Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
    Route::put('/offers/{offer}', [OfferController::class, 'update'])->name('offers.update');
    Route::post('/offers/{offer}/toggle', [OfferController::class, 'toggle'])->name('offers.toggle');
    Route::delete('/offers/{offer}', [OfferController::class, 'destroy'])->name('offers.destroy');

    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::delete('/schedule/{schedule}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings');
    Route::put('/settings/institute', [SettingController::class, 'updateInstitute'])->name('settings.institute');
    Route::put('/settings/theme', [SettingController::class, 'updateTheme'])->name('settings.theme');
    Route::post('/settings/toggle', [SettingController::class, 'toggle'])->name('settings.toggle');

    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
});

/*
|--------------------------------------------------------------------------
| بوابة الكادر التدريسي
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:staff'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/', [TeacherController::class, 'dashboard'])->name('dashboard');

    Route::get('/courses', [TeacherController::class, 'courses'])->name('courses');
    Route::get('/courses/{course}', [TeacherController::class, 'course'])->name('courses.show');

    Route::get('/students', [TeacherController::class, 'students'])->name('students');

    Route::get('/attendance', [TeacherController::class, 'attendance'])->name('attendance');
    Route::post('/attendance', [TeacherController::class, 'attendanceStore'])->name('attendance.store');

    Route::get('/grades', [TeacherController::class, 'grades'])->name('grades');
    Route::post('/grades', [TeacherController::class, 'gradesStore'])->name('grades.store');
    Route::delete('/grades/{grade}', [TeacherController::class, 'gradesDestroy'])->name('grades.destroy');

    Route::get('/homework', [TeacherController::class, 'homework'])->name('homework');
    Route::post('/homework', [TeacherController::class, 'homeworkStore'])->name('homework.store');
    Route::get('/homework/{task}', [TeacherController::class, 'homeworkShow'])->name('homework.show');
    Route::post('/homework/{task}/submissions/{submission}/review', [TeacherController::class, 'review'])->name('homework.review');

    Route::get('/schedule', [TeacherController::class, 'schedule'])->name('schedule');

    Route::get('/profile', [TeacherController::class, 'profile'])->name('profile');
    Route::put('/profile', [TeacherController::class, 'profileUpdate'])->name('profile.update');
});

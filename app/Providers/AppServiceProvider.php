<?php

namespace App\Providers;

use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Grade;
use App\Models\HomeworkSubmission;
use App\Models\HomeworkTask;
use App\Models\NotificationRecipient;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\RegistrationRequest;
use App\Models\ScheduleSlot;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentCharge;
use App\Models\SystemNotification;
use App\Models\User;
use App\Support\Eyal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $models = [
            Setting::class,
            Stage::class,
            Course::class,
            Offer::class,
            Announcement::class,
            ScheduleSlot::class,
            Staff::class,
            Student::class,
            StudentCharge::class,
            Payment::class,
            RegistrationRequest::class,
            AttendanceRecord::class,
            Assessment::class,
            Grade::class,
            HomeworkTask::class,
            HomeworkSubmission::class,
            SystemNotification::class,
            NotificationRecipient::class,
            User::class,
        ];

        foreach ($models as $modelClass) {
            /** @var class-string<Model> $modelClass */
            $modelClass::saved(static fn () => Eyal::flushCache());
            $modelClass::deleted(static fn () => Eyal::flushCache());
        }
    }
}

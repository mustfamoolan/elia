<?php

use App\Support\Eyal;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('eyal:clean-curriculum', function () {
    Eyal::ensureCurriculumTables();

    Schema::disableForeignKeyConstraints();

    foreach (['course_student', 'course_subject', 'subject_teacher', 'stage_teacher', 'courses'] as $table) {
        if (Schema::hasTable($table)) {
            DB::table($table)->truncate();
        }
    }

    if (Schema::hasTable('staff')) {
        DB::table('staff')->update([
            'subject' => null,
            'stage_label' => null,
        ]);
    }

    if (Schema::hasTable('registration_requests')) {
        DB::table('registration_requests')->update(['course_id' => null]);
    }

    Schema::enableForeignKeyConstraints();

    Eyal::flushCache();

    $this->info('تم تنظيف الكورسات القديمة والربط العشوائي بين الطلاب والمدرسين والكورسات بنجاح.');
})->purpose('تنظيف الكورسات القديمة والربط العشوائي لإعادة ضبط المناهج والكورسات بشكل نظيف');

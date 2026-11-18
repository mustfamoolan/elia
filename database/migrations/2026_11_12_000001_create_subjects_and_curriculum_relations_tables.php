<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(1);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->unique(['stage_id', 'name']);
            });
        }

        if (! Schema::hasTable('stage_teacher')) {
            Schema::create('stage_teacher', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['teacher_id', 'stage_id']);
            });
        }

        if (! Schema::hasTable('subject_teacher')) {
            Schema::create('subject_teacher', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['teacher_id', 'subject_id']);
            });
        }

        if (! Schema::hasTable('course_subject')) {
            Schema::create('course_subject', function (Blueprint $table) {
                $table->id();
                $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
                $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
                $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['course_id', 'subject_id']);
            });
        }

        foreach (['homework_tasks', 'assessments', 'attendance_records', 'schedule_slots'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'subject_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('subject_id')->nullable()->after('course_id')->constrained('subjects')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['homework_tasks', 'assessments', 'attendance_records', 'schedule_slots'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'subject_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('subject_id');
                });
            }
        }

        Schema::dropIfExists('course_subject');
        Schema::dropIfExists('subject_teacher');
        Schema::dropIfExists('stage_teacher');
        Schema::dropIfExists('subjects');
    }
};

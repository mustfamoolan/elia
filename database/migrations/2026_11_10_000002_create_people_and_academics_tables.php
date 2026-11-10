<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(1);
            $table->string('icon', 40)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->string('student_no', 30)->unique();
            $table->string('name');
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('enrollment_date')->nullable();
            $table->string('study_type', 40)->nullable();
            $table->string('shift', 20)->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('subject')->nullable();
            $table->string('stage_label')->nullable();
            $table->text('bio')->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('relation', 20)->default('guardian');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->unique(['parent_id', 'student_id']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 0)->default(0);
            $table->unsignedSmallInteger('seats')->default(0);
            $table->string('schedule_text')->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('course_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->decimal('fee', 12, 0)->default(0);
            $table->date('enrolled_at')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
            $table->unique(['course_id', 'student_id']);
        });

        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->string('hall')->nullable();
            $table->string('course_name')->nullable();
            $table->string('stage_name')->nullable();
            $table->string('teacher_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_slots');
        Schema::dropIfExists('course_student');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('parent_student');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('students');
        Schema::dropIfExists('stages');
    }
};

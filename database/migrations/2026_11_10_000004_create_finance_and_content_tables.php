<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 0)->default(0);
            $table->date('due_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_id')->nullable()->constrained('student_charges')->nullOnDelete();
            $table->decimal('amount', 12, 0)->default(0);
            $table->string('method', 20)->default('cash');
            $table->string('receipt_no', 40)->unique();
            $table->date('paid_at');
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('registration_requests', function (Blueprint $table) {
            $table->id();
            $table->string('student_name');
            $table->date('birth_date')->nullable();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('parent_name')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('parent_phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('category', 30)->default('عام');
            $table->string('audience', 20)->default('all');
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_published')->default(true)->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('stage_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('old_price', 12, 0)->default(0);
            $table->decimal('price', 12, 0)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('registration_requests');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('student_charges');
    }
};

<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Course;
use App\Models\Grade;
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
use App\Models\Subject;
use App\Models\SystemNotification;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات الواجهة المشتقة من قاعدة البيانات مع نظام كاش ذكي متعدد المستويات.
 */
class Eyal
{
    public const VERSION_CACHE_KEY = 'eyal.v1.cache_version';

    /**
     * كاش الذاكرة اللحظي ضمن دورة حياة الطلب الواحد (Request-level Memoization).
     *
     * @var array<string, mixed>
     */
    private static array $runtimeCache = [];

    /**
     * رقم إصدار الكاش الحالي.
     */
    private static ?string $cacheVersion = null;

    private static bool $curriculumTablesChecked = false;

    /**
     * مخزن الكاش السريع (ملفات القرص المحلي SSD بدلاً من استعلامات جدول cache في قاعدة البيانات).
     */
    private static function fastCache(): \Illuminate\Contracts\Cache\Repository
    {
        try {
            return Cache::store('file');
        } catch (\Throwable) {
            return Cache::store();
        }
    }

    /**
     * التأكد من وجود جداول المناهج والمواد على سيرفر الإنتاج بأمان تام وبدون أي حمل على الطلبات.
     */
    public static function ensureCurriculumTables(): void
    {
        if (self::$curriculumTablesChecked) {
            return;
        }
        self::$curriculumTablesChecked = true;

        try {
            $flagFile = storage_path('framework/cache/eyal_curriculum_schema_v1.flag');
            if (is_file($flagFile)) {
                return;
            }

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

            @file_put_contents($flagFile, '1');
        } catch (\Throwable) {
            // Ignore if DB connection is unavailable during CLI build
        }
    }

    private static bool $pushTablesChecked = false;

    /**
     * التأكد من وجود جدول رموز إشعارات الدفع (push_tokens) على سيرفر الإنتاج بدون أي حمل على الطلبات.
     */
    public static function ensurePushTables(): void
    {
        if (self::$pushTablesChecked) {
            return;
        }
        self::$pushTablesChecked = true;

        try {
            $flagFile = storage_path('framework/cache/eyal_push_schema_v1.flag');
            if (is_file($flagFile)) {
                return;
            }

            if (! Schema::hasTable('push_tokens')) {
                Schema::create('push_tokens', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->string('token', 512)->unique();
                    $table->string('platform', 30)->default('web')->index();
                    $table->string('device_label', 120)->nullable();
                    $table->timestamp('last_seen_at')->nullable();
                    $table->timestamps();
                    $table->index(['user_id', 'platform']);
                });
            }

            @file_put_contents($flagFile, '1');
        } catch (\Throwable) {
            // Ignore if DB is unavailable
        }
    }

    private static bool $apiTokensTableChecked = false;

    /**
     * التأكد من وجود جدول رموز مصادقة تطبيقات الموبايل (api_tokens) تلقائياً على الإنتاج.
     */
    public static function ensureApiTokensTable(): void
    {
        if (self::$apiTokensTableChecked) {
            return;
        }
        self::$apiTokensTableChecked = true;

        try {
            $flagFile = storage_path('framework/cache/eyal_api_tokens_v1.flag');
            if (is_file($flagFile)) {
                return;
            }

            if (! Schema::hasTable('api_tokens')) {
                Schema::create('api_tokens', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $table->string('name', 120)->default('mobile');
                    $table->string('token_hash', 64)->unique();
                    $table->timestamp('last_used_at')->nullable();
                    $table->timestamp('expires_at')->nullable();
                    $table->timestamps();
                    $table->index(['user_id', 'last_used_at']);
                });
            }

            @file_put_contents($flagFile, '1');
        } catch (\Throwable) {
            // Ignore if DB is unavailable
        }
    }

    /**
     * استرجاع قيمة من الكاش الذكي (ذاكرة الطلب أولاً ثم كاش الملفات السريع).
     */
    public static function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if (array_key_exists($key, self::$runtimeCache)) {
            return self::$runtimeCache[$key];
        }

        $versionedKey = 'eyal.c.'.self::cacheVersion().'.'.$key;

        try {
            $value = self::fastCache()->remember($versionedKey, $ttlSeconds, $callback);
        } catch (\Throwable) {
            $value = $callback();
        }

        self::$runtimeCache[$key] = $value;

        return $value;
    }

    /**
     * تفريغ الكاش الذكي فوراً عند أي تعديل في البيانات.
     */
    public static function flushCache(): void
    {
        self::$runtimeCache = [];
        self::$cacheVersion = (string) round(microtime(true) * 1000);

        try {
            self::fastCache()->forever(self::VERSION_CACHE_KEY, self::$cacheVersion);
        } catch (\Throwable) {
            //
        }
    }

    /**
     * تسجيل تواجد المستخدم الفوري (Online Presence & Last Seen) في الكاش السريع بدون ضغط على قاعدة البيانات.
     */
    public static function touchUserPresence(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        $key = 'eyal.presence.'.$userId;
        if (isset(self::$runtimeCache[$key])) {
            return;
        }
        self::$runtimeCache[$key] = true;

        try {
            $nowTs = now()->timestamp;
            $store = self::fastCache();
            $lastTs = (int) $store->get($key, 0);
            if (($nowTs - $lastTs) >= 20) {
                $store->put($key, $nowTs, 86400 * 7);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * جلب حالة الاتصال الفورية وآخر ظهور للمستخدم.
     *
     * @return array{is_online: bool, label: string, last_seen_ts: int|null}
     */
    public static function getUserPresence(User|int|null $user): array
    {
        if (! $user) {
            return [
                'is_online' => false,
                'label' => 'غير متصل',
                'last_seen_ts' => null,
            ];
        }

        $userId = $user instanceof User ? (int) $user->id : (int) $user;
        $fallbackCarbon = $user instanceof User ? $user->last_login_at : null;

        try {
            $ts = (int) self::fastCache()->get('eyal.presence.'.$userId, 0);
        } catch (\Throwable) {
            $ts = 0;
        }

        if ($ts <= 0 && $fallbackCarbon instanceof Carbon) {
            $ts = $fallbackCarbon->timestamp;
        }

        if ($ts <= 0) {
            return [
                'is_online' => false,
                'label' => 'غير متصل حالياً',
                'last_seen_ts' => null,
            ];
        }

        $diffSeconds = max(0, now()->timestamp - $ts);
        if ($diffSeconds <= 45) {
            return [
                'is_online' => true,
                'label' => 'متصل الآن',
                'last_seen_ts' => $ts,
            ];
        }

        $seenAt = Carbon::createFromTimestamp($ts);
        if ($seenAt->isToday()) {
            $label = $diffSeconds < 3600
                ? 'آخر ظهور '.$seenAt->diffForHumans()
                : 'آخر ظهور اليوم الساعة '.$seenAt->format('H:i');
        } elseif ($seenAt->isYesterday()) {
            $label = 'آخر ظهور أمس الساعة '.$seenAt->format('H:i');
        } else {
            $label = 'آخر ظهور '.$seenAt->format('Y/m/d H:i');
        }

        return [
            'is_online' => false,
            'label' => $label,
            'last_seen_ts' => $ts,
        ];
    }

    /**
     * إصدار ملفات الواجهة الثابتة (CSS/JS) لكسر كاش المتصفح فوراً عند أي تعديل بدون استعلام قاعدة بيانات.
     */
    public static function assetVersion(): string
    {
        if (isset(self::$runtimeCache['asset_version'])) {
            return (string) self::$runtimeCache['asset_version'];
        }

        $cssTime = @filemtime(public_path('css/custom.css')) ?: 1;
        $jsTime = @filemtime(public_path('js/app.js')) ?: 1;
        $version = 'v7.'.max($cssTime, $jsTime);

        self::$runtimeCache['asset_version'] = $version;

        return $version;
    }

    private static function cacheVersion(): string
    {
        if (self::$cacheVersion !== null) {
            return self::$cacheVersion;
        }

        try {
            $store = self::fastCache();
            $v = $store->get(self::VERSION_CACHE_KEY);
            if (! $v) {
                $v = '1';
                $store->forever(self::VERSION_CACHE_KEY, $v);
            }
            self::$cacheVersion = (string) $v;
        } catch (\Throwable) {
            self::$cacheVersion = '1';
        }

        return self::$cacheVersion;
    }

    /**
     * إعدادات المعهد من قاعدة البيانات مع الرجوع إلى الإعدادات الثابتة.
     *
     * @return array<string, mixed>
     */
    public static function institute(): array
    {
        return self::remember('institute', 3600, function (): array {
            $config = config('eyal.institute', []);

            try {
                $settings = Setting::allAsArray();
            } catch (\Throwable) {
                $settings = [];
            }

            $institute = $config;

            foreach ([
                'name',
                'short_name',
                'address',
                'phone',
                'whatsapp',
                'whatsapp_intl',
                'phone_intl',
                'instagram',
                'tiktok',
                'facebook',
                'facebook_url',
                'maps_url',
                'lat',
                'lng',
                'work_hours',
                'announcement',
            ] as $key) {
                if (array_key_exists('institute.'.$key, $settings)) {
                    $institute[$key] = $settings['institute.'.$key];
                }
            }

            if (
                (! array_key_exists('institute.lat', $settings) || ! array_key_exists('institute.lng', $settings))
                && ! empty($institute['maps_url'])
                && is_string($institute['maps_url'])
            ) {
                if (preg_match('/@(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?)/', $institute['maps_url'], $m)) {
                    $institute['lat'] = (float) $m[1];
                    $institute['lng'] = (float) $m[2];
                } elseif (preg_match('/!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)/', $institute['maps_url'], $m)) {
                    $institute['lat'] = (float) $m[1];
                    $institute['lng'] = (float) $m[2];
                }
            }
            $institute['lat'] = (float) ($institute['lat'] ?? 30.0367708);
            $institute['lng'] = (float) ($institute['lng'] ?? 47.9226716);

            $about = $settings['institute.about'] ?? [];
            $institute['about'] = array_merge(
                is_array($config['about'] ?? null) ? $config['about'] : [],
                is_array($about) ? $about : [],
            );

            $institute['primary_color'] = $settings['theme.primary_color'] ?? ($config['primary_color'] ?? '#0B3A63');
            $institute['secondary_color'] = $settings['theme.secondary_color'] ?? ($config['secondary_color'] ?? '#1565C0');
            $institute['staff_enabled'] = (bool) ($settings['features.staff_enabled'] ?? ($config['staff_enabled'] ?? true));
            $institute['parent_enabled'] = (bool) ($settings['features.parent_enabled'] ?? true);
            $institute['student_enabled'] = (bool) ($settings['features.student_enabled'] ?? true);
            $institute['announcement_bar'] = (bool) ($settings['features.announcement_bar'] ?? true);
            $institute['registrations_open'] = (bool) ($settings['features.registrations_open'] ?? true);
            $institute['chat_enabled'] = (bool) ($settings['features.chat_enabled'] ?? true);
            $institute['chat_parent_teacher_enabled'] = (bool) ($settings['features.chat_parent_teacher_enabled'] ?? true);
            $institute['chat_parent_admin_enabled'] = (bool) ($settings['features.chat_parent_admin_enabled'] ?? true);
            $institute['chat_staff_admin_enabled'] = (bool) ($settings['features.chat_staff_admin_enabled'] ?? true);
            $institute['chat_parents_group_enabled'] = (bool) ($settings['features.chat_parents_group_enabled'] ?? true);
            $institute['chat_staff_group_enabled'] = (bool) ($settings['features.chat_staff_group_enabled'] ?? true);
            $institute['ai_enabled'] = (bool) ($settings['features.ai_enabled'] ?? true);
            $institute['ai_auto_homework'] = (bool) ($settings['features.ai_auto_homework'] ?? true);
            $institute['ai_auto_reply'] = (bool) ($settings['features.ai_auto_reply'] ?? true);
            $institute['ai_model'] = (string) ($settings['ai.model'] ?? 'gemini-2.5-flash');
            $institute['ai_has_key'] = ! empty($settings['ai.gemini_api_key']);
            $institute['ai_key_masked'] = ! empty($settings['ai.gemini_api_key'])
                ? (substr((string) $settings['ai.gemini_api_key'], 0, 6).'••••••••••••')
                : '';

            $institute['push_enabled'] = (bool) ($settings['features.push_enabled'] ?? true);
            $institute['firebase_api_key'] = (string) ($settings['firebase.api_key'] ?? '');
            $institute['firebase_auth_domain'] = (string) ($settings['firebase.auth_domain'] ?? '');
            $institute['firebase_project_id'] = (string) ($settings['firebase.project_id'] ?? '');
            $institute['firebase_messaging_sender_id'] = (string) ($settings['firebase.messaging_sender_id'] ?? '');
            $institute['firebase_app_id'] = (string) ($settings['firebase.app_id'] ?? '');
            $institute['firebase_vapid_key'] = (string) ($settings['firebase.vapid_key'] ?? '');
            $institute['firebase_has_service_account'] = ! empty($settings['firebase.service_account_json']);
            $institute['firebase_client_configured'] = $institute['push_enabled']
                && $institute['firebase_api_key'] !== ''
                && $institute['firebase_project_id'] !== ''
                && $institute['firebase_messaging_sender_id'] !== ''
                && $institute['firebase_app_id'] !== ''
                && $institute['firebase_vapid_key'] !== '';

            $waKey = trim((string) ($settings['wa.api_key'] ?? ''));
            $waSecret = trim((string) ($settings['wa.webhook_secret'] ?? ''));
            $institute['wa_api_enabled'] = (bool) ($settings['features.wa_api_enabled'] ?? ($waKey !== ''));
            $institute['wa_api_url'] = (string) ($settings['wa.api_url'] ?? 'https://whtsappapi.almoq3.com/api/v1');
            $institute['wa_api_has_key'] = $waKey !== '';
            $institute['wa_api_key_masked'] = $waKey !== ''
                ? (substr($waKey, 0, 8).'••••••••••••')
                : '';
            $institute['wa_has_webhook_secret'] = $waSecret !== '';
            $institute['wa_webhook_secret_masked'] = $waSecret !== ''
                ? (substr($waSecret, 0, 8).'••••••••••••')
                : '';
            $institute['wa_ai_group_jid'] = (string) ($settings['wa.ai_group_jid'] ?? '');
            $institute['wa_ai_group_enabled'] = (bool) ($settings['features.wa_ai_group_enabled'] ?? false);
            $institute['wa_ai_private_reply'] = (bool) ($settings['features.wa_ai_private_reply'] ?? false);
            $institute['wa_notify_attendance'] = (bool) ($settings['features.wa_notify_attendance'] ?? true);
            $institute['wa_notify_payments'] = (bool) ($settings['features.wa_notify_payments'] ?? true);
            $institute['wa_notify_registrations'] = (bool) ($settings['features.wa_notify_registrations'] ?? true);
            $institute['wa_notify_grades'] = (bool) ($settings['features.wa_notify_grades'] ?? true);
            $institute['wa_notify_homework'] = (bool) ($settings['features.wa_notify_homework'] ?? true);

            return $institute;
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function stages(): Collection
    {
        self::ensureCurriculumTables();

        $rows = self::remember('stages', 3600, function (): array {
            return Stage::query()
                ->with(['subjects' => fn ($q) => $q->with('teachers:id,name')->orderBy('sort_order')->orderBy('id')])
                ->withCount([
                    'courses' => fn (Builder $query) => $query->where('is_active', true),
                    'subjects',
                ])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Stage $stage) => [
                    'id' => (string) $stage->id,
                    'name' => $stage->name,
                    'visible' => $stage->is_visible,
                    'order' => $stage->sort_order,
                    'icon' => $stage->icon,
                    'color' => $stage->color,
                    'description' => $stage->description,
                    'courses' => (int) $stage->courses_count,
                    'subjects_count' => (int) $stage->subjects_count,
                    'subjects' => $stage->subjects->map(fn (Subject $sub) => [
                        'id' => (string) $sub->id,
                        'name' => $sub->name,
                        'stage_id' => (string) $sub->stage_id,
                        'active' => (bool) $sub->is_active,
                        'teachers' => $sub->teachers->map(fn (User $t) => [
                            'id' => (string) $t->id,
                            'name' => $t->name,
                        ])->values()->all(),
                        'teacher_names' => $sub->teachers->pluck('name')->implode('، '),
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * جميع المواد الدراسية مع مراحلها ومدرسيها.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function subjectsAll(?string $stageId = null): Collection
    {
        self::ensureCurriculumTables();

        $rows = self::remember('subjects_all', 3600, function (): array {
            return Subject::query()
                ->with(['stage:id,name', 'teachers:id,name'])
                ->withCount('courses')
                ->orderBy('stage_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Subject $subject) => [
                    'id' => (string) $subject->id,
                    'name' => $subject->name,
                    'stage_id' => (string) $subject->stage_id,
                    'stage_name' => $subject->stage?->name ?? '—',
                    'description' => $subject->description ?? '',
                    'order' => (int) $subject->sort_order,
                    'active' => (bool) $subject->is_active,
                    'courses_count' => (int) $subject->courses_count,
                    'teacher_ids' => $subject->teachers->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                    'teachers' => $subject->teachers->map(fn (User $t) => [
                        'id' => (string) $t->id,
                        'name' => $t->name,
                    ])->values()->all(),
                    'teacher_names' => $subject->teachers->pluck('name')->implode('، '),
                ])
                ->values()
                ->all();
        });

        $collection = collect($rows);

        if ($stageId !== null && $stageId !== '') {
            return $collection->where('stage_id', (string) $stageId)->values();
        }

        return $collection;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function courses(?string $stageId = null): Collection
    {
        self::ensureCurriculumTables();

        $allActive = self::remember('courses_active', 1800, function (): array {
            return Course::query()
                ->with(['stage:id,name', 'teacher:id,name', 'subjects:id,stage_id,name', 'subjectTeachers:id,name'])
                ->withCount('students')
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(function (Course $course) {
                    $teachersById = $course->subjectTeachers->keyBy('id');
                    $subjectNames = $course->subjects->pluck('name')->filter()->unique()->implode(' + ');
                    $teacherNames = $course->subjectTeachers->pluck('name')->filter()->unique()->implode('، ');

                    return [
                        'id' => (string) $course->id,
                        'name' => $course->name,
                        'teacher' => $teacherNames ?: ($course->teacher?->name ?? '—'),
                        'stage' => (string) $course->stage_id,
                        'stage_name' => $course->stage?->name ?? '—',
                        'subject' => $subjectNames ?: $course->subject,
                        'subject_ids' => $course->subjects->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                        'course_subjects' => $course->subjects->map(fn (Subject $sub) => [
                            'id' => (string) $sub->id,
                            'name' => $sub->name,
                            'teacher_id' => (string) ($sub->pivot?->teacher_id ?? ''),
                            'teacher_name' => $sub->pivot?->teacher_id ? ($teachersById->get($sub->pivot->teacher_id)?->name ?? '—') : '—',
                        ])->values()->all(),
                        'price' => (int) $course->price,
                        'seats' => (int) $course->seats,
                        'enrolled' => (int) $course->students_count,
                        'schedule' => $course->schedule_text,
                        'description' => $course->description,
                        'color' => $course->color,
                        'active' => $course->is_active,
                    ];
                })
                ->values()
                ->all();
        });

        $collection = collect($allActive);

        if ($stageId !== null && $stageId !== '') {
            return $collection->where('stage', (string) $stageId)->values();
        }

        return $collection;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function offers(): Collection
    {
        $rows = self::remember('offers_active', 1800, function (): array {
            return Offer::query()
                ->with('stage:id,name')
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(fn (Offer $offer) => [
                    'id' => (string) $offer->id,
                    'title' => $offer->title,
                    'stage' => $offer->stage?->name ?? 'جميع المراحل',
                    'old_price' => (int) $offer->old_price,
                    'price' => (int) $offer->price,
                    'discount' => $offer->discount_label,
                    'expires' => $offer->ends_at?->toDateString() ?? '',
                    'active' => $offer->is_active,
                    'description' => $offer->description,
                    'color' => $offer->color,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function announcements(): Collection
    {
        $rows = self::remember('announcements_published', 1800, function (): array {
            return Announcement::query()
                ->where('is_published', true)
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Announcement $announcement) => [
                    'id' => (string) $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'category' => $announcement->category,
                    'date' => ($announcement->published_at ?? $announcement->created_at)?->toDateString() ?? '',
                    'pinned' => $announcement->is_pinned,
                    'color' => $announcement->color,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function schedule(): Collection
    {
        $rows = self::remember('schedule_active', 1800, function (): array {
            return ScheduleSlot::query()
                ->with(['course.stage:id,name', 'course.teacher:id,name'])
                ->where('is_active', true)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->map(fn (ScheduleSlot $slot) => [
                    'id' => (string) $slot->id,
                    'course_id' => (string) ($slot->course_id ?? ''),
                    'day' => $slot->day_name,
                    'stage' => $slot->stage_name ?? $slot->course?->stage?->name ?? 'جميع المراحل',
                    'course' => self::courseLabel($slot->course_name ?? $slot->course?->name ?? '—'),
                    'time' => self::formatTimeRange($slot->start_time, $slot->end_time),
                    'teacher' => $slot->teacher_name ?? $slot->course?->teacher?->name ?? 'الكادر التدريسي',
                    'hall' => $slot->hall,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function staff(): Collection
    {
        $rows = self::remember('staff_public', 1800, function (): array {
            $coursesByTeacher = Course::query()
                ->with('stage:id,name')
                ->whereNotNull('teacher_id')
                ->get()
                ->groupBy('teacher_id');

            return Staff::query()
                ->with('user:id,name,phone')
                ->where('is_public', true)
                ->orderBy('id')
                ->get()
                ->map(function (Staff $member) use ($coursesByTeacher) {
                    $tCourses = $coursesByTeacher->get($member->user_id, collect());
                    $subjectsLabel = $tCourses->map(fn (Course $c) => $c->subject ?: $c->name)->filter()->unique()->implode('، ');
                    $stagesLabel = $tCourses->map(fn (Course $c) => $c->stage?->name)->filter()->unique()->implode('، ');

                    return [
                        'id' => (string) $member->id,
                        'name' => $member->user?->name ?? 'عضو الكادر',
                        'subject' => $member->subject ?: ($subjectsLabel ?: 'كادر تدريسي'),
                        'stage' => $member->stage_label ?: ($stagesLabel ?: 'جميع المراحل'),
                        'bio' => $member->bio,
                        'phone' => $member->phone,
                        'color' => $member->color,
                    ];
                })
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function students(): Collection
    {
        self::ensureCurriculumTables();

        $rows = self::remember('students_all', 900, function (): array {
            return self::studentQuery()
                ->orderBy('student_no')
                ->get()
                ->map(fn (Student $student) => self::mapStudent($student))
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function studentByNo(?string $studentNo): ?array
    {
        if (! $studentNo) {
            return null;
        }

        $student = self::studentQuery()
            ->where('student_no', (string) $studentNo)
            ->first();

        return $student ? self::mapStudent($student) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function studentByPhone(?string $phone): ?array
    {
        if (! $phone) {
            return null;
        }

        $student = self::studentQuery()
            ->where('phone', $phone)
            ->first();

        return $student ? self::mapStudent($student) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function searchStudent(string $term): ?array
    {
        $term = trim($term);

        if ($term === '') {
            return null;
        }

        $student = self::studentQuery()
            ->where(function (Builder $query) use ($term) {
                $query->where('student_no', $term)
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('phone', 'like', '%'.$term.'%');
            })
            ->first();

        return $student ? self::mapStudent($student) : null;
    }

    /**
     * قائمة جميع أبناء ولي الأمر مع بياناتهم الأكاديمية
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function childrenForParent(int|array $parent): Collection
    {
        $parentId = is_array($parent) ? ($parent['id'] ?? null) : $parent;
        if (! $parentId) {
            return collect();
        }

        $rows = self::remember('parent_children.'.$parentId, 600, function () use ($parentId): array {
            $students = self::studentQuery()
                ->whereHas('parents', fn (Builder $query) => $query->where('users.id', $parentId))
                ->orderBy('id')
                ->get();

            if ($students->isEmpty()) {
                $parentUser = User::find($parentId);
                if ($parentUser && $parentUser->phone) {
                    $phoneMatches = Student::query()
                        ->where('guardian_phone', $parentUser->phone)
                        ->orWhere('phone', $parentUser->phone)
                        ->pluck('id');

                    if ($phoneMatches->isNotEmpty()) {
                        foreach ($phoneMatches as $studentId) {
                            $parentUser->children()->syncWithoutDetaching([
                                (int) $studentId => ['relation' => 'ولي أمر', 'is_primary' => true],
                            ]);
                        }

                        $students = self::studentQuery()
                            ->whereHas('parents', fn (Builder $query) => $query->where('users.id', $parentId))
                            ->orderBy('id')
                            ->get();
                    }
                }
            }

            return $students
                ->map(fn (Student $student) => self::mapStudent($student))
                ->values()
                ->all();
        });

        return collect($rows);
    }

    public static function studentForUser(?array $user, int|string|null $selectedId = null): ?array
    {
        if (! $user) {
            return null;
        }

        $role = $user['role'] ?? null;
        $userId = $user['id'] ?? null;
        $studentNo = $user['student_no'] ?? null;

        if ($role === 'parent' && $userId) {
            $children = self::childrenForParent((int) $userId);

            if ($selectedId) {
                $found = $children->first(fn (array $ch) => $ch['id'] === (string) $selectedId || $ch['student_no'] === (string) $selectedId);
                if ($found) {
                    return $found;
                }
            }

            if ($children->isNotEmpty()) {
                return $children->first();
            }

            if ($studentNo) {
                return self::studentByNo((string) $studentNo);
            }

            return null;
        }

        if ($role === 'student') {
            return self::remember('student_for_user.'.$userId.'.'.$studentNo, 600, function () use ($userId, $studentNo): ?array {
                $student = $userId
                    ? self::studentQuery()->where('user_id', $userId)->first()
                    : null;

                if (! $student && $studentNo) {
                    $student = self::studentQuery()->where('student_no', $studentNo)->first();
                }

                return $student ? self::mapStudent($student) : null;
            });
        }

        return null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function gradesFor(?string $studentNo): Collection
    {
        if (! $studentNo) {
            return collect();
        }

        $rows = self::remember('grades_for.'.$studentNo, 600, function () use ($studentNo): array {
            $student = self::findStudentModel($studentNo);

            if (! $student) {
                return [];
            }

            return Grade::query()
                ->with(['assessment.course.stage:id,name'])
                ->where('student_id', $student->id)
                ->get()
                ->sortByDesc(fn (Grade $grade) => $grade->assessment?->assessed_at?->timestamp ?? 0)
                ->map(function (Grade $grade) use ($student) {
                    $assessment = $grade->assessment;
                    $course = $assessment?->course;

                    return [
                        'id' => (string) $grade->id,
                        'student_no' => $student->student_no,
                        'student' => $student->name,
                        'course' => self::courseLabel($course?->name ?? '—'),
                        'exam' => $assessment?->title ?? '—',
                        'score' => (int) round((float) $grade->score),
                        'max' => (int) round((float) ($assessment?->max_score ?? 100)),
                        'date' => $assessment?->assessed_at?->toDateString() ?? '',
                        'stage' => $course?->stage?->name ?? $student->stage?->name ?? '—',
                    ];
                })
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function attendanceFor(?string $studentNo): Collection
    {
        if (! $studentNo) {
            return collect();
        }

        $rows = self::remember('attendance_for.'.$studentNo, 600, function () use ($studentNo): array {
            $student = self::findStudentModel($studentNo);

            if (! $student) {
                return [];
            }

            return AttendanceRecord::query()
                ->with(['course.stage:id,name'])
                ->where('student_id', $student->id)
                ->orderByDesc('date')
                ->get()
                ->map(fn (AttendanceRecord $record) => [
                    'id' => (string) $record->id,
                    'student_no' => $student->student_no,
                    'student' => $student->name,
                    'date' => $record->date?->toDateString() ?? '',
                    'status' => $record->status,
                    'stage' => $record->course?->stage?->name ?? $student->stage?->name ?? '—',
                    'subject' => $record->course?->subject ?: ($record->course?->name ?: 'حضور عام'),
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function paymentsFor(?string $studentNo): Collection
    {
        if (! $studentNo) {
            return collect();
        }

        $rows = self::remember('payments_for.'.$studentNo, 600, function () use ($studentNo): array {
            $student = self::findStudentModel($studentNo);

            if (! $student) {
                return [];
            }

            return Payment::query()
                ->where('student_id', $student->id)
                ->orderByDesc('paid_at')
                ->get()
                ->map(fn (Payment $payment) => [
                    'id' => (string) $payment->id,
                    'student_no' => $student->student_no,
                    'student' => $student->name,
                    'amount' => (int) $payment->amount,
                    'date' => $payment->paid_at?->toDateString() ?? '',
                    'method' => $payment->method_label,
                    'receipt' => $payment->receipt_no,
                    'note' => $payment->note,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function homeworkFor(?string $stageName = null, ?string $studentNo = null): Collection
    {
        self::ensureCurriculumTables();

        $cacheKey = 'homework_for.'.($stageName ?? 'all').'.'.($studentNo ?? 'guest');

        $rows = self::remember($cacheKey, 600, function () use ($stageName, $studentNo): array {
            $query = HomeworkTask::query()
                ->with(['course.stage:id,name', 'subject:id,name', 'teacher:id,name', 'files', 'submissions.files'])
                ->where('status', '!=', 'closed');

            $student = self::findStudentModel($studentNo);

            if ($student) {
                $query->where(function (Builder $query) use ($student) {
                    $query->where(function (Builder $query) use ($student) {
                        $query->where('is_class_task', true)
                            ->whereHas('course.students', fn (Builder $sq) => $sq->where('students.id', $student->id));
                    })->orWhere('student_id', $student->id);
                });
            } elseif ($stageName) {
                $query->whereHas('course.stage', fn (Builder $query) => $query->where('name', $stageName));
            }

            return $query
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->get()
                ->map(function (HomeworkTask $task) use ($student) {
                    $submission = $student
                        ? $task->submissions->firstWhere('student_id', $student->id)
                        : null;

                    $taskFiles = $task->is_class_task
                        ? $task->files->whereNull('homework_submission_id')
                        : $task->files;

                    return [
                        'id' => (string) $task->id,
                        'title' => $task->title,
                        'course' => self::courseLabel($task->course?->name ?? '—'),
                        'course_id' => (string) ($task->course_id ?? ''),
                        'subject_id' => (string) ($task->subject_id ?? ''),
                        'subject_name' => $task->subject?->name ?? '',
                        'stage' => $task->course?->stage?->name ?? '—',
                        'teacher' => $task->teacher?->name ?? 'الإدارة',
                        'due' => $task->due_date?->toDateString() ?? '',
                        'description' => $task->description,
                        'color' => $task->color ?? 'blue',
                        'submission_status' => $submission?->status,
                        'submission_label' => $submission?->status_label,
                        'feedback' => $submission?->feedback,
                        'files' => $taskFiles->map(fn ($file) => [
                            'id' => (string) $file->id,
                            'name' => $file->original_name,
                            'size' => $file->human_size,
                            'is_image' => (bool) $file->is_image,
                        ])->values()->all(),
                        'submission_files' => $submission
                            ? $submission->files->map(fn ($file) => [
                                'id' => (string) $file->id,
                                'name' => $file->original_name,
                                'size' => $file->human_size,
                                'is_image' => (bool) $file->is_image,
                            ])->values()->all()
                            : [],
                    ];
                })
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @param  array<string, mixed>|null  $user
     * @return Collection<int, array<string, mixed>>
     */
    public static function notificationsFor(?array $user): Collection
    {
        $userId = (int) ($user['id'] ?? 0);
        $role = (string) ($user['role'] ?? 'guest');

        $rows = self::remember("notifications_for.{$role}.{$userId}", 300, function () use ($user, $role, $userId): array {
            $studentNosByUserId = Student::query()
                ->whereNotNull('user_id')
                ->pluck('student_no', 'user_id')
                ->all();

            if (! $user) {
                return SystemNotification::query()
                    ->with(['stage:id,name', 'course:id,name', 'user:id,name'])
                    ->where('audience_type', 'all')
                    ->latest()
                    ->get()
                    ->map(fn (SystemNotification $notification) => self::mapNotification($notification, false, $studentNosByUserId))
                    ->values()
                    ->all();
            }

            if ($role === 'admin') {
                return SystemNotification::query()
                    ->with(['stage:id,name', 'course:id,name', 'user:id,name', 'recipients'])
                    ->latest()
                    ->get()
                    ->map(function (SystemNotification $notification) use ($userId, $studentNosByUserId) {
                        $recipient = $notification->recipients->firstWhere('user_id', $userId);

                        return self::mapNotification($notification, $recipient?->read_at !== null, $studentNosByUserId);
                    })
                    ->values()
                    ->all();
            }

            $userIds = self::notificationUserIds($user);

            return NotificationRecipient::query()
                ->with(['notification.stage:id,name', 'notification.course:id,name', 'notification.user:id,name'])
                ->whereIn('user_id', $userIds)
                ->get()
                ->filter(fn (NotificationRecipient $recipient) => $recipient->notification !== null)
                ->groupBy('notification_id')
                ->map(function (Collection $recipients) use ($userId, $studentNosByUserId) {
                    /** @var NotificationRecipient $recipient */
                    $recipient = $recipients->firstWhere('user_id', $userId) ?? $recipients->first();

                    return self::mapNotification($recipient->notification, $recipient->read_at !== null, $studentNosByUserId);
                })
                ->sortByDesc('date')
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @param  array<string, mixed>|null  $user
     */
    public static function unreadNotificationsCount(?array $user): int
    {
        if (! $user || empty($user['id'])) {
            return 0;
        }

        $userId = (int) $user['id'];

        return (int) self::remember('unread_notif.'.$userId, 180, function () use ($user): int {
            return NotificationRecipient::query()
                ->whereIn('user_id', self::notificationUserIds($user))
                ->whereNull('read_at')
                ->count();
        });
    }

    /**
     * @param  array<string, mixed>  $student
     */
    public static function remaining(array $student): int
    {
        return max(0, (int) $student['fee'] - (int) $student['paid']);
    }

    /**
     * إحصائيات المعهد المجمعة بأقل عدد ممكن من الاستعلامات مع الكاش.
     *
     * @return array<string, int>
     */
    public static function stats(): array
    {
        return self::remember('stats', 900, function (): array {
            $attRow = AttendanceRecord::query()
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status IN ('present', 'late', 'excused') THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
                ")
                ->first();

            $regRow = RegistrationRequest::query()
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_count,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                ")
                ->first();

            $courseRow = Course::query()
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                ")
                ->first();

            $offerRow = Offer::query()
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                ")
                ->first();

            $paymentRow = Payment::query()
                ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(amount), 0) as total_paid')
                ->first();

            $attendanceRecords = (int) ($attRow->total ?? 0);
            $present = (int) ($attRow->present_count ?? 0);
            $totalFees = (int) StudentCharge::query()
                ->whereHas('student', fn (Builder $q) => $q->whereNull('deleted_at')->whereHas('courses'))
                ->sum('amount');
            $totalPaid = (int) ($paymentRow->total_paid ?? 0);

            return [
                'students' => (int) Student::count(),
                'stages' => (int) Stage::where('is_visible', true)->count(),
                'registrations' => (int) ($regRow->total ?? 0),
                'new_registrations' => (int) ($regRow->new_count ?? 0),
                'accepted_registrations' => (int) ($regRow->accepted_count ?? 0),
                'rejected_registrations' => (int) ($regRow->rejected_count ?? 0),
                'attendance_rate' => $attendanceRecords > 0 ? (int) round(($present / $attendanceRecords) * 100) : 0,
                'absences' => (int) ($attRow->absent_count ?? 0),
                'lates' => (int) ($attRow->late_count ?? 0),
                'attendance_records' => $attendanceRecords,
                'exams' => (int) Grade::count(),
                'fees' => $totalFees,
                'paid' => $totalPaid,
                'remaining' => max(0, $totalFees - $totalPaid),
                'courses' => (int) ($courseRow->total ?? 0),
                'active_courses' => (int) ($courseRow->active_count ?? 0),
                'offers' => (int) ($offerRow->total ?? 0),
                'active_offers' => (int) ($offerRow->active_count ?? 0),
                'payments' => (int) ($paymentRow->total_count ?? 0),
                'payments_total' => $totalPaid,
                'staff' => (int) Staff::count(),
                'announcements' => (int) Announcement::where('is_published', true)->count(),
            ];
        });
    }

    /**
     * @return array<int, array{name: string, count: int, percent: int}>
     */
    public static function studentsByStage(): array
    {
        return self::remember('students_by_stage', 900, function (): array {
            $stages = Stage::query()
                ->withCount('students')
                ->orderBy('sort_order')
                ->get();

            $total = max(1, (int) $stages->sum('students_count'));

            return $stages
                ->map(fn (Stage $stage) => [
                    'name' => $stage->name,
                    'count' => (int) $stage->students_count,
                    'percent' => (int) round(($stage->students_count / $total) * 100),
                ])
                ->all();
        });
    }

    /**
     * @return array<int, array{name: string, count: int, price: int}>
     */
    public static function studentsByCourse(): array
    {
        return self::remember('students_by_course', 900, function (): array {
            return Course::query()
                ->withCount('students')
                ->orderBy('id')
                ->get()
                ->map(fn (Course $course) => [
                    'name' => $course->name,
                    'count' => (int) $course->students_count,
                    'price' => (int) $course->price,
                ])
                ->all();
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function registrations(): Collection
    {
        $rows = self::remember('registrations_all', 600, function (): array {
            return RegistrationRequest::query()
                ->with(['stage:id,name', 'course:id,name'])
                ->latest()
                ->get()
                ->map(fn (RegistrationRequest $registration) => [
                    'id' => (string) $registration->id,
                    'name' => $registration->student_name,
                    'parent' => $registration->parent_name,
                    'phone' => $registration->phone,
                    'stage_id' => (string) ($registration->stage_id ?? ''),
                    'stage' => $registration->stage?->name ?? '—',
                    'course_id' => (string) ($registration->course_id ?? ''),
                    'course' => self::courseLabel($registration->course?->name ?? ''),
                    'date' => $registration->created_at?->toDateString() ?? '',
                    'status' => $registration->status,
                    'notes' => $registration->note,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function payments(): Collection
    {
        $rows = self::remember('payments_all', 600, function (): array {
            return Payment::query()
                ->with('student:id,student_no,name')
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Payment $payment) => [
                    'id' => (string) $payment->id,
                    'student_no' => $payment->student?->student_no ?? '',
                    'student' => $payment->student?->name ?? '—',
                    'amount' => (int) $payment->amount,
                    'date' => $payment->paid_at?->toDateString() ?? '',
                    'method' => $payment->method_label,
                    'method_raw' => $payment->method,
                    'receipt' => $payment->receipt_no,
                    'note' => $payment->note,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * جميع الدورات (بما فيها المتوقفة) لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function coursesAll(): Collection
    {
        self::ensureCurriculumTables();

        $rows = self::remember('courses_all', 1800, function (): array {
            return Course::query()
                ->with(['stage:id,name', 'teacher:id,name', 'subjects:id,stage_id,name', 'subjectTeachers:id,name'])
                ->withCount('students')
                ->orderBy('id')
                ->get()
                ->map(function (Course $course) {
                    $teachersById = $course->subjectTeachers->keyBy('id');
                    $subjectNames = $course->subjects->pluck('name')->filter()->unique()->implode(' + ');
                    $teacherNames = $course->subjectTeachers->pluck('name')->filter()->unique()->implode('، ');

                    return [
                        'id' => (string) $course->id,
                        'name' => $course->name,
                        'teacher' => $teacherNames ?: ($course->teacher?->name ?? '—'),
                        'teacher_id' => (string) ($course->teacher_id ?? ''),
                        'stage' => (string) $course->stage_id,
                        'stage_name' => $course->stage?->name ?? '—',
                        'subject' => $subjectNames ?: $course->subject,
                        'subject_ids' => $course->subjects->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                        'course_subjects' => $course->subjects->map(fn (Subject $sub) => [
                            'id' => (string) $sub->id,
                            'name' => $sub->name,
                            'teacher_id' => (string) ($sub->pivot?->teacher_id ?? ''),
                            'teacher_name' => $sub->pivot?->teacher_id ? ($teachersById->get($sub->pivot->teacher_id)?->name ?? '—') : '—',
                        ])->values()->all(),
                        'price' => (int) $course->price,
                        'seats' => (int) $course->seats,
                        'enrolled' => (int) $course->students_count,
                        'schedule' => $course->schedule_text,
                        'description' => $course->description,
                        'color' => $course->color,
                        'active' => $course->is_active,
                    ];
                })
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * جميع أعضاء الكادر (بما فيهم غير الظاهرين للعامة) مع دوراتهم ومراحلهم وموادهم وطلابهم.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function staffAll(): Collection
    {
        self::ensureCurriculumTables();

        $rows = self::remember('staff_all', 1800, function (): array {
            $allCourses = Course::query()
                ->with(['stage:id,name', 'subjects:id,name'])
                ->withCount('students')
                ->get();

            return Staff::query()
                ->with([
                    'user:id,name,username,phone,is_active',
                    'user.teachingStages:id,name',
                    'user.teachingSubjects.stage:id,name',
                ])
                ->orderBy('id')
                ->get()
                ->map(function (Staff $member) use ($allCourses) {
                    $user = $member->user;
                    $teachingStages = $user?->teachingStages ?? collect();
                    $teachingSubjects = $user?->teachingSubjects ?? collect();

                    $tCourses = $allCourses->filter(function (Course $c) use ($member) {
                        if ((int) $c->teacher_id === (int) $member->user_id) {
                            return true;
                        }

                        return $c->subjects->contains(fn (Subject $s) => (int) ($s->pivot?->teacher_id ?? 0) === (int) $member->user_id);
                    })->values();

                    $subjectsLabel = $teachingSubjects->isNotEmpty()
                        ? $teachingSubjects->pluck('name')->unique()->implode('، ')
                        : ($member->subject ?: $tCourses->map(fn (Course $c) => $c->subject ?: $c->name)->filter()->unique()->implode('، '));

                    $stagesLabel = $teachingStages->isNotEmpty()
                        ? $teachingStages->pluck('name')->unique()->implode('، ')
                        : ($teachingSubjects->isNotEmpty()
                            ? $teachingSubjects->map(fn (Subject $s) => $s->stage?->name)->filter()->unique()->implode('، ')
                            : ($member->stage_label ?: $tCourses->map(fn (Course $c) => $c->stage?->name)->filter()->unique()->implode('، ')));

                    $stageIds = $teachingStages->pluck('id')
                        ->merge($teachingSubjects->pluck('stage_id'))
                        ->merge($tCourses->pluck('stage_id'))
                        ->filter()
                        ->unique()
                        ->map(fn ($id) => (string) $id)
                        ->values()
                        ->all();

                    $subjectIds = $teachingSubjects->pluck('id')
                        ->map(fn ($id) => (string) $id)
                        ->values()
                        ->all();

                    return [
                        'id' => (string) $member->id,
                        'user_id' => (string) $member->user_id,
                        'name' => $user?->name ?? 'عضو الكادر',
                        'username' => $user?->username,
                        'subject' => $subjectsLabel,
                        'stage' => $stagesLabel,
                        'bio' => $member->bio,
                        'phone' => $member->phone ?? $user?->phone,
                        'color' => $member->color,
                        'active' => (bool) $user?->is_active,
                        'is_public' => $member->is_public,
                        'stage_ids' => $stageIds,
                        'subject_ids' => $subjectIds,
                        'course_ids' => $tCourses->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                        'subjects_list' => $teachingSubjects->map(fn (Subject $s) => [
                            'id' => (string) $s->id,
                            'name' => $s->name,
                            'stage_id' => (string) $s->stage_id,
                            'stage_name' => $s->stage?->name ?? '—',
                        ])->values()->all(),
                        'courses_list' => $tCourses->map(fn (Course $c) => [
                            'id' => (string) $c->id,
                            'name' => $c->name,
                            'subject' => $c->subject,
                            'stage_name' => $c->stage?->name ?? '—',
                            'students_count' => (int) $c->students_count,
                        ])->values()->all(),
                        'courses_count' => $tCourses->count(),
                        'students_count' => (int) $tCourses->sum('students_count'),
                    ];
                })
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * قائمة جميع أولياء الأمور مع أبنائهم المرتبطين بهم لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function parentsAll(): Collection
    {
        $rows = self::remember('parents_all', 900, function (): array {
            return User::query()
                ->where('role', 'parent')
                ->with(['children.stage:id,name'])
                ->orderBy('id')
                ->get()
                ->map(fn (User $parent) => [
                    'id' => (string) $parent->id,
                    'name' => $parent->name,
                    'username' => $parent->username,
                    'phone' => $parent->phone ?? '',
                    'active' => (bool) $parent->is_active,
                    'created_at' => $parent->created_at?->toDateString() ?? '',
                    'student_ids' => $parent->children->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
                    'children' => $parent->children->map(fn (Student $st) => [
                        'id' => (string) $st->id,
                        'student_no' => $st->student_no,
                        'name' => $st->name,
                        'stage_name' => $st->stage?->name ?? '—',
                        'relation' => $st->pivot?->relation ?? 'ولي أمر',
                    ])->values()->all(),
                    'children_count' => $parent->children->count(),
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * قائمة حسابات الدخول لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function usersList(): Collection
    {
        $rows = self::remember('users_list', 900, function (): array {
            return User::query()
                ->orderBy('id')
                ->get()
                ->map(fn (User $user) => [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                    'role_label' => $user->roleLabel(),
                    'active' => $user->is_active,
                    'phone' => $user->phone,
                    'created_at' => $user->created_at?->toDateString() ?? '',
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * جميع الدرجات لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function gradesAll(): Collection
    {
        $rows = self::remember('grades_all', 600, function (): array {
            return Grade::query()
                ->with(['assessment.course.stage:id,name', 'student.stage:id,name'])
                ->get()
                ->sortByDesc(fn (Grade $grade) => $grade->assessment?->assessed_at?->timestamp ?? 0)
                ->map(function (Grade $grade) {
                    $assessment = $grade->assessment;
                    $course = $assessment?->course;

                    return [
                        'id' => (string) $grade->id,
                        'assessment_id' => (string) ($assessment?->id ?? ''),
                        'student_id' => (string) $grade->student_id,
                        'student_no' => $grade->student?->student_no ?? '',
                        'student' => $grade->student?->name ?? '—',
                        'course' => self::courseLabel($course?->name ?? '—'),
                        'course_id' => (string) ($course?->id ?? ''),
                        'exam' => $assessment?->title ?? '—',
                        'score' => (int) round((float) $grade->score),
                        'max' => (int) round((float) ($assessment?->max_score ?? 100)),
                        'date' => $assessment?->assessed_at?->toDateString() ?? '',
                        'stage' => $course?->stage?->name ?? $grade->student?->stage?->name ?? '—',
                    ];
                })
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * آخر سجلات الحضور لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function attendanceAll(int $limit = 10): Collection
    {
        $rows = self::remember('attendance_all.'.$limit, 600, function () use ($limit): array {
            return AttendanceRecord::query()
                ->with(['student.stage:id,name', 'course.stage:id,name'])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(fn (AttendanceRecord $record) => [
                    'id' => (string) $record->id,
                    'student_id' => (string) ($record->student_id ?? ''),
                    'student_no' => $record->student?->student_no ?? '',
                    'student' => $record->student?->name ?? '—',
                    'course_id' => (string) ($record->course_id ?? ''),
                    'date' => $record->date?->toDateString() ?? '',
                    'status' => $record->status,
                    'stage' => $record->course?->stage?->name ?? $record->student?->stage?->name ?? '—',
                    'subject' => $record->course?->subject ?: ($record->course?->name ?: 'حضور عام'),
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * جميع الواجبات لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function homeworkAll(): Collection
    {
        $rows = self::remember('homework_all', 600, function (): array {
            return HomeworkTask::query()
                ->with(['course.stage:id,name', 'subject:id,name', 'teacher:id,name', 'student:id,name,student_no', 'files'])
                ->orderByDesc('id')
                ->get()
                ->map(fn (HomeworkTask $task) => [
                    'id' => (string) $task->id,
                    'course_id' => (string) ($task->course_id ?? ''),
                    'title' => $task->title,
                    'course' => self::courseLabel($task->course?->name ?? '—'),
                    'subject_name' => $task->subject?->name ?? '',
                    'student_name' => $task->student?->name ?? '',
                    'student_no' => $task->student?->student_no ?? '',
                    'source' => $task->source ?? 'teacher',
                    'source_label' => $task->source_label,
                    'stage' => $task->course?->stage?->name ?? '—',
                    'teacher' => $task->teacher?->name ?? 'الإدارة',
                    'due' => $task->due_date?->toDateString() ?? ($task->created_at?->toDateString() ?? ''),
                    'description' => $task->description,
                    'color' => $task->color ?? 'blue',
                    'status' => $task->status,
                    'files' => $task->files->map(fn ($f) => [
                        'id' => (string) $f->id,
                        'name' => $f->original_name,
                        'size' => $f->human_size,
                        'is_image' => (bool) $f->is_image,
                    ])->values()->all(),
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * جميع العروض (بما فيها غير النشطة) لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function offersAll(): Collection
    {
        $rows = self::remember('offers_all', 1800, function (): array {
            return Offer::query()
                ->with('stage:id,name')
                ->orderBy('id')
                ->get()
                ->map(fn (Offer $offer) => [
                    'id' => (string) $offer->id,
                    'title' => $offer->title,
                    'stage' => $offer->stage?->name ?? 'جميع المراحل',
                    'stage_id' => (string) ($offer->stage_id ?? ''),
                    'old_price' => (int) $offer->old_price,
                    'price' => (int) $offer->price,
                    'discount' => $offer->discount_label,
                    'expires' => $offer->ends_at?->toDateString() ?? '',
                    'active' => $offer->is_active,
                    'description' => $offer->description,
                    'color' => $offer->color,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * جميع الإعلانات (بما فيها غير المنشورة) لواجهة الإدارة.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function announcementsAll(): Collection
    {
        $rows = self::remember('announcements_all', 1800, function (): array {
            return Announcement::query()
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Announcement $announcement) => [
                    'id' => (string) $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'category' => $announcement->category,
                    'date' => ($announcement->published_at ?? $announcement->created_at)?->toDateString() ?? '',
                    'pinned' => $announcement->is_pinned,
                    'published' => $announcement->is_published,
                    'color' => $announcement->color,
                ])
                ->values()
                ->all();
        });

        return collect($rows);
    }

    /**
     * @return Collection<int, Collection<int, Course>>
     */
    private static function activeCoursesByStage(): Collection
    {
        return Course::query()
            ->where('is_active', true)
            ->get(['id', 'stage_id', 'name', 'subject'])
            ->groupBy('stage_id');
    }

    private static function studentQuery(): Builder
    {
        return Student::query()
            ->with([
                'stage:id,name',
                'courses:id,stage_id,teacher_id,name,subject,price',
                'courses.stage:id,name',
                'courses.teacher:id,name',
                'courses.subjects:id,stage_id,name',
                'courses.subjectTeachers:id,name',
                'parents:id,name,phone',
            ])
            ->withSum('charges as charges_sum', 'amount')
            ->withSum('payments as payments_sum', 'amount')
            ->withCount([
                'charges as charges_count',
                'attendances as attendance_total',
                'attendances as attendance_present' => fn (Builder $query) => $query->whereIn('status', ['present', 'late', 'excused']),
                'grades as grades_total',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapStudent(Student $student): array
    {
        $attrs = $student->getAttributes();
        $totalAttendance = (int) ($student->attendance_total ?? 0);
        $presentAttendance = (int) ($student->attendance_present ?? 0);
        $isEnrolled = $student->courses->isNotEmpty();
        $coursesPrice = (int) $student->courses->sum(fn (Course $course) => (int) $course->price);
        $hasChargeRow = array_key_exists('charges_count', $attrs)
            ? ((int) ($attrs['charges_count'] ?? 0) > 0)
            : $student->charges()->exists();
        $rawChargesSum = array_key_exists('charges_sum', $attrs)
            ? (int) ($attrs['charges_sum'] ?? 0)
            : (int) $student->charges()->sum('amount');
        $totalPaid = array_key_exists('payments_sum', $attrs)
            ? (int) ($attrs['payments_sum'] ?? 0)
            : (int) $student->payments()->sum('amount');

        $isFree = false;
        $hasDiscount = false;

        if (! $isEnrolled) {
            $totalFees = 0;
        } elseif ($hasChargeRow && $rawChargesSum === 0) {
            $totalFees = 0;
            $isFree = true;
        } elseif (! $hasChargeRow) {
            $totalFees = $coursesPrice;
            $isFree = ($coursesPrice === 0);
        } else {
            $totalFees = $rawChargesSum;
            $hasDiscount = ($coursesPrice > 0 && $totalFees < $coursesPrice);
        }

        // مرحلة الطالب ومواده تُشتق من الكورسات المشترك بها فعلياً
        $firstCourseStage = $student->courses->first(fn (Course $c) => $c->stage !== null)?->stage;
        $effectiveStageId = (string) ($firstCourseStage?->id ?? $student->stage_id ?? '');
        $effectiveStageName = $firstCourseStage?->name ?? $student->stage?->name ?? 'غير مشترك بكورس';

        $studentSubjects = collect();
        $mappedCourses = $student->courses->map(function (Course $course) use (&$studentSubjects) {
            $teachersById = $course->subjectTeachers->keyBy('id');
            $cSubjects = $course->subjects->map(function (Subject $sub) use ($teachersById, $course, &$studentSubjects) {
                $tId = $sub->pivot?->teacher_id ?: $course->teacher_id;
                $tName = $tId ? ($teachersById->get($tId)?->name ?? $course->teacher?->name ?? '—') : ($course->teacher?->name ?? '—');
                $studentSubjects->push($sub->name);

                return [
                    'id' => (string) $sub->id,
                    'name' => $sub->name,
                    'teacher_id' => (string) ($tId ?? ''),
                    'teacher_name' => $tName,
                ];
            })->values()->all();

            if (empty($cSubjects) && $course->subject) {
                $studentSubjects->push($course->subject);
            }

            return [
                'id' => (string) $course->id,
                'name' => $course->name,
                'stage_id' => (string) ($course->stage_id ?? ''),
                'stage_name' => $course->stage?->name ?? '—',
                'subject' => $course->subjects->isNotEmpty()
                    ? $course->subjects->pluck('name')->implode(' + ')
                    : $course->subject,
                'subjects' => $cSubjects,
            ];
        })->values()->all();

        return [
            'id' => (string) $student->id,
            'student_no' => $student->student_no,
            'name' => $student->name,
            'parent' => $student->parents->first()?->name ?? $student->guardian_name ?? '',
            'parent_id' => (string) ($student->parents->first()?->id ?? ''),
            'phone' => $student->phone ?? $student->guardian_phone ?? '',
            'stage' => $effectiveStageId,
            'stage_name' => $effectiveStageName,
            'course_ids' => $student->courses
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all(),
            'subjects' => $studentSubjects->filter()->unique()->values()->all(),
            'courses' => $mappedCourses,
            'study_type' => $student->study_type,
            'shift' => $student->shift,
            'is_enrolled' => $isEnrolled,
            'is_free' => $isFree,
            'has_discount' => $hasDiscount,
            'courses_price' => $coursesPrice,
            'fee' => $totalFees,
            'paid' => $totalPaid,
            'notes' => $student->notes ?? '',
            'has_account' => $student->user_id !== null,
            'attendance' => $totalAttendance > 0 ? (int) round(($presentAttendance / $totalAttendance) * 100) : 0,
            'grades_count' => (int) ($student->grades_total ?? 0),
            'registered_at' => $student->enrollment_date?->toDateString() ?? '',
            'status' => match ($student->status) {
                'inactive' => 'غير نشط',
                'graduated' => 'متخرج',
                'suspended' => 'موقوف',
                default => 'نشط',
            },
        ];
    }

    private static function findStudentModel(?string $studentNo): ?Student
    {
        if (! $studentNo) {
            return null;
        }

        return Student::query()
            ->with('stage:id,name')
            ->where('student_no', (string) $studentNo)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<int, int>
     */
    public static function notificationUserIds(array $user): array
    {
        $ids = [(int) ($user['id'] ?? 0)];

        if (($user['role'] ?? null) === 'parent' && ! empty($user['id'])) {
            $children = Student::query()
                ->whereHas('parents', fn (Builder $query) => $query->where('users.id', $user['id']))
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all();

            $ids = array_merge($ids, $children);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * @param  array<int|string, string>  $studentNosByUserId
     * @return array<string, mixed>
     */
    private static function mapNotification(SystemNotification $notification, bool $read, array $studentNosByUserId = []): array
    {
        $audienceRaw = 'all';
        if ($notification->audience_type === 'role') {
            $audienceRaw = $notification->audience_role === 'student' ? 'students' : 'parents';
        } elseif ($notification->audience_type === 'user' && $notification->user_id) {
            $stNo = $studentNosByUserId[$notification->user_id]
                ?? Student::where('user_id', $notification->user_id)->value('student_no');
            if ($stNo) {
                $audienceRaw = (string) $stNo;
            }
        }

        return [
            'id' => (string) $notification->id,
            'title' => $notification->title,
            'body' => $notification->body,
            'audience' => $notification->audience_label,
            'audience_raw' => $audienceRaw,
            'date' => ($notification->created_at ?? now())->toDateString(),
            'read' => $read,
            'color' => match ($notification->audience_type) {
                'stage' => 'amber',
                'user' => 'emerald',
                'role' => 'violet',
                default => 'blue',
            },
        ];
    }

    private static function courseLabel(?string $name): string
    {
        if (! $name) {
            return '';
        }

        return trim((string) preg_replace('/^دورة\s+/u', '', $name));
    }

    private static function formatTimeRange(?string $start, ?string $end): string
    {
        $startLabel = self::formatTime($start);
        $endLabel = self::formatTime($end);

        if ($startLabel === '' && $endLabel === '') {
            return '';
        }

        if ($startLabel === '') {
            return $endLabel;
        }

        if ($endLabel === '') {
            return $startLabel;
        }

        return $startLabel.' – '.$endLabel;
    }

    private static function formatTime(?string $time): string
    {
        if (! $time) {
            return '';
        }

        try {
            $value = Carbon::createFromFormat('H:i:s', $time);
        } catch (\Throwable) {
            try {
                $value = Carbon::parse($time);
            } catch (\Throwable) {
                return '';
            }
        }

        $hour = (int) $value->format('G');
        $suffix = $hour >= 12 ? 'م' : 'ص';
        $hour = $hour % 12;
        $hour = $hour === 0 ? 12 : $hour;

        return $hour.':'.$value->format('i').' '.$suffix;
    }
}

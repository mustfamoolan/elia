@extends('layouts.public')

@section('title', 'حساب ولي الأمر')

@push('styles')
<style>
    .parent-nav-bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        overflow-x: auto;
        padding: 0.375rem;
        background: #ffffff;
        border: 1px solid rgba(15, 29, 58, 0.08);
        border-radius: 1rem;
        box-shadow: 0 4px 18px -6px rgba(15, 29, 58, 0.06);
        margin-bottom: 1rem;
        -webkit-overflow-scrolling: touch;
    }
    .parent-nav-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        padding: 0.6rem 0.95rem;
        border-radius: 0.75rem;
        font-size: 0.8rem;
        font-weight: 800;
        color: rgba(15, 29, 58, 0.7);
        white-space: nowrap;
        transition: all 0.18s ease;
        border: 1px solid transparent;
        background: transparent;
        cursor: pointer;
        text-decoration: none;
    }
    .parent-nav-btn:hover {
        background: rgba(15, 29, 58, 0.04);
        color: #0f1d3a;
    }
    .parent-nav-btn.is-active {
        background: #0f1d3a;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(15, 29, 58, 0.18);
    }
    .parent-nav-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.35rem;
        height: 1.35rem;
        padding: 0 0.35rem;
        border-radius: 999px;
        font-size: 0.68rem;
        font-weight: 800;
        background: rgba(15, 29, 58, 0.08);
        color: #0f1d3a;
    }
    .parent-nav-btn.is-active .parent-nav-badge {
        background: rgba(255, 255, 255, 0.2);
        color: #ffffff;
    }
    .parent-stat-card {
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    }
    .parent-stat-card:hover {
        transform: translateY(-2px);
        border-color: rgba(15, 29, 58, 0.2);
    }
    .parent-panel {
        display: none;
    }
    .parent-panel.is-active {
        display: block;
        animation: parentFadeIn 0.2s ease;
    }
    @keyframes parentFadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endpush

@section('content')
    @php
        $user = session('eyal_user');
        $activeTab = $activeTab ?? request()->query('tab', 'overview');
    @endphp

    <div class="fade-in">
        @if ($notice)
            <div class="mx-auto max-w-lg">
                <div class="card flex flex-col items-center gap-3 p-8 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/5 text-primary">
                        <x-icon name="circle-user" :size="32" />
                    </div>
                    <h1 class="text-xl font-extrabold">حساب ولي الأمر</h1>
                    @if ($notice === 'guest')
                        <p class="text-sm leading-relaxed text-primary/70">سجّل الدخول بحساب ولي الأمر لمتابعة حضور ابنك، درجاته، واجباته، ودفعاته المالية.</p>
                        <div class="mt-2 flex w-full flex-col gap-2 sm:flex-row">
                            <a href="{{ url('/login') }}" class="btn btn-dark flex-1">تسجيل الدخول</a>
                            <a href="{{ url('/register') }}" class="btn btn-outline flex-1">تسجيل طالب</a>
                        </div>
                    @elseif ($notice === 'no_child')
                        <p class="text-sm leading-relaxed text-primary/70">لا يوجد طالب مرتبط بحسابك حالياً. يمكنك إرسال طلب تسجيل جديد لابنك أو التواصل مع الإدارة لربط حسابه.</p>
                        <div class="mt-3 flex w-full flex-col gap-2 sm:flex-row">
                            <button type="button" class="btn btn-dark flex-1" data-modal-open="registerChildModal">
                                <x-icon name="user-plus" :size="16" /> تسجيل ابنك الآن
                            </button>
                            <a href="{{ url('/contact') }}" class="btn btn-outline flex-1">تواصل مع الإدارة</a>
                        </div>
                    @else
                        <p class="text-sm leading-relaxed text-primary/70">
                            أنت مسجّل الدخول بحساب {{ $notice === 'student' ? 'طالب' : 'مدير المعهد' }}. صفحة ولي الأمر متاحة لحسابات أولياء الأمور فقط.
                        </p>
                        <a href="{{ url($notice === 'student' ? '/student' : '/admin') }}" class="btn btn-dark mt-2">الانتقال إلى حسابي</a>
                    @endif
                </div>
            </div>
        @elseif ($child)
            @php
                $attendanceRate = (int) $child['attendance'];
                $totalPaid = (int) $payments->sum('amount');
                $totalFee = (int) $child['fee'];
                $remaining = max(0, $totalFee - $totalPaid);
                $paidPercent = $totalFee > 0 ? min(100, (int) round(($totalPaid / $totalFee) * 100)) : 100;
                $avg = $grades->count() ? (int) round($grades->avg(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;
                $highestGrade = $grades->count() ? (int) round($grades->max(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;

                $presentCount = $attendance->where('status', 'present')->count();
                $lateCount = $attendance->where('status', 'late')->count();
                $absentCount = $attendance->where('status', 'absent')->count();

                $submittedHwCount = $homework->filter(fn ($h) => !empty($h['submission_status']))->count();
                $pendingHwCount = max(0, $homework->count() - $submittedHwCount);
            @endphp

            {{-- 1. شريط اختيار الأبناء مع زر تسجيل ابن جديد --}}
            @if (! empty($children) && $children->count() > 0)
                <div class="card mb-4 p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-primary/5 pb-2.5">
                        <div class="flex items-center gap-2">
                            <x-icon name="users" :size="18" class="text-primary" />
                            <span class="text-xs font-extrabold text-primary">الأبناء المسجلون في حسابك ({{ $children->count() }})</span>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" data-modal-open="registerChildModal">
                            <x-icon name="user-plus" :size="13" /> تسجيل ابن جديد
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-0.5">
                        @foreach ($children as $c)
                            @php
                                $isSelected = $child && ($child['id'] === $c['id']);
                            @endphp
                            <a href="{{ url('/parent?child_id=' . $c['id'] . '&tab=' . $activeTab) }}"
                               data-child-link="{{ $c['id'] }}"
                               class="flex items-center gap-2.5 rounded-xl border px-3.5 py-2 transition-all {{ $isSelected ? 'border-primary bg-primary text-white shadow-sm' : 'border-primary/10 bg-white text-primary hover:border-primary/30' }}">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg {{ $isSelected ? 'bg-white/20 text-white' : 'bg-primary/5 text-primary' }}">
                                    <x-icon name="graduation-cap" :size="16" />
                                </div>
                                <div class="text-right">
                                    <div class="text-xs font-extrabold {{ $isSelected ? 'text-white' : 'text-primary' }}">{{ $c['name'] }}</div>
                                    <div class="text-[10px] {{ $isSelected ? 'text-white/80' : 'text-primary/50' }}">{{ $c['stage_name'] ?? 'طالب' }} • #{{ $c['student_no'] }}</div>
                                </div>
                                @if ($isSelected)
                                    <span class="mr-1 text-xs font-black text-white">✓</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 2. بطاقة تعريف الطالب المختار --}}
            <div class="card mb-4 bg-primary p-5 text-white">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="graduation-cap" :size="30" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-lg font-extrabold text-white">{{ $child['name'] }}</h1>
                            <span class="chip bg-white/15 text-white">{{ $child['stage_name'] }}</span>
                        </div>
                        <div class="mt-0.5 text-xs opacity-80">
                            رقم الطالب: #{{ $child['student_no'] }} • ولي الأمر: {{ $child['parent'] }}
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ url('/chat?student_id=' . $child['id']) }}" class="btn btn-outline btn-sm border-white/30 bg-white/10 text-white hover:bg-white/20">
                            <x-icon name="message-square" :size="14" /> المحادثات والدعم
                        </a>
                        <button type="button" class="btn btn-sm bg-white text-primary hover:bg-white/90" onclick="switchParentTab('homework'); document.getElementById('parentHomeworkModal')?.classList.add('open');">
                            <x-icon name="upload" :size="14" /> رفع واجب
                        </button>
                    </div>
                </div>
            </div>

            {{-- 3. شريط التنقل بين أقسام وصفحات ولي الأمر --}}
            <nav class="parent-nav-bar hide-scrollbar" aria-label="أقسام صفحة ولي الأمر">
                <a href="{{ url('/parent?child_id=' . $child['id'] . '&tab=overview') }}"
                   data-parent-tab="overview"
                   onclick="event.preventDefault(); switchParentTab('overview')"
                   class="parent-nav-btn {{ $activeTab === 'overview' ? 'is-active' : '' }}">
                    <x-icon name="house" :size="16" />
                    <span>نظرة عامة</span>
                </a>

                <a href="{{ url('/parent?child_id=' . $child['id'] . '&tab=homework') }}"
                   data-parent-tab="homework"
                   onclick="event.preventDefault(); switchParentTab('homework')"
                   class="parent-nav-btn {{ $activeTab === 'homework' ? 'is-active' : '' }}">
                    <x-icon name="list-todo" :size="16" />
                    <span>الواجبات</span>
                    <span class="parent-nav-badge">{{ $homework->count() }}</span>
                </a>

                <a href="{{ url('/parent?child_id=' . $child['id'] . '&tab=grades') }}"
                   data-parent-tab="grades"
                   onclick="event.preventDefault(); switchParentTab('grades')"
                   class="parent-nav-btn {{ $activeTab === 'grades' ? 'is-active' : '' }}">
                    <x-icon name="clipboard-list" :size="16" />
                    <span>الدرجات والامتحانات</span>
                    <span class="parent-nav-badge">{{ $grades->count() }}</span>
                </a>

                <a href="{{ url('/parent?child_id=' . $child['id'] . '&tab=attendance') }}"
                   data-parent-tab="attendance"
                   onclick="event.preventDefault(); switchParentTab('attendance')"
                   class="parent-nav-btn {{ $activeTab === 'attendance' ? 'is-active' : '' }}">
                    <x-icon name="calendar-check" :size="16" />
                    <span>الحضور والغياب</span>
                    <span class="parent-nav-badge">{{ $attendanceRate }}%</span>
                </a>

                <a href="{{ url('/parent?child_id=' . $child['id'] . '&tab=payments') }}"
                   data-parent-tab="payments"
                   onclick="event.preventDefault(); switchParentTab('payments')"
                   class="parent-nav-btn {{ $activeTab === 'payments' ? 'is-active' : '' }}">
                    <x-icon name="wallet" :size="16" />
                    <span>المبالغ والدفعات</span>
                </a>
            </nav>

            {{-- =========================================================
                 القسم 1: نظرة عامة (Overview)
            ========================================================= --}}
            <div data-parent-panel="overview" class="parent-panel {{ $activeTab === 'overview' ? 'is-active' : '' }}">
                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="card parent-stat-card p-4" onclick="switchParentTab('homework')">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                                <x-icon name="list-todo" :size="18" />
                            </span>
                            <x-icon name="arrow-left" :size="14" class="text-primary/30" />
                        </div>
                        <div class="text-xl font-extrabold text-primary">{{ $homework->count() }}</div>
                        <div class="text-[11px] font-bold text-primary/60">الواجبات ({{ $pendingHwCount }} بانتظار الحل)</div>
                    </div>

                    <div class="card parent-stat-card p-4" onclick="switchParentTab('grades')">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <x-icon name="clipboard-list" :size="18" />
                            </span>
                            <x-icon name="arrow-left" :size="14" class="text-primary/30" />
                        </div>
                        <div class="text-xl font-extrabold text-primary">{{ $avg }}%</div>
                        <div class="text-[11px] font-bold text-primary/60">معدل الدرجات ({{ $grades->count() }} امتحان)</div>
                    </div>

                    <div class="card parent-stat-card p-4" onclick="switchParentTab('attendance')">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <x-icon name="calendar-check" :size="18" />
                            </span>
                            <x-icon name="arrow-left" :size="14" class="text-primary/30" />
                        </div>
                        <div class="text-xl font-extrabold text-primary">{{ $attendanceRate }}%</div>
                        <div class="text-[11px] font-bold text-primary/60">نسبة الحضور ({{ $presentCount }} حضور)</div>
                    </div>

                    <div class="card parent-stat-card p-4" onclick="switchParentTab('payments')">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                                <x-icon name="wallet" :size="18" />
                            </span>
                            <x-icon name="arrow-left" :size="14" class="text-primary/30" />
                        </div>
                        <div class="text-lg font-extrabold text-primary">{{ number_format($remaining) }} <span class="text-[10px]">د.ع</span></div>
                        <div class="text-[11px] font-bold text-primary/60">المبلغ المتبقي</div>
                    </div>
                </div>

                @if (! empty($child['courses']))
                    <section class="card mb-4 p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="flex items-center gap-2 text-sm font-extrabold">
                                <x-icon name="book-open" :size="17" class="text-primary" /> الدورات والمواد المسجّل بها
                            </h2>
                            <span class="chip bg-primary/5 text-primary/60">{{ count($child['courses']) }} دورة</span>
                        </div>
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            @foreach ($child['courses'] as $cItem)
                                <div class="flex items-center gap-3 rounded-xl border border-primary/8 bg-primary/2 p-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/8 text-primary">
                                        <x-icon name="book-open" :size="18" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-extrabold text-primary">{{ $cItem['name'] }}</div>
                                        @if (!empty($cItem['subject']))
                                            <div class="mt-0.5 text-[11px] text-primary/60">{{ $cItem['subject'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="grid gap-4 lg:grid-cols-2">
                    <section class="card p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="flex items-center gap-2 text-sm font-extrabold">
                                <x-icon name="list-todo" :size="17" class="text-primary" /> أحدث الواجبات
                            </h2>
                            <button type="button" class="btn btn-outline btn-sm" onclick="switchParentTab('homework')">
                                عرض كل الواجبات <x-icon name="arrow-left" :size="13" />
                            </button>
                        </div>
                        @forelse ($homework->take(3) as $item)
                            @php
                                $tone = match ($item['submission_status'] ?? null) {
                                    'submitted' => 'bg-amber-50 text-amber-700',
                                    'under_review' => 'bg-violet-50 text-violet-700',
                                    'reviewed' => 'bg-emerald-50 text-emerald-700',
                                    'needs_revision' => 'bg-rose-50 text-rose-700',
                                    default => 'bg-primary/5 text-primary/60',
                                };
                            @endphp
                            <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-xs font-extrabold text-primary">{{ $item['title'] }}</div>
                                    <div class="text-[10px] text-primary/50">{{ $item['course'] }} • {{ $item['teacher'] }}</div>
                                </div>
                                <span class="chip {{ $tone }}">{{ $item['submission_label'] ?? 'لم يُسلَّم' }}</span>
                            </div>
                        @empty
                            <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد واجبات حالياً</p>
                        @endforelse
                    </section>

                    <section class="card p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <h2 class="flex items-center gap-2 text-sm font-extrabold">
                                <x-icon name="clipboard-list" :size="17" class="text-primary" /> أحدث الدرجات
                            </h2>
                            <button type="button" class="btn btn-outline btn-sm" onclick="switchParentTab('grades')">
                                عرض كل الدرجات <x-icon name="arrow-left" :size="13" />
                            </button>
                        </div>
                        @forelse ($grades->take(3) as $grade)
                            <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                                <div class="min-w-0 flex-1">
                                    <div class="text-xs font-extrabold text-primary">{{ $grade['exam'] }}</div>
                                    <div class="text-[10px] text-primary/50">{{ $grade['course'] }} • {{ \Illuminate\Support\Carbon::parse($grade['date'])->format('Y/m/d') }}</div>
                                </div>
                                <span class="chip bg-emerald-50 text-emerald-700">{{ $grade['score'] }}/{{ $grade['max'] }}</span>
                            </div>
                        @empty
                            <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد درجات مسجلة بعد</p>
                        @endforelse
                    </section>
                </div>
            </div>

            {{-- =========================================================
                 القسم 2: صفحة الواجبات المدرسية (Homework)
            ========================================================= --}}
            <div data-parent-panel="homework" class="parent-panel {{ $activeTab === 'homework' ? 'is-active' : '' }}">
                <div class="card mb-4 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="flex items-center gap-2 text-base font-extrabold text-primary">
                                <x-icon name="list-todo" :size="19" class="text-primary" /> واجبات {{ $child['name'] }}
                            </h2>
                            <p class="mt-0.5 text-xs text-primary/60">متابعة الواجبات المطلوبة، معاينة الصور المرفقة، ورفع الحلول مباشرة لمدرس المادة.</p>
                        </div>
                        <button type="button" class="btn btn-dark btn-sm" data-modal-open="parentHomeworkModal">
                            <x-icon name="upload" :size="15" /> رفع واجب جديد للمدرس
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-primary/5 p-3">
                            <div class="text-base font-extrabold text-primary">{{ $homework->count() }}</div>
                            <div class="text-[10px] font-bold text-primary/60">إجمالي الواجبات</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-3">
                            <div class="text-base font-extrabold text-emerald-700">{{ $submittedHwCount }}</div>
                            <div class="text-[10px] font-bold text-emerald-600">تم التسليم</div>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-3">
                            <div class="text-base font-extrabold text-amber-700">{{ $pendingHwCount }}</div>
                            <div class="text-[10px] font-bold text-amber-600">بانتظار التسليم</div>
                        </div>
                    </div>
                </div>

                @if ($homework->isEmpty())
                    <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📚</div>
                        <p class="text-sm font-bold text-primary/60">لا توجد واجبات مرتبطة بابنك حالياً</p>
                        <button type="button" class="btn btn-outline btn-sm mt-2" data-modal-open="parentHomeworkModal">
                            <x-icon name="upload" :size="14" /> إرسال واجب لمدرس المادة
                        </button>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($homework as $item)
                            @php
                                $submitted = ! empty($item['submission_status']);
                                $tone = match ($item['submission_status'] ?? null) {
                                    'submitted' => 'bg-amber-50 text-amber-700',
                                    'under_review' => 'bg-violet-50 text-violet-700',
                                    'reviewed' => 'bg-emerald-50 text-emerald-700',
                                    'needs_revision' => 'bg-rose-50 text-rose-700',
                                    default => 'bg-primary/5 text-primary/60',
                                };
                            @endphp
                            <article class="card p-5">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-1 flex flex-wrap items-center gap-1.5">
                                            <span class="chip bg-primary/5 text-primary">{{ $item['course'] }}</span>
                                            <span class="chip {{ $tone }}">{{ $item['submission_label'] ?? 'لم يُسلَّم بعد' }}</span>
                                            @if ($item['due'])
                                                <span class="chip bg-blue-50 text-blue-700">
                                                    <x-icon name="calendar" :size="11" /> التسليم: {{ \Illuminate\Support\Carbon::parse($item['due'])->format('Y/m/d') }}
                                                </span>
                                            @endif
                                        </div>
                                        <h3 class="text-sm font-extrabold text-primary">{{ $item['title'] }}</h3>
                                        <div class="mt-0.5 text-[11px] text-primary/50">المدرس: {{ $item['teacher'] }} • {{ $item['stage'] }}</div>
                                    </div>
                                    <button type="button" class="btn btn-dark btn-sm" data-modal-open="parentSubmit{{ $item['id'] }}">
                                        <x-icon name="upload" :size="13" /> {{ $submitted ? 'إعادة رفع الحل' : 'رفع حل الواجب' }}
                                    </button>
                                </div>

                                @if (!empty($item['description']))
                                    <p class="mt-2.5 rounded-xl bg-primary/3 p-3 text-xs leading-relaxed text-primary/75">{{ $item['description'] }}</p>
                                @endif

                                @if (! empty($item['files']))
                                    @php
                                        $hwImages = collect($item['files'])->filter(fn ($f) => !empty($f['is_image']));
                                        $hwDocs = collect($item['files'])->reject(fn ($f) => !empty($f['is_image']));
                                    @endphp
                                    <div class="mt-3">
                                        <div class="mb-1.5 text-[11px] font-extrabold text-primary/60">مرفقات الواجب</div>
                                        @if ($hwImages->isNotEmpty())
                                            <div class="mb-2 grid gap-2.5 sm:grid-cols-2">
                                                @foreach ($hwImages as $file)
                                                    @php
                                                        $inlineUrl = route('homework.download', ['file' => $file['id'], 'inline' => 1]);
                                                        $dlUrl = route('homework.download', $file['id']);
                                                    @endphp
                                                    <div class="overflow-hidden rounded-2xl border border-primary/10 bg-primary/3">
                                                        <button type="button" class="block w-full cursor-pointer overflow-hidden bg-slate-900/5"
                                                                data-src="{{ $inlineUrl }}" data-title="{{ $file['name'] }}" data-dl="{{ $dlUrl }}"
                                                                onclick="openParentHwLightbox(this.dataset.src, this.dataset.title, this.dataset.dl)">
                                                            <img src="{{ $inlineUrl }}" alt="{{ $file['name'] }}" class="mx-auto max-h-64 w-full object-contain" loading="lazy">
                                                        </button>
                                                        <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-[10px]">
                                                            <span class="truncate font-bold text-primary/70">{{ $file['name'] }}</span>
                                                            <a href="{{ $dlUrl }}" class="font-extrabold text-primary hover:underline">تحميل</a>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if ($hwDocs->isNotEmpty())
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($hwDocs as $file)
                                                    <a href="{{ route('homework.download', $file['id']) }}" class="chip bg-primary/5 text-primary">
                                                        <x-icon name="download" :size="11" /> {{ $file['name'] }} ({{ $file['size'] }})
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if (! empty($item['submission_files']))
                                    @php
                                        $subImages = collect($item['submission_files'])->filter(fn ($f) => !empty($f['is_image']));
                                        $subDocs = collect($item['submission_files'])->reject(fn ($f) => !empty($f['is_image']));
                                    @endphp
                                    <div class="mt-3 border-t border-primary/5 pt-2.5">
                                        <div class="mb-1.5 text-[11px] font-extrabold text-emerald-700">الحل المرفوع من قبلكم</div>
                                        @if ($subImages->isNotEmpty())
                                            <div class="mb-2 grid gap-2.5 sm:grid-cols-2">
                                                @foreach ($subImages as $file)
                                                    @php
                                                        $inlineUrl = route('homework.download', ['file' => $file['id'], 'inline' => 1]);
                                                        $dlUrl = route('homework.download', $file['id']);
                                                    @endphp
                                                    <div class="overflow-hidden rounded-2xl border border-emerald-500/20 bg-emerald-50/30">
                                                        <button type="button" class="block w-full cursor-pointer overflow-hidden bg-slate-900/5"
                                                                data-src="{{ $inlineUrl }}" data-title="{{ $file['name'] }}" data-dl="{{ $dlUrl }}"
                                                                onclick="openParentHwLightbox(this.dataset.src, this.dataset.title, this.dataset.dl)">
                                                            <img src="{{ $inlineUrl }}" alt="{{ $file['name'] }}" class="mx-auto max-h-64 w-full object-contain" loading="lazy">
                                                        </button>
                                                        <div class="flex items-center justify-between gap-2 px-3 py-1.5 text-[10px]">
                                                            <span class="truncate font-bold text-emerald-800">{{ $file['name'] }}</span>
                                                            <a href="{{ $dlUrl }}" class="font-extrabold text-emerald-700 hover:underline">تحميل</a>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if ($subDocs->isNotEmpty())
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($subDocs as $file)
                                                    <a href="{{ route('homework.download', $file['id']) }}" class="chip bg-emerald-50 text-emerald-700">
                                                        <x-icon name="file-text" :size="11" /> {{ $file['name'] }}
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if (! empty($item['feedback']))
                                    <div class="mt-3 rounded-xl bg-amber-50 px-3.5 py-2.5 text-xs font-bold text-amber-800">
                                        💬 ملاحظات المدرس: {{ $item['feedback'] }}
                                    </div>
                                @endif
                            </article>

                            <div class="modal-overlay" id="parentSubmit{{ $item['id'] }}">
                                <div class="modal-panel modal-md">
                                    <div class="mb-4 flex items-center justify-between">
                                        <h3 class="text-lg font-extrabold">رفع واجب: {{ $item['title'] }}</h3>
                                        <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                                            <x-icon name="x" :size="20" class="text-primary" />
                                        </button>
                                    </div>
                                    <form class="space-y-3" method="POST" action="{{ route('homework.submit', $item['id']) }}" enctype="multipart/form-data">
                                        @csrf
                                        <input type="hidden" name="student_id" value="{{ $child['id'] }}">
                                        <div>
                                            <label class="label">ملف أو صورة الواجب *</label>
                                            <input type="file" name="file" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" required>
                                            <p class="mt-1 text-[10px] text-primary/50">PDF أو صورة أو Word — الحد الأقصى 10 ميغابايت.</p>
                                        </div>
                                        <div>
                                            <label class="label">ملاحظة للمدرس (اختياري)</label>
                                            <textarea name="note" class="input min-h-[80px]" placeholder="اكتب ملاحظة للمدرس..."></textarea>
                                        </div>
                                        <div class="flex gap-2 pt-1">
                                            <button type="submit" class="btn btn-dark flex-1">
                                                <x-icon name="send" :size="16" /> إرسال للمدرس
                                            </button>
                                            <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- =========================================================
                 القسم 3: صفحة الدرجات والامتحانات (Grades)
            ========================================================= --}}
            <div data-parent-panel="grades" class="parent-panel {{ $activeTab === 'grades' ? 'is-active' : '' }}">
                <div class="card mb-4 p-5">
                    <h2 class="flex items-center gap-2 text-base font-extrabold text-primary">
                        <x-icon name="clipboard-list" :size="19" class="text-primary" /> سجل درجات وامتحانات {{ $child['name'] }}
                    </h2>
                    <p class="mt-0.5 text-xs text-primary/60">جميع نتائج الامتحانات الشهرية واليومية المسجلة للطالب من قبل الكادر التدريسي.</p>

                    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-primary/5 p-3">
                            <div class="text-base font-extrabold text-primary">{{ $avg }}%</div>
                            <div class="text-[10px] font-bold text-primary/60">المعدل العام</div>
                        </div>
                        <div class="rounded-xl bg-blue-50 p-3">
                            <div class="text-base font-extrabold text-blue-700">{{ $grades->count() }}</div>
                            <div class="text-[10px] font-bold text-blue-600">عدد الامتحانات</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-3">
                            <div class="text-base font-extrabold text-emerald-700">{{ $highestGrade }}%</div>
                            <div class="text-[10px] font-bold text-emerald-600">أعلى نسبة</div>
                        </div>
                    </div>
                </div>

                <section class="card p-5">
                    @forelse ($grades as $grade)
                        @php
                            $pct = (int) round(($grade['score'] / max(1, $grade['max'])) * 100);
                            $badgeTone = $pct >= 85 ? 'bg-emerald-50 text-emerald-700' : ($pct >= 70 ? 'bg-blue-50 text-blue-700' : ($pct >= 50 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700'));
                            $ratingLabel = $pct >= 90 ? 'ممتاز' : ($pct >= 80 ? 'جيد جداً' : ($pct >= 65 ? 'جيد' : ($pct >= 50 ? 'مقبول' : 'يحتاج متابعة')));
                        @endphp
                        <div class="border-b border-primary/5 py-3.5 first:pt-0 last:border-0 last:pb-0">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-extrabold text-primary">{{ $grade['exam'] }}</div>
                                    <div class="mt-0.5 text-[11px] text-primary/60">
                                        {{ $grade['course'] }} • {{ \Illuminate\Support\Carbon::parse($grade['date'])->format('Y/m/d') }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="chip {{ $badgeTone }}">{{ $ratingLabel }}</span>
                                    <span class="chip bg-primary text-white font-extrabold">{{ $grade['score'] }} / {{ $grade['max'] }}</span>
                                </div>
                            </div>
                            <div class="mt-2">
                                <div class="progress">
                                    <span style="width: {{ min(100, $pct) }}%"></span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="py-10 text-center text-xs font-bold text-primary/50">لا توجد درجات مسجلة لهذا الطالب بعد</p>
                    @endforelse
                </section>
            </div>

            {{-- =========================================================
                 القسم 4: صفحة الحضور والغياب (Attendance)
            ========================================================= --}}
            <div data-parent-panel="attendance" class="parent-panel {{ $activeTab === 'attendance' ? 'is-active' : '' }}">
                <div class="card mb-4 p-5">
                    <h2 class="flex items-center gap-2 text-base font-extrabold text-primary">
                        <x-icon name="calendar-check" :size="19" class="text-primary" /> سجل الحضور والغياب — {{ $child['name'] }}
                    </h2>
                    <p class="mt-0.5 text-xs text-primary/60">متابعة يومية لحضور الطالب في المحاضرات والدورات التدريبية.</p>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-center sm:grid-cols-4">
                        <div class="rounded-xl bg-primary/5 p-3">
                            <div class="text-base font-extrabold text-primary">{{ $attendanceRate }}%</div>
                            <div class="text-[10px] font-bold text-primary/60">نسبة الالتزام</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50 p-3">
                            <div class="text-base font-extrabold text-emerald-700">{{ $presentCount }}</div>
                            <div class="text-[10px] font-bold text-emerald-600">أيام الحضور</div>
                        </div>
                        <div class="rounded-xl bg-amber-50 p-3">
                            <div class="text-base font-extrabold text-amber-700">{{ $lateCount }}</div>
                            <div class="text-[10px] font-bold text-amber-600">أيام التأخير</div>
                        </div>
                        <div class="rounded-xl bg-rose-50 p-3">
                            <div class="text-base font-extrabold text-rose-700">{{ $absentCount }}</div>
                            <div class="text-[10px] font-bold text-rose-600">أيام الغياب</div>
                        </div>
                    </div>
                </div>

                <section class="card p-5">
                    @forelse ($attendance as $record)
                        @php
                            $tone = $record['status'] === 'present' ? 'bg-emerald-50 text-emerald-700' : ($record['status'] === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                            $label = $record['status'] === 'present' ? 'حاضر' : ($record['status'] === 'late' ? 'متأخر' : 'غائب');
                        @endphp
                        <div class="flex items-center gap-3 border-b border-primary/5 py-3 first:pt-0 last:border-0 last:pb-0">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $tone }}">
                                <x-icon name="calendar-check" :size="16" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">{{ $record['subject'] }}</div>
                                <div class="text-[11px] text-primary/50">{{ \Illuminate\Support\Carbon::parse($record['date'])->format('Y/m/d') }}</div>
                            </div>
                            <span class="chip {{ $tone }}">{{ $label }}</span>
                        </div>
                    @empty
                        <p class="py-10 text-center text-xs font-bold text-primary/50">لا يوجد سجل حضور مسجل بعد</p>
                    @endforelse
                </section>
            </div>

            {{-- =========================================================
                 القسم 5: صفحة المبالغ والدفعات المالية (Payments)
            ========================================================= --}}
            <div data-parent-panel="payments" class="parent-panel {{ $activeTab === 'payments' ? 'is-active' : '' }}">
                <div class="card mb-4 p-5">
                    <h2 class="flex items-center gap-2 text-base font-extrabold text-primary">
                        <x-icon name="wallet" :size="19" class="text-primary" /> كشف الحساب والدفعات المالية — {{ $child['name'] }}
                    </h2>
                    <p class="mt-0.5 text-xs text-primary/60">تفاصيل الأقساط الدراسية والمبالغ المسددة والذمم المتبقية ووصولات القبض.</p>

                    <div class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                        <div class="rounded-2xl bg-primary/5 p-4 text-center">
                            <div class="text-base font-extrabold text-primary">{{ number_format($totalFee) }} <span class="text-[10px]">د.ع</span></div>
                            <div class="mt-0.5 text-[11px] font-bold text-primary/60">إجمالي قيمة الاشتراك</div>
                        </div>
                        <div class="rounded-2xl bg-emerald-50 p-4 text-center">
                            <div class="text-base font-extrabold text-emerald-700">{{ number_format($totalPaid) }} <span class="text-[10px]">د.ع</span></div>
                            <div class="mt-0.5 text-[11px] font-bold text-emerald-600">إجمالي المبلغ المسدد ({{ $paidPercent }}%)</div>
                        </div>
                        <div class="rounded-2xl bg-rose-50 p-4 text-center">
                            <div class="text-base font-extrabold text-rose-700">{{ number_format($remaining) }} <span class="text-[10px]">د.ع</span></div>
                            <div class="mt-0.5 text-[11px] font-bold text-rose-600">المبلغ المتبقي</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="mb-1 flex items-center justify-between text-[11px] font-bold text-primary/60">
                            <span>نسبة إكمال القسط الدراسي</span>
                            <span>{{ $paidPercent }}%</span>
                        </div>
                        <div class="progress">
                            <span style="width: {{ $paidPercent }}%"></span>
                        </div>
                    </div>
                </div>

                <section class="card p-5">
                    <h3 class="mb-3 text-sm font-extrabold text-primary">سجل الوصولات والدفعات ({{ $payments->count() }})</h3>
                    @forelse ($payments as $payment)
                        <div class="flex flex-wrap items-center gap-3 border-b border-primary/5 py-3 first:pt-0 last:border-0 last:pb-0">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                <x-icon name="wallet" :size="18" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-extrabold text-primary">{{ number_format($payment['amount']) }} د.ع</div>
                                <div class="text-[11px] text-primary/60">
                                    طريقة الدفع: {{ $payment['method'] }} • التاريخ: {{ \Illuminate\Support\Carbon::parse($payment['date'])->format('Y/m/d') }}
                                    @if (!empty($payment['note']))
                                        • {{ $payment['note'] }}
                                    @endif
                                </div>
                            </div>
                            <span class="chip bg-emerald-50 text-emerald-700 font-extrabold">وصل {{ $payment['receipt'] }}</span>
                        </div>
                    @empty
                        <p class="py-10 text-center text-xs font-bold text-primary/50">لا توجد دفعات مسجلة حتى الآن</p>
                    @endforelse
                </section>
            </div>

            {{-- نافذة رفع واجب جديد للمدرس --}}
            <div class="modal-overlay" id="parentHomeworkModal">
                <div class="modal-panel modal-md">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-extrabold">رفع واجب جديد للمدرس</h3>
                        <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                            <x-icon name="x" :size="20" class="text-primary" />
                        </button>
                    </div>
                    <form class="space-y-3" method="POST" action="{{ route('parent.homework.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $child['id'] }}">
                        <div>
                            <label class="label">الدورة *</label>
                            <select name="course_id" class="input" required>
                                <option value="">— اختر الدورة —</option>
                                @foreach ($child['courses'] as $course)
                                    <option value="{{ $course['id'] }}">{{ $course['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[10px] text-primary/50">يُرسل الواجب تلقائياً إلى مدرس الدورة المختارة.</p>
                        </div>
                        <div>
                            <label class="label">عنوان الواجب *</label>
                            <input name="title" class="input" placeholder="مثال: حل تمارين الفصل الثاني" required>
                        </div>
                        <div>
                            <label class="label">وصف الواجب</label>
                            <textarea name="description" class="input min-h-[70px]" placeholder="تفاصيل إضافية للمدرس..."></textarea>
                        </div>
                        <div>
                            <label class="label">تاريخ التسليم</label>
                            <input type="date" name="due_date" class="input">
                        </div>
                        <div>
                            <label class="label">ملف أو صورة الواجب *</label>
                            <input type="file" name="file" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" required>
                            <p class="mt-1 text-[10px] text-primary/50">PDF أو صورة أو Word — الحد الأقصى 10 ميغابايت.</p>
                        </div>
                        <div>
                            <label class="label">ملاحظة (اختياري)</label>
                            <textarea name="note" class="input min-h-[70px]" placeholder="ملاحظة للمدرس..."></textarea>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button type="submit" class="btn btn-dark flex-1">
                                <x-icon name="send" :size="16" /> إرسال للمدرس
                            </button>
                            <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- نافذة تكبير صور الواجبات --}}
            <div class="modal-overlay" id="parentHwLightboxModal" onclick="if(event.target===this) closeParentHwLightbox()">
                <div class="modal-panel modal-lg" style="max-width: 54rem;">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 id="parentHwLightboxTitle" class="truncate text-sm font-extrabold text-primary">معاينة صورة الواجب</h3>
                        <div class="flex items-center gap-2">
                            <a id="parentHwLightboxDl" href="#" class="btn btn-outline btn-sm">
                                <x-icon name="download" :size="14" /> تحميل
                            </a>
                            <button type="button" class="rounded-full p-2 hover:bg-primary/5" onclick="closeParentHwLightbox()" aria-label="إغلاق">
                                <x-icon name="x" :size="20" class="text-primary" />
                            </button>
                        </div>
                    </div>
                    <div class="flex max-h-[75vh] items-center justify-center overflow-auto rounded-2xl bg-slate-950/5 p-2">
                        <img id="parentHwLightboxImg" src="" alt="صورة الواجب" class="max-h-[72vh] w-auto max-w-full rounded-xl object-contain">
                    </div>
                </div>
            </div>
        @endif

        {{-- نافذة تسجيل ابن جديد لولي الأمر --}}
        <div class="modal-overlay" id="registerChildModal">
            <div class="modal-panel modal-md">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-extrabold">تسجيل ابن جديد في المعهد</h3>
                    <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                        <x-icon name="x" :size="20" class="text-primary" />
                    </button>
                </div>
                <form class="space-y-3" method="POST" action="{{ route('register.store') }}">
                    @csrf
                    <input type="hidden" name="parent_name" value="{{ $user['name'] ?? '' }}">
                    <input type="hidden" name="phone" value="{{ $user['phone'] ?? '' }}">

                    <div class="rounded-xl bg-primary/5 p-3 text-xs leading-relaxed text-primary/75">
                        سيتم إرسال طلب تسجيل جديد باسم ولي الأمر: <strong>{{ $user['name'] ?? '' }}</strong>، ورقم الهاتف: <strong>{{ $user['phone'] ?? '' }}</strong>. وعند موافقة إدارة المعهد على الطلب، سيتم ربط الابن بحسابك تلقائياً ليظهر في قائمة أبنائك.
                    </div>

                    <div>
                        <label class="label">اسم الطالب الثلاثي *</label>
                        <input name="student_name" class="input" placeholder="اسم الابن / الابنة كاملاً" required>
                    </div>

                    <div>
                        <label class="label">المرحلة الدراسية *</label>
                        <select name="stage" class="input" required>
                            <option value="">— اختر المرحلة الدراسية —</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="label">ملاحظات أو مواد تقوية مطلوبة (اختياري)</label>
                        <textarea name="notes" class="input min-h-[70px]" placeholder="مثال: بحاجة لتقوية بمادة الرياضيات واللغة الإنجليزية..."></textarea>
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="btn btn-dark flex-1">
                            <x-icon name="send" :size="16" /> إرسال طلب التسجيل
                        </button>
                        <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function switchParentTab(tabName) {
        document.querySelectorAll('[data-parent-tab]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-parent-tab') === tabName);
        });
        document.querySelectorAll('[data-parent-panel]').forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-parent-panel') === tabName);
        });
        document.querySelectorAll('[data-child-link]').forEach(function (link) {
            var childId = link.getAttribute('data-child-link');
            link.setAttribute('href', '/parent?child_id=' + encodeURIComponent(childId) + '&tab=' + encodeURIComponent(tabName));
        });
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url.toString());
        }
    }

    function openParentHwLightbox(src, title, dl) {
        var modal = document.getElementById('parentHwLightboxModal');
        var img = document.getElementById('parentHwLightboxImg');
        var titleEl = document.getElementById('parentHwLightboxTitle');
        var dlEl = document.getElementById('parentHwLightboxDl');
        if (!modal || !img) return;
        img.src = src;
        if (titleEl) titleEl.textContent = title || 'معاينة صورة الواجب';
        if (dlEl) dlEl.href = dl || src;
        modal.classList.add('open');
    }

    function closeParentHwLightbox() {
        var modal = document.getElementById('parentHwLightboxModal');
        var img = document.getElementById('parentHwLightboxImg');
        if (modal) modal.classList.remove('open');
        if (img) img.src = '';
    }
</script>
@endpush

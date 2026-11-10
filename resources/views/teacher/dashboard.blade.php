@extends('layouts.teacher')

@section('title', 'لوحة المعلومات')

@section('content')
    @php
        $cards = [
            ['book-open', $courses->count(), 'دوراتي'],
            ['users', $studentsCount, 'طلابي'],
            ['list-todo', $pendingHomework, 'واجبات بحاجة للمتابعة'],
            ['calendar-check', $todaySlots->count(), 'حصص اليوم'],
        ];
        $statusMap = [
            'open' => ['مفتوح', 'bg-blue-50 text-blue-700'],
            'submitted' => ['بانتظار المراجعة', 'bg-amber-50 text-amber-700'],
            'under_review' => ['قيد المراجعة', 'bg-violet-50 text-violet-700'],
            'reviewed' => ['تمت المراجعة', 'bg-emerald-50 text-emerald-700'],
            'needs_revision' => ['يحتاج تعديل', 'bg-rose-50 text-rose-700'],
            'closed' => ['مغلق', 'bg-primary/5 text-primary/60'],
        ];
    @endphp

    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-extrabold">لوحة المعلومات</h1>
            <span class="chip bg-primary/5 text-primary/60">مرحباً، {{ $user->name }}</span>
            @if ($user->staff?->subject)
                <span class="chip bg-blue-50 text-blue-700">{{ $user->staff->subject }}</span>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($cards as [$icon, $value, $label])
                <div class="card flex items-center gap-3 p-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary-light">
                        <x-icon :name="$icon" :size="22" />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xl font-extrabold text-primary">{{ $value }}</div>
                        <div class="text-xs font-bold text-primary/60">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-sm font-extrabold">حصص اليوم</h2>
                    <span class="mr-auto chip bg-primary/5 text-primary/60">{{ now()->locale('ar')->translatedFormat('l') }}</span>
                </div>

                @forelse ($todaySlots as $slot)
                    <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <x-icon name="clock" :size="17" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-primary">{{ $slot->course?->name ?? $slot->course_name }}</div>
                            <div class="text-[10px] text-primary/50">
                                {{ substr($slot->start_time, 0, 5) }} – {{ substr($slot->end_time, 0, 5) }}
                                @if ($slot->hall) • {{ $slot->hall }} @endif
                            </div>
                        </div>
                        @if ($slot->stage_name)
                            <span class="chip bg-primary/5 text-primary/60">{{ $slot->stage_name }}</span>
                        @endif
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">☕</div>
                        <p class="text-xs font-bold text-primary/60">لا توجد حصص مجدولة اليوم</p>
                    </div>
                @endforelse
            </section>

            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-sm font-extrabold">أحدث الواجبات</h2>
                </div>

                @forelse ($homework as $task)
                    @php [$label, $tone] = $statusMap[$task->status] ?? [$task->status, 'bg-primary/5 text-primary/60']; @endphp
                    <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <x-icon name="list-todo" :size="17" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-primary">{{ $task->title }}</div>
                            <div class="text-[10px] text-primary/50">
                                {{ $task->course?->name }}
                                @if ($task->due_date) • التسليم {{ $task->due_date->format('Y-m-d') }} @endif
                            </div>
                        </div>
                        <span class="chip {{ $tone }}">{{ $label }}</span>
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">📚</div>
                        <p class="text-xs font-bold text-primary/60">لا توجد واجبات بعد</p>
                    </div>
                @endforelse
            </section>
        </div>

        <section class="card mt-4 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-primary/5 px-5 py-3">
                <h2 class="text-sm font-extrabold">دوراتي وطلابها</h2>
            </div>

            @if ($courses->isEmpty())
                <div class="flex flex-col items-center gap-2 py-10 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">📖</div>
                    <p class="text-xs font-bold text-primary/60">لم يتم تعيين أي دورة لك بعد. راجع إدارة المعهد.</p>
                </div>
            @else
                <div class="table-wrap" style="border:0;border-radius:0">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>الدورة</th>
                            <th>المرحلة</th>
                            <th>الطلاب</th>
                            <th>الجدول</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($courses as $course)
                            <tr>
                                <td>
                                    <div class="font-extrabold text-primary">{{ $course->name }}</div>
                                    <div class="text-[10px] text-primary/50">{{ $course->subject }}</div>
                                </td>
                                <td>{{ $course->stage?->name ?? '—' }}</td>
                                <td>{{ $course->students_count }}</td>
                                <td class="text-[11px] text-primary/60">{{ $course->schedule_text ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card mt-4 p-5">
            <div class="mb-3 flex items-center gap-2">
                <h2 class="text-sm font-extrabold">أحدث التسليمات</h2>
            </div>

            @forelse ($recentSubmissions as $submission)
                <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                        <x-icon name="file-text" :size="17" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-xs font-extrabold text-primary">{{ $submission->student?->name ?? 'طالب' }}</div>
                        <div class="text-[10px] text-primary/50">{{ $submission->task?->title }} • {{ $submission->task?->course?->name }}</div>
                    </div>
                    <span class="chip bg-primary/5 text-primary/60">{{ $submission->status_label }}</span>
                </div>
            @empty
                <div class="flex flex-col items-center gap-2 py-6 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">📥</div>
                    <p class="text-xs font-bold text-primary/60">لا توجد تسليمات جديدة</p>
                </div>
            @endforelse
        </section>
    </div>
@endsection

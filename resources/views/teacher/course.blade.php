@extends('layouts.teacher')

@section('title', $course->name)

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <a href="{{ route('teacher.courses') }}" class="btn btn-outline btn-sm">
                <x-icon name="arrow-right" :size="14" /> رجوع
            </a>
            <h1 class="text-2xl font-extrabold">{{ $course->name }}</h1>
            <span class="chip bg-primary/5 text-primary/60">{{ $course->subject }}</span>
            @if ($course->stage)
                <span class="chip bg-blue-50 text-blue-700">{{ $course->stage->name }}</span>
            @endif
        </div>

        <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="card p-3 text-center">
                <div class="text-lg font-extrabold text-primary">{{ $course->students->count() }}</div>
                <div class="text-[11px] font-bold text-primary/60">الطلاب</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-lg font-extrabold text-primary">{{ $course->scheduleSlots->count() }}</div>
                <div class="text-[11px] font-bold text-primary/60">الحصص الأسبوعية</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-lg font-extrabold text-primary">{{ $course->homeworkTasks->count() }}</div>
                <div class="text-[11px] font-bold text-primary/60">الواجبات</div>
            </div>
            <div class="card p-3 text-center">
                <div class="text-lg font-extrabold text-primary">{{ number_format((int) $course->price) }}</div>
                <div class="text-[11px] font-bold text-primary/60">الاشتراك (د.ع)</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-primary/5 px-5 py-3">
                    <h2 class="text-sm font-extrabold">طلاب الدورة</h2>
                    <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $course->students->count() }}</span>
                </div>
                @if ($course->students->isEmpty())
                    <p class="px-5 py-8 text-center text-xs font-bold text-primary/50">لا يوجد طلاب مسجلون في هذه الدورة</p>
                @else
                    <div class="table-wrap" style="border:0;border-radius:0">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>الرقم</th>
                                <th>الهاتف</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($course->students as $student)
                                <tr>
                                    <td>
                                        <div class="font-extrabold text-primary">{{ $student->name }}</div>
                                        <div class="text-[10px] text-primary/50">{{ $student->stage?->name ?? '—' }}</div>
                                    </td>
                                    <td class="text-[11px] text-primary/60">{{ $student->student_no }}</td>
                                    <td class="text-[11px] text-primary/60" dir="ltr">{{ $student->phone ?: '—' }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="card p-5">
                <h2 class="mb-3 text-sm font-extrabold">جدول الدورة</h2>
                @forelse ($course->scheduleSlots as $slot)
                    <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <x-icon name="clock" :size="17" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">{{ $slot->day_name }}</div>
                            <div class="text-[10px] text-primary/50">{{ substr($slot->start_time, 0, 5) }} – {{ substr($slot->end_time, 0, 5) }}</div>
                        </div>
                        @if ($slot->hall)
                            <span class="chip bg-primary/5 text-primary/60">{{ $slot->hall }}</span>
                        @endif
                    </div>
                @empty
                    <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد حصص مجدولة</p>
                @endforelse
            </section>
        </div>

        <section class="card mt-4 p-5">
            <div class="mb-3 flex items-center gap-2">
                <h2 class="text-sm font-extrabold">أحدث واجبات الدورة</h2>
                <a href="{{ route('teacher.homework', ['course' => $course->id]) }}" class="btn btn-outline btn-sm mr-auto">
                    <x-icon name="arrow-left" :size="13" /> كل الواجبات
                </a>
            </div>
            @forelse ($course->homeworkTasks as $task)
                <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <x-icon name="list-todo" :size="17" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-xs font-extrabold text-primary">{{ $task->title }}</div>
                        <div class="text-[10px] text-primary/50">
                            {{ $task->source_label }}
                            @if ($task->due_date) • التسليم {{ $task->due_date->format('Y-m-d') }} @endif
                        </div>
                    </div>
                    <a href="{{ route('teacher.homework.show', $task) }}" class="btn btn-outline btn-sm">
                        <x-icon name="eye" :size="12" /> مراجعة
                    </a>
                </div>
            @empty
                <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد واجبات بعد</p>
            @endforelse
        </section>
    </div>
@endsection

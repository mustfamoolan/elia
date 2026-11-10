@extends('layouts.teacher')

@section('title', 'دوراتي')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">دوراتي</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $courses->count() }} دورة</span>
        </div>

        @if ($courses->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📖</div>
                <p class="text-sm font-bold text-primary/60">لم يتم تعيين أي دورة لك بعد. راجع إدارة المعهد.</p>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($courses as $course)
                    <article class="card flex flex-col p-5">
                        <div class="mb-2 flex items-start gap-2">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                                <x-icon name="book-open" :size="20" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-sm font-extrabold text-primary">{{ $course->name }}</h2>
                                <div class="text-[11px] text-primary/50">{{ $course->subject }}</div>
                            </div>
                            @if ($course->stage)
                                <span class="chip bg-primary/5 text-primary/60">{{ $course->stage->name }}</span>
                            @endif
                        </div>

                        <div class="my-3 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-xl bg-primary/5 p-2">
                                <div class="text-sm font-extrabold text-primary">{{ $course->students_count }}</div>
                                <div class="text-[10px] text-primary/50">طالب</div>
                            </div>
                            <div class="rounded-xl bg-blue-50 p-2">
                                <div class="text-sm font-extrabold text-blue-700">{{ $course->homework_tasks_count }}</div>
                                <div class="text-[10px] text-blue-600">واجب</div>
                            </div>
                        </div>

                        <div class="mb-3 text-[11px] text-primary/60">
                            <x-icon name="calendar" :size="12" class="inline" /> {{ $course->schedule_text ?: 'لا يوجد جدول محدد' }}
                        </div>

                        <a href="{{ route('teacher.courses.show', $course) }}" class="btn btn-dark btn-sm mt-auto">
                            <x-icon name="eye" :size="13" /> عرض الدورة
                        </a>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

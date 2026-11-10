@extends('layouts.teacher')

@section('title', $task->title)

@section('content')
    @php
        $statusMap = [
            'open' => ['مفتوح', 'bg-blue-50 text-blue-700'],
            'submitted' => ['بانتظار المراجعة', 'bg-amber-50 text-amber-700'],
            'under_review' => ['قيد المراجعة', 'bg-violet-50 text-violet-700'],
            'reviewed' => ['تمت المراجعة', 'bg-emerald-50 text-emerald-700'],
            'needs_revision' => ['يحتاج تعديل', 'bg-rose-50 text-rose-700'],
            'closed' => ['مغلق', 'bg-primary/5 text-primary/60'],
        ];
        [$taskLabel, $taskTone] = $statusMap[$task->status] ?? [$task->status, 'bg-primary/5 text-primary/60'];
        $submittedIds = $task->submissions->pluck('student_id');
        $missing = $task->is_class_task
            ? $task->course?->students->whereNotIn('id', $submittedIds) ?? collect()
            : collect();
        if (! $task->is_class_task && $task->student && ! $submittedIds->contains($task->student->id)) {
            $missing = collect([$task->student]);
        }
    @endphp

    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <a href="{{ route('teacher.homework') }}" class="btn btn-outline btn-sm">
                <x-icon name="arrow-right" :size="14" /> رجوع
            </a>
            <h1 class="text-2xl font-extrabold">{{ $task->title }}</h1>
            <span class="chip {{ $taskTone }}">{{ $taskLabel }}</span>
            <span class="chip bg-primary/5 text-primary/60">{{ $task->source_label }}</span>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card p-5 lg:col-span-2">
                <div class="mb-2 flex flex-wrap items-center gap-2 text-[11px] font-bold text-primary/50">
                    <span class="flex items-center gap-1"><x-icon name="book-open" :size="13" /> {{ $task->course?->name ?? '—' }}</span>
                    @if ($task->course?->stage)
                        <span class="flex items-center gap-1"><x-icon name="layers" :size="13" /> {{ $task->course->stage->name }}</span>
                    @endif
                    @if ($task->due_date)
                        <span class="flex items-center gap-1"><x-icon name="calendar" :size="13" /> التسليم {{ $task->due_date->format('Y/m/d') }}</span>
                    @endif
                </div>

                <p class="text-sm leading-relaxed text-primary/70">{{ $task->description ?: 'لا يوجد وصف لهذا الواجب.' }}</p>

                @if ($task->files->isNotEmpty())
                    <div class="mt-3">
                        <div class="mb-1 text-[11px] font-extrabold text-primary/60">ملفات الواجب</div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($task->files as $file)
                                <a href="{{ route('homework.download', $file) }}" class="chip bg-primary/5 text-primary">
                                    <x-icon name="download" :size="11" /> {{ $file->original_name }} ({{ $file->human_size }})
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <section class="card p-5">
                <h2 class="mb-3 text-sm font-extrabold">ملخص التسليمات</h2>
                <div class="space-y-2">
                    <div class="flex items-center justify-between rounded-xl bg-primary/5 px-3 py-2">
                        <span class="text-[11px] font-bold text-primary/60">عدد التسليمات</span>
                        <span class="text-sm font-extrabold text-primary">{{ $task->submissions->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-amber-50 px-3 py-2">
                        <span class="text-[11px] font-bold text-amber-700">بانتظار المراجعة</span>
                        <span class="text-sm font-extrabold text-amber-700">{{ $task->submissions->whereIn('status', ['submitted', 'under_review'])->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-rose-50 px-3 py-2">
                        <span class="text-[11px] font-bold text-rose-700">لم يُسلَّم</span>
                        <span class="text-sm font-extrabold text-rose-700">{{ $missing->count() }}</span>
                    </div>
                </div>
            </section>
        </div>

        <section class="card mt-4 p-5">
            <h2 class="mb-3 text-sm font-extrabold">التسليمات والمراجعة</h2>

            @forelse ($task->submissions as $submission)
                @php [$sLabel, $sTone] = $statusMap[$submission->status] ?? [$submission->status_label, 'bg-primary/5 text-primary/60']; @endphp
                <div class="mb-3 rounded-xl border border-primary/5 p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                            <x-icon name="user-round" :size="16" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">{{ $submission->student?->name ?? 'طالب' }}</div>
                            <div class="text-[10px] text-primary/50">
                                رفع بواسطة {{ $submission->submitter?->name ?? '—' }}
                                • {{ $submission->created_at?->format('Y/m/d H:i') }}
                            </div>
                        </div>
                        <span class="chip {{ $sTone }}">{{ $sLabel }}</span>
                    </div>

                    @if ($submission->note)
                        <p class="mt-2 rounded-lg bg-primary/3 px-2 py-1.5 text-[11px] text-primary/70">{{ $submission->note }}</p>
                    @endif

                    @if ($submission->files->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($submission->files as $file)
                                <a href="{{ route('homework.download', $file) }}" class="chip bg-emerald-50 text-emerald-700">
                                    <x-icon name="file-text" :size="11" /> {{ $file->original_name }} ({{ $file->human_size }})
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('teacher.homework.review', [$task, $submission]) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-primary/5 pt-3">
                        @csrf
                        <div class="min-w-[10rem]">
                            <label class="label">حالة المراجعة</label>
                            <select name="status" class="input">
                                <option value="under_review" @selected($submission->status === 'under_review')>قيد المراجعة</option>
                                <option value="reviewed" @selected($submission->status === 'reviewed')>تمت المراجعة</option>
                                <option value="needs_revision" @selected($submission->status === 'needs_revision')>يحتاج تعديل</option>
                            </select>
                        </div>
                        <div class="min-w-[14rem] flex-1">
                            <label class="label">ملاحظات للطالب وولي الأمر</label>
                            <input name="feedback" class="input" value="{{ $submission->feedback }}" placeholder="ملاحظات اختيارية...">
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm">
                            <x-icon name="check" :size="13" /> حفظ المراجعة
                        </button>
                    </form>
                </div>
            @empty
                <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد تسليمات لهذا الواجب بعد</p>
            @endforelse
        </section>

        @if ($missing->isNotEmpty())
            <section class="card mt-4 p-5">
                <h2 class="mb-3 text-sm font-extrabold">طلاب لم يسلّموا بعد</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($missing as $student)
                        <span class="chip bg-rose-50 text-rose-700">{{ $student->name }}</span>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection

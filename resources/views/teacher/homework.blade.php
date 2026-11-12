@extends('layouts.teacher')

@section('title', 'الواجبات')

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
    @endphp

    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الواجبات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $tasks->count() }} واجب</span>
            <button type="button" class="btn btn-dark btn-sm" data-modal-open="homeworkCreateModal">
                <x-icon name="plus" :size="14" /> واجب جديد
            </button>
        </div>

        <form method="GET" action="{{ route('teacher.homework') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[12rem] flex-1">
                <label class="label">الدورة</label>
                <select name="course" class="input">
                    <option value="">كل الدورات</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected((int) $courseFilter === $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[10rem]">
                <label class="label">الحالة</label>
                <select name="status" class="input">
                    <option value="">كل الحالات</option>
                    @foreach ($statusMap as $value => [$label])
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">
                <x-icon name="filter" :size="13" /> تصفية
            </button>
        </form>

        @if ($tasks->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📚</div>
                <p class="text-sm font-bold text-primary/60">لا توجد واجبات مطابقة</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($tasks as $task)
                    @php [$label, $tone] = $statusMap[$task->status] ?? [$task->status, 'bg-primary/5 text-primary/60']; @endphp
                    <article class="card p-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                                <x-icon name="list-todo" :size="19" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-sm font-extrabold text-primary">{{ $task->title }}</h2>
                                <div class="text-[10px] text-primary/50">
                                    {{ $task->course?->name }} • {{ $task->source_label }}
                                    @if ($task->student) • {{ $task->student->name }} @endif
                                    @if ($task->due_date) • التسليم {{ $task->due_date->format('Y/m/d') }} @endif
                                </div>
                            </div>
                            <span class="chip {{ $tone }}">{{ $label }}</span>
                            @if ($task->pending_submissions > 0)
                                <span class="chip bg-amber-50 text-amber-700">{{ $task->pending_submissions }} بانتظار المراجعة</span>
                            @endif
                            <span class="chip bg-primary/5 text-primary/60">{{ $task->submissions_count }} تسليم</span>
                            <a href="{{ route('teacher.homework.show', $task) }}" class="btn btn-outline btn-sm">
                                <x-icon name="eye" :size="12" /> عرض
                            </a>
                            <button type="button" class="btn btn-outline btn-sm"
                                    data-modal="homeworkEditModal"
                                    data-edit="{{ json_encode([
                                        'action' => route('teacher.homework.update', $task),
                                        'title' => $task->title,
                                        'description' => $task->description,
                                        'due_date' => $task->due_date?->format('Y-m-d'),
                                        'color' => $task->color,
                                        'status' => $task->status,
                                    ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" /> تعديل
                            </button>
                            <form method="POST" action="{{ route('teacher.homework.destroy', $task) }}" onsubmit="return confirm('هل تريد حذف هذا الواجب؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" title="حذف الواجب">
                                    <x-icon name="trash-2" :size="12" />
                                </button>
                            </form>
                        </div>

                        @if ($task->files->isNotEmpty())
                            @php
                                $taskImages = $task->files->filter(fn ($f) => $f->is_image);
                                $taskDocs = $task->files->reject(fn ($f) => $f->is_image);
                            @endphp

                            @if ($taskImages->isNotEmpty())
                                <div class="mt-3 flex flex-wrap gap-2.5">
                                    @foreach ($taskImages as $file)
                                        @php $inlineUrl = route('homework.download', [$file, 'inline' => 1]); @endphp
                                        <div class="overflow-hidden rounded-xl border border-primary/10 bg-primary/3 p-1.5">
                                            <a href="{{ route('teacher.homework.show', $task) }}" class="block overflow-hidden rounded-lg bg-slate-900/5">
                                                <img src="{{ $inlineUrl }}" alt="{{ $file->original_name }}"
                                                     class="h-28 w-40 object-cover transition hover:scale-105" loading="lazy">
                                            </a>
                                            <div class="mt-1 flex items-center justify-between gap-2 text-[10px]">
                                                <span class="max-w-[7rem] truncate font-bold text-primary/70">{{ $file->original_name }}</span>
                                                <a href="{{ route('homework.download', $file) }}" class="font-extrabold text-primary hover:underline">تحميل</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($taskDocs->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($taskDocs as $file)
                                        <a href="{{ route('homework.download', $file) }}" class="chip bg-primary/5 text-primary">
                                            <x-icon name="download" :size="11" /> {{ $file->original_name }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <div class="modal-overlay" id="homeworkCreateModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold">واجب جديد لكل طلاب الدورة</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('teacher.homework.store') }}" enctype="multipart/form-data">
                @csrf
                <div>
                    <label class="label">الدورة *</label>
                    <select name="course_id" class="input" required>
                        <option value="">— اختر الدورة —</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}">{{ $course->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">عنوان الواجب *</label>
                    <input name="title" class="input" placeholder="مثال: حل تمارين الفصل الثاني" required>
                </div>
                <div>
                    <label class="label">وصف الواجب</label>
                    <textarea name="description" class="input min-h-[70px]" placeholder="تفاصيل الواجب للطلاب..."></textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">تاريخ التسليم</label>
                        <input type="date" name="due_date" class="input">
                    </div>
                    <div>
                        <label class="label">اللون</label>
                        <select name="color" class="input">
                            <option value="blue">أزرق</option>
                            <option value="violet">بنفسجي</option>
                            <option value="emerald">أخضر</option>
                            <option value="amber">ذهبي</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">ملفات مرفقة (اختياري)</label>
                    <input type="file" name="files[]" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" multiple>
                    <p class="mt-1 text-[10px] text-primary/50">حتى 5 ملفات — PDF أو صورة أو Word — 10 ميغابايت لكل ملف.</p>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="send" :size="16" /> نشر الواجب
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="homeworkEditModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold">تعديل الواجب</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="">
                @csrf
                @method('PUT')
                <div>
                    <label class="label">عنوان الواجب *</label>
                    <input name="title" class="input" required>
                </div>
                <div>
                    <label class="label">وصف الواجب</label>
                    <textarea name="description" class="input min-h-[70px]"></textarea>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label">تاريخ التسليم</label>
                        <input type="date" name="due_date" class="input">
                    </div>
                    <div>
                        <label class="label">الحالة</label>
                        <select name="status" class="input">
                            @foreach ($statusMap as $value => [$label])
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">اللون</label>
                        <select name="color" class="input">
                            <option value="blue">أزرق</option>
                            <option value="violet">بنفسجي</option>
                            <option value="emerald">أخضر</option>
                            <option value="amber">ذهبي</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ التعديلات
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

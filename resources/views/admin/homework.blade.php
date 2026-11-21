@extends('layouts.admin')

@section('title', 'الواجبات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الواجبات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $homework->count() }} واجب</span>
            <button class="btn btn-dark btn-sm" data-modal-open="homeworkModal" data-add="homeworkModal" data-add-title="إضافة واجب">
                <x-icon name="plus" :size="14" /> إضافة واجب
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-paginate="9">
            @foreach ($homework as $item)
                <div class="card p-4">
                    <div class="mb-2 flex items-start gap-2">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                            <x-icon name="list-todo" :size="18" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-extrabold text-primary">{{ $item['title'] }}</div>
                            <div class="text-[11px] text-primary/50">
                                {{ $item['course'] }}
                                @if (!empty($item['subject_name'])) • {{ $item['subject_name'] }} @endif
                                • {{ $item['stage'] }}
                            </div>
                        </div>
                    </div>
                    <p class="mb-3 text-[11px] leading-relaxed text-primary/60">{{ $item['description'] }}</p>

                    @if (!empty($item['files']))
                        <div class="mb-3 space-y-2">
                            @foreach ($item['files'] as $file)
                                @if (!empty($file['is_image']))
                                    @php $inlineUrl = route('homework.download', [$file['id'], 'inline' => 1]); @endphp
                                    <div class="overflow-hidden rounded-xl border border-primary/10 bg-primary/3 p-1.5">
                                        <a href="{{ $inlineUrl }}" target="_blank" class="block overflow-hidden rounded-lg bg-slate-900/5">
                                            <img src="{{ $inlineUrl }}" alt="{{ $file['name'] }}" class="h-32 w-full object-cover transition hover:scale-105" loading="lazy">
                                        </a>
                                        <div class="mt-1 flex items-center justify-between gap-2 text-[10px]">
                                            <span class="truncate font-bold text-primary/70">{{ $file['name'] }}</span>
                                            <a href="{{ route('homework.download', $file['id']) }}" class="font-extrabold text-primary hover:underline">تحميل الصورة</a>
                                        </div>
                                    </div>
                                @else
                                    <a href="{{ route('homework.download', $file['id']) }}" class="chip bg-primary/5 text-primary">
                                        <x-icon name="download" :size="11" /> {{ $file['name'] }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-bold text-primary/50">
                        <span class="chip bg-primary/5 text-primary">أ. {{ $item['teacher'] }}</span>
                        @if (!empty($item['student_name']))
                            <span class="chip bg-violet-50 text-violet-700">الطالب: {{ $item['student_name'] }}</span>
                        @endif
                        @if (!empty($item['source_label']))
                            <span class="chip bg-emerald-50 text-emerald-700">{{ $item['source_label'] }}</span>
                        @endif
                        @if (!empty($item['due']))
                            <span class="chip bg-amber-50 text-amber-700">
                                <x-icon name="calendar" :size="11" /> {{ $item['due'] }}
                            </span>
                        @endif
                    </div>
                    <div class="mt-3 flex items-center gap-1 border-t border-primary/5 pt-3">
                        <button class="btn btn-outline btn-sm" data-modal="homeworkModal" data-edit="{{ json_encode([
                            'action' => route('admin.homework.update', $item['id']),
                            'modal_title' => 'تعديل الواجب',
                            'title' => $item['title'],
                            'course' => $item['course_id'],
                            'due' => $item['due'],
                            'description' => $item['description'],
                        ], JSON_UNESCAPED_UNICODE) }}">
                            <x-icon name="pencil" :size="12" /> تعديل
                        </button>
                        <form method="POST" action="{{ route('admin.homework.destroy', $item['id']) }}" class="inline mr-auto" onsubmit="return confirm('هل تريد حذف هذا الواجب؟')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit">
                                <x-icon name="trash-2" :size="12" />
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="homeworkModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة واجب</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.homework.store') }}" data-store-action="{{ route('admin.homework.store') }}">
                @csrf
                <div>
                    <label class="label">عنوان الواجب *</label>
                    <input name="title" class="input" placeholder="مثال: حل تمارين الفصل الثالث" required>
                </div>
                <div>
                    <label class="label">المادة</label>
                    <select name="course" class="input" required>
                        <option value="">— اختر المادة —</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course['id'] }}">{{ $course['name'] }} — {{ $course['stage_name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">تاريخ التسليم</label>
                    <input name="due" type="date" class="input" value="{{ now()->addDays(7)->toDateString() }}">
                </div>
                <div>
                    <label class="label">تفاصيل الواجب</label>
                    <textarea name="description" class="input min-h-[90px]" placeholder="اشرح المطلوب من الطالب..."></textarea>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'الدورات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الدورات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $courses->count() }} دورة</span>
            <button class="btn btn-dark btn-sm" data-modal-open="courseModal" data-add="courseModal" data-add-title="إضافة دورة">
                <x-icon name="plus" :size="14" /> إضافة دورة
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($courses as $course)
                <div class="card course-card flex flex-col p-4">
                    <div class="mb-2 flex items-start gap-2">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                            <x-icon name="book-open" :size="20" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-extrabold text-primary">{{ $course['name'] }}</div>
                            <div class="text-[11px] text-primary/50">{{ $course['stage_name'] }} • {{ $course['teacher'] }}</div>
                        </div>
                        <span class="chip {{ $course['active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $course['active'] ? 'نشطة' : 'متوقفة' }}
                        </span>
                    </div>
                    <p class="mb-3 text-[11px] leading-relaxed text-primary/60">{{ $course['description'] }}</p>
                    <div class="space-y-1 text-[11px] text-primary/60">
                        <div class="flex items-center gap-1.5"><x-icon name="calendar-days" :size="12" /> {{ $course['schedule'] }}</div>
                        <div class="flex items-center gap-1.5"><x-icon name="users" :size="12" /> {{ $course['enrolled'] }} / {{ $course['seats'] }} مقعد</div>
                    </div>
                    <div class="mt-2"><div class="progress"><span style="width: {{ (int) round(($course['enrolled'] / max(1, $course['seats'])) * 100) }}%"></span></div></div>
                    <div class="mt-3 flex items-center gap-1 border-t border-primary/5 pt-3">
                        <div class="text-sm font-extrabold text-primary">{{ number_format($course['price']) }} <span class="text-[10px] text-primary/50">د.ع</span></div>
                        <div class="mr-auto flex items-center gap-1">
                            <button class="btn btn-outline btn-sm" data-modal="courseModal" data-edit="{{ json_encode([
                                'action' => route('admin.courses.update', $course['id']),
                                'modal_title' => 'تعديل الدورة',
                                'name' => $course['name'],
                                'stage' => $course['stage'],
                                'teacher_id' => $course['teacher_id'],
                                'subject' => $course['subject'],
                                'price' => $course['price'],
                                'seats' => $course['seats'],
                                'schedule' => $course['schedule'],
                                'description' => $course['description'],
                                'active' => $course['active'],
                            ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" />
                            </button>
                            <form method="POST" action="{{ route('admin.courses.destroy', $course['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف {{ $course['name'] }}؟')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm" type="submit">
                                    <x-icon name="trash-2" :size="12" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="courseModal">
        <div class="modal-panel modal-lg">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة دورة</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.courses.store') }}" data-store-action="{{ route('admin.courses.store') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">اسم الدورة *</label>
                        <input name="name" class="input" placeholder="مثال: دورة الرياضيات" required>
                    </div>
                    <div>
                        <label class="label">المرحلة</label>
                        <select name="stage" class="input">
                            <option value="">— اختر —</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">المدرس</label>
                        <select name="teacher_id" class="input">
                            <option value="">— بدون مدرس —</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher['user_id'] }}">{{ $teacher['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">المادة</label>
                        <input name="subject" class="input" placeholder="مثال: رياضيات">
                    </div>
                    <div>
                        <label class="label">السعر (د.ع)</label>
                        <input name="price" type="number" min="0" step="1000" class="input" placeholder="150000">
                    </div>
                    <div>
                        <label class="label">عدد المقاعد</label>
                        <input name="seats" type="number" min="1" class="input" placeholder="24">
                    </div>
                    <div>
                        <label class="label">الجدول</label>
                        <input name="schedule" class="input" placeholder="الأحد – الثلاثاء · 4:00 م">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">وصف الدورة</label>
                        <textarea name="description" class="input min-h-[80px]" placeholder="وصف مختصر للدورة..."></textarea>
                    </div>
                </div>
                <label class="flex items-center gap-2 rounded-xl bg-primary/4 p-3">
                    <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-primary/20">
                    <span class="text-xs font-extrabold text-primary">الدورة نشطة ومتاحة للتسجيل</span>
                </label>
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

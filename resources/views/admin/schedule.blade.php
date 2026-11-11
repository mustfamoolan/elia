@extends('layouts.admin')

@section('title', 'الجدول الدراسي')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الجدول الدراسي</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ collect($schedule)->flatten(1)->count() }} موعد</span>
            <button class="btn btn-dark btn-sm" data-modal-open="scheduleModal" data-add="scheduleModal" data-add-title="إضافة موعد">
                <x-icon name="plus" :size="14" /> إضافة موعد
            </button>
        </div>

        <div class="space-y-3">
            @foreach ($days as $day)
                @php $items = $schedule->get($day, collect()); @endphp
                <section class="card overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-primary/5 bg-primary/4 px-4 py-2.5">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary text-[10px] font-extrabold text-white">{{ $loop->iteration }}</span>
                        <span class="text-sm font-extrabold text-primary">{{ $day }}</span>
                        @if ($day === 'الجمعة')
                            <span class="chip bg-slate-100 text-slate-600 mr-auto">عطلة</span>
                        @else
                            <span class="chip bg-white text-primary/60 mr-auto">{{ $items->count() }} موعد</span>
                        @endif
                    </div>
                    @if ($items->isEmpty())
                        <div class="px-4 py-4 text-center text-[11px] font-bold text-primary/40">لا توجد مواعيد</div>
                    @else
                        <div class="divide-y divide-primary/5">
                            @foreach ($items as $item)
                                @php
                                    $editSchedulePayload = [
                                        'action' => route('admin.schedule.update', $item['id']),
                                        'modal_title' => 'تعديل الموعد الدراسي',
                                        'day' => $item['day'],
                                        'course' => $item['course_id'],
                                        'hall' => $item['hall'] ?? '',
                                        'time' => $item['time'] ?? '',
                                        'teacher' => $item['teacher'] === 'الكادر التدريسي' ? '' : ($item['teacher'] ?? ''),
                                    ];
                                @endphp
                                <div class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                                    <span class="chip bg-primary/5 text-primary">{{ $item['time'] }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-extrabold text-primary">{{ $item['course'] }}</div>
                                        <div class="text-[10px] text-primary/50">{{ $item['stage'] }} • {{ $item['teacher'] }} • {{ $item['hall'] }}</div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button type="button" class="btn btn-outline btn-sm" data-modal="scheduleModal" data-edit="{{ json_encode($editSchedulePayload, JSON_UNESCAPED_UNICODE) }}">
                                            <x-icon name="pencil" :size="12" /> تعديل
                                        </button>
                                        <form method="POST" action="{{ route('admin.schedule.destroy', $item['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف هذا الموعد؟')">
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
                    @endif
                </section>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="scheduleModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة موعد</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.schedule.store') }}" data-store-action="{{ route('admin.schedule.store') }}">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">اليوم</label>
                        <select name="day" class="input">
                            @foreach ($days as $day)
                                <option value="{{ $day }}">{{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">القاعة</label>
                        <input name="hall" class="input" placeholder="قاعة 1">
                    </div>
                </div>
                <div>
                    <label class="label">الدورة / المادة *</label>
                    <select name="course" class="input" required>
                        <option value="">— اختر الدورة —</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course['id'] }}">{{ $course['name'] }} — {{ $course['stage_name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">الوقت</label>
                        <input name="time" class="input" placeholder="4:00 م – 6:00 م">
                    </div>
                    <div>
                        <label class="label">المدرس</label>
                        <input name="teacher" class="input" placeholder="اسم المدرس">
                    </div>
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

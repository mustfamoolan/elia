@extends('layouts.admin')

@section('title', 'طلبات التسجيل')

@section('content')
    @php
        $statusMap = [
            'new' => ['جديدة', 'bg-amber-50 text-amber-700'],
            'accepted' => ['مقبولة', 'bg-emerald-50 text-emerald-700'],
            'rejected' => ['مرفوضة', 'bg-rose-50 text-rose-700'],
        ];
    @endphp

    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">طلبات التسجيل</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $counts['all'] }} طلب</span>
        </div>

        <div class="hide-scrollbar mb-4 flex gap-2 overflow-x-auto" data-tabs="#registrationsList">
            <button type="button" class="chip whitespace-nowrap bg-primary text-white shadow-soft" data-tab-value="all">الكل ({{ $counts['all'] }})</button>
            <button type="button" class="chip whitespace-nowrap bg-white text-primary" data-tab-value="new">جديدة ({{ $counts['new'] }})</button>
            <button type="button" class="chip whitespace-nowrap bg-white text-primary" data-tab-value="accepted">مقبولة ({{ $counts['accepted'] }})</button>
            <button type="button" class="chip whitespace-nowrap bg-white text-primary" data-tab-value="rejected">مرفوضة ({{ $counts['rejected'] }})</button>
        </div>

        <div id="registrationsList" class="space-y-2">
            @forelse ($registrations as $item)
                @php
                    [$label, $tone] = $statusMap[$item['status']] ?? ['—', 'bg-primary/5 text-primary'];
                    $editRegPayload = [
                        'action' => route('admin.registrations.update', $item['id']),
                        'modal_title' => 'تعديل طلب التسجيل',
                        'student_name' => $item['name'],
                        'parent_name' => $item['parent'] ?? '',
                        'phone' => $item['phone'] ?? '',
                        'stage_id' => $item['stage_id'] ?? '',
                        'course_id' => $item['course_id'] ?? '',
                        'status' => $item['status'],
                        'note' => $item['notes'] ?? '',
                    ];
                @endphp
                <div class="card p-4" data-status="{{ $item['status'] }}">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                            <x-icon name="user-plus" :size="20" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-extrabold text-primary">{{ $item['name'] }}</span>
                                <span class="chip {{ $tone }}">{{ $label }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-primary/60">
                                ولي الأمر: {{ $item['parent'] }} • {{ $item['phone'] }} • {{ $item['stage'] }} • {{ $item['course'] ?: 'عام' }}
                            </div>
                            @if ($item['notes'])
                                <div class="mt-1 text-[11px] text-primary/50">ملاحظات: {{ $item['notes'] }}</div>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="hidden text-[10px] font-bold text-primary/40 sm:block">{{ $item['date'] }}</span>
                            <a href="https://wa.me/964{{ ltrim($item['phone'], '0') }}" target="_blank" rel="noopener" class="btn btn-wa btn-sm" title="مراسلة واتساب">
                                <x-icon name="whatsapp" :size="13" />
                            </a>
                            @if ($item['status'] === 'new')
                                <form method="POST" action="{{ route('admin.registrations.status', [$item['id'], 'accepted']) }}" class="inline" onsubmit="return confirm('هل تريد قبول الطلب وإنشاء ملف الطالب؟')">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-primary btn-sm" type="submit">
                                        <x-icon name="check" :size="13" /> قبول
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.registrations.status', [$item['id'], 'rejected']) }}" class="inline" onsubmit="return confirm('هل تريد رفض الطلب؟')">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-danger btn-sm" type="submit">
                                        <x-icon name="x" :size="13" /> رفض
                                    </button>
                                </form>
                            @endif
                            <button type="button" class="btn btn-outline btn-sm" data-modal="registrationModal" data-edit="{{ json_encode($editRegPayload, JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" /> تعديل
                            </button>
                            <form method="POST" action="{{ route('admin.registrations.destroy', $item['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف طلب التسجيل؟')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline btn-sm" type="submit" title="حذف الطلب">
                                    <x-icon name="trash-2" :size="13" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                    <p class="text-sm font-bold text-primary/60">لا توجد طلبات تسجيل</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="modal-overlay" id="registrationModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>تعديل طلب التسجيل</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">اسم الطالب *</label>
                        <input name="student_name" class="input" required>
                    </div>
                    <div>
                        <label class="label">اسم ولي الأمر</label>
                        <input name="parent_name" class="input">
                    </div>
                    <div>
                        <label class="label">رقم الهاتف *</label>
                        <input name="phone" class="input" inputmode="tel" required>
                    </div>
                    <div>
                        <label class="label">حالة الطلب</label>
                        <select name="status" class="input">
                            <option value="new">جديدة (قيد المراجعة)</option>
                            <option value="accepted">مقبولة</option>
                            <option value="rejected">مرفوضة</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">المرحلة الدراسية</label>
                        <select name="stage_id" class="input">
                            <option value="">— اختر المرحلة —</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">الدورة / المادة</label>
                        <select name="course_id" class="input">
                            <option value="">— عام / بدون تحديد —</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course['id'] }}">{{ $course['name'] }} ({{ $course['stage_name'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">ملاحظات</label>
                    <textarea name="note" class="input min-h-[80px]"></textarea>
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

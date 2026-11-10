@extends('layouts.admin')

@section('title', 'إدارة الطلاب')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">إدارة الطلاب</h1>
            <button class="btn btn-dark mr-auto" data-modal-open="studentModal" data-add="studentModal" data-add-title="إضافة طالب">
                <x-icon name="plus" :size="16" />
                <span> إضافة طالب</span>
            </button>
        </div>

        <div class="mb-4">
            <input class="input" placeholder="بحث بالاسم أو الهاتف..." data-filter-input="#studentsList" data-filter-empty="#studentsEmpty">
        </div>

        <div id="studentsList" class="space-y-2">
            @foreach ($students as $student)
                @php
                    $remaining = \App\Support\Eyal::remaining($student);
                    $editPayload = [
                        'action' => route('admin.students.update', $student['id']),
                        'modal_title' => 'تعديل بيانات الطالب',
                        'student_name' => $student['name'],
                        'parent_name' => $student['parent'],
                        'parent_id' => $student['parent_id'],
                        'phone' => $student['phone'],
                        'stage' => $student['stage'],
                        'subjects' => implode('، ', $student['subjects']),
                        'study_type' => $student['study_type'],
                        'shift' => $student['shift'],
                        'fee' => $student['fee'],
                        'notes' => $student['notes'],
                        'has_account' => $student['has_account'],
                    ];
                @endphp
                <div class="card flex flex-wrap items-center gap-3 p-4" data-filter-item
                     data-search="{{ $student['name'].' '.$student['student_no'].' '.$student['phone'].' '.$student['parent'].' '.$student['stage_name'] }}">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-extrabold text-primary">{{ $student['name'] }}</span>
                            @if ($student['has_account'])
                                <span class="chip bg-emerald-50 text-emerald-700">لديه حساب</span>
                            @endif
                            <span class="chip bg-primary/5 text-primary/60">{{ $student['stage_name'] }}</span>
                        </div>
                        <div class="mt-0.5 text-xs text-primary/60">
                            <span>رقم الطالب: </span><b>{{ $student['student_no'] }}</b>
                            <span> • ولي الأمر: </span>{{ $student['parent'] }}
                            <span> • </span>{{ $student['phone'] }}
                            <span> • </span>{{ $student['study_type'] }}
                        </div>
                        <div class="mt-1 text-[11px] text-primary/60">المواد: {{ implode('، ', $student['subjects']) }}</div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <span class="chip bg-primary/5 text-primary">الاشتراك: {{ number_format($student['fee']) }} د.ع</span>
                            @if ($remaining > 0)
                                <span class="chip bg-rose-50 text-rose-600">المتبقي: {{ number_format($remaining) }} د.ع</span>
                            @else
                                <span class="chip bg-emerald-50 text-emerald-700">مكتمل الدفع</span>
                            @endif
                            <span class="chip bg-slate-100 text-slate-600">حضور {{ $student['attendance'] }}%</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <a href="{{ url('/admin/payments') }}" class="btn btn-outline btn-sm" title="الحسابات">
                            <x-icon name="wallet" :size="13" />
                        </a>
                        <button class="btn btn-outline btn-sm" data-modal="studentModal" data-edit="{{ json_encode($editPayload, JSON_UNESCAPED_UNICODE) }}">
                            <x-icon name="pencil" :size="13" /> تعديل
                        </button>
                        <form method="POST" action="{{ route('admin.students.destroy', $student['id']) }}" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف الطالب {{ $student['name'] }}؟')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit">
                                <x-icon name="trash-2" :size="13" />
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div id="studentsEmpty" class="is-hidden card flex flex-col items-center gap-2 px-6 py-10 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
            <p class="text-sm font-bold text-primary/60">لا توجد نتائج مطابقة</p>
        </div>
    </div>

    <div class="modal-overlay" id="studentModal">
        <div class="modal-panel modal-lg">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة طالب</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.students.store') }}" data-store-action="{{ route('admin.students.store') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">اسم الطالب الكامل *</label>
                        <input name="student_name" class="input" placeholder="مثال: أحمد محمد علي" required>
                    </div>
                    <div>
                        <label class="label">اسم ولي الأمر</label>
                        <input name="parent_name" class="input" placeholder="اسم ولي الأمر">
                    </div>
                    <div>
                        <label class="label">حساب ولي الأمر المرتبط</label>
                        <select name="parent_id" class="input">
                            <option value="">— بدون حساب —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}{{ $parent->phone ? ' — '.$parent->phone : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">رقم الهاتف</label>
                        <input name="phone" class="input" placeholder="07XXXXXXXXX" inputmode="tel">
                    </div>
                    <div>
                        <label class="label">المرحلة الدراسية</label>
                        <select name="stage" class="input">
                            <option value="">— اختر —</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">المادة أو المواد (مفصولة بفاصلة)</label>
                        <input name="subjects" class="input" placeholder="مثال: رياضيات، فيزياء">
                    </div>
                    <div>
                        <label class="label">نوع الدراسة</label>
                        <select name="study_type" class="input">
                            <option value="دروس تقوية">دروس تقوية</option>
                            <option value="منصة (أونلاين)">منصة (أونلاين)</option>
                            <option value="مفرد (درس خاص)">مفرد (درس خاص)</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">الفترة</label>
                        <select name="shift" class="input">
                            <option value="مسائي">مسائي</option>
                            <option value="صباحي">صباحي</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">قيمة الاشتراك (د.ع)</label>
                        <input name="fee" type="number" min="0" step="1000" class="input" placeholder="150000">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">ملاحظات</label>
                        <textarea name="notes" class="input min-h-[80px]" placeholder="ملاحظات إضافية..."></textarea>
                    </div>
                </div>

                <div class="rounded-xl bg-primary/4 p-3">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="has_account" value="1" class="h-4 w-4 rounded border-primary/20">
                        <span class="text-xs font-extrabold text-primary">إنشاء حساب دخول للطالب</span>
                    </label>
                    <p class="mt-1 pr-6 text-[10px] leading-relaxed text-primary/50">
                        اسم المستخدم للطالب هو رقم الطالب (يُنشأ تلقائياً عند الحفظ). يمكن أيضاً تسجيل الدخول برقم الهاتف.
                    </p>
                    <div class="mt-2">
                        <input name="password" type="password" class="input" placeholder="كلمة المرور (اختياري)">
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

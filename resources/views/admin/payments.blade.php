@extends('layouts.admin')

@section('title', 'الحسابات والدفعات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الحسابات والدفعات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $payments->count() }} دفعة</span>
            <button class="btn btn-dark btn-sm" data-modal-open="paymentModal" data-add="paymentModal" data-add-title="تسجيل دفعة">
                <x-icon name="plus" :size="14" /> تسجيل دفعة
            </button>
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card p-4">
                <div class="text-lg font-extrabold text-primary">{{ number_format($stats['fees']) }}</div>
                <div class="text-[11px] font-bold text-primary/60">قيمة الاشتراكات (د.ع)</div>
            </div>
            <div class="card p-4">
                <div class="text-lg font-extrabold text-emerald-600">{{ number_format($stats['paid']) }}</div>
                <div class="text-[11px] font-bold text-primary/60">المدفوع (د.ع)</div>
            </div>
            <div class="card p-4">
                <div class="text-lg font-extrabold text-rose-600">{{ number_format($stats['remaining']) }}</div>
                <div class="text-[11px] font-bold text-primary/60">المتبقي (د.ع)</div>
            </div>
            <div class="card p-4">
                <div class="text-lg font-extrabold text-primary">{{ number_format($stats['payments_total']) }}</div>
                <div class="text-[11px] font-bold text-primary/60">إجمالي الدفعات (د.ع)</div>
            </div>
        </div>

        <div class="mb-4">
            <input class="input" placeholder="بحث بالطالب أو رقم الإيصال..." data-filter-input="#paymentsList" data-filter-empty="#paymentsEmpty">
        </div>

        <section class="card overflow-hidden">
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>المبلغ</th>
                        <th>طريقة الدفع</th>
                        <th>الإيصال</th>
                        <th>التاريخ</th>
                        <th>ملاحظة</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="paymentsList">
                    @foreach ($payments as $payment)
                        <tr data-filter-item data-search="{{ $payment['student'].' '.$payment['student_no'].' '.$payment['receipt'].' '.$payment['method'] }}">
                            <td>
                                <div class="font-extrabold text-primary">{{ $payment['student'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ $payment['student_no'] }}</div>
                            </td>
                            <td class="font-extrabold text-emerald-600">{{ number_format($payment['amount']) }} د.ع</td>
                            <td><span class="chip bg-primary/5 text-primary">{{ $payment['method'] }}</span></td>
                            <td class="text-[11px] text-primary/60">{{ $payment['receipt'] }}</td>
                            <td class="text-[11px] text-primary/50">{{ $payment['date'] }}</td>
                            <td class="text-[11px] text-primary/50">{{ $payment['note'] ?: '—' }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <a href="{{ route('admin.payments.receipt', $payment['id']) }}" target="_blank" class="btn btn-outline btn-sm" title="طباعة الإيصال">
                                        <x-icon name="receipt" :size="12" />
                                    </a>
                                    <form method="POST" action="{{ route('admin.payments.destroy', $payment['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف هذه الدفعة؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit">
                                            <x-icon name="trash-2" :size="12" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div id="paymentsEmpty" class="is-hidden py-6 text-center text-xs font-bold text-primary/50">لا توجد نتائج مطابقة</div>
        </section>

        <section class="card mt-4 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-primary/5 px-5 py-3">
                <h2 class="text-sm font-extrabold">ملخص حسابات الطلاب</h2>
            </div>
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>الاشتراك</th>
                        <th>المدفوع</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($students as $student)
                        @php $remaining = \App\Support\Eyal::remaining($student); @endphp
                        <tr>
                            <td>
                                <div class="font-extrabold text-primary">{{ $student['name'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ $student['student_no'] }} • {{ $student['stage_name'] }}</div>
                            </td>
                            <td>{{ number_format($student['fee']) }}</td>
                            <td class="text-emerald-600">{{ number_format($student['paid']) }}</td>
                            <td class="{{ $remaining > 0 ? 'text-rose-600' : 'text-emerald-600' }}">{{ number_format($remaining) }}</td>
                            <td>
                                <span class="chip {{ $remaining > 0 ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700' }}">
                                    {{ $remaining > 0 ? 'عليه مبلغ' : 'مكتمل' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal-overlay" id="paymentModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>تسجيل دفعة</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.payments.store') }}" data-store-action="{{ route('admin.payments.store') }}">
                @csrf
                <div>
                    <label class="label">الطالب *</label>
                    <select name="student" class="input" required>
                        <option value="">— اختر الطالب —</option>
                        @foreach ($students as $student)
                            <option value="{{ $student['student_no'] }}">{{ $student['name'] }} ({{ $student['student_no'] }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">المبلغ (د.ع) *</label>
                        <input name="amount" type="number" min="0" step="1000" class="input" placeholder="100000" required>
                    </div>
                    <div>
                        <label class="label">طريقة الدفع</label>
                        <select name="method" class="input">
                            <option value="cash">نقدي</option>
                            <option value="zaincash">زين كاش</option>
                            <option value="transfer">تحويل</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">التاريخ</label>
                    <input name="date" type="date" class="input" value="{{ now()->toDateString() }}">
                </div>
                <div>
                    <label class="label">ملاحظة</label>
                    <input name="note" class="input" placeholder="مثال: الدفعة الأولى">
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

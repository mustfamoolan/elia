@extends('layouts.admin')

@section('title', 'لوحة المعلومات')

@section('content')
    @php
        $cards = [
            ['users', $stats['students'], 'عدد الطلاب'],
            ['file-text', $stats['new_registrations'], 'تسجيلات جديدة'],
            ['calendar-check', $stats['attendance_rate'].'%', 'نسبة الحضور العامة'],
            ['circle-x', number_format($stats['remaining']).' د.ع', 'المبالغ المتبقية'],
            ['wallet', number_format($stats['payments_total']).' د.ع', 'الإيرادات'],
            ['banknote', number_format($stats['fees']).' د.ع', 'قيمة الاشتراكات'],
            ['book-open', $stats['courses'], 'الدورات'],
            ['tag', $stats['active_offers'], 'عروض نشطة'],
        ];
        $statusMap = [
            'new' => ['جديد', 'bg-amber-50 text-amber-700'],
            'accepted' => ['مقبول', 'bg-emerald-50 text-emerald-700'],
            'rejected' => ['مرفوض', 'bg-rose-50 text-rose-700'],
        ];
    @endphp

    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-extrabold">لوحة المعلومات</h1>
            <span class="chip bg-primary/5 text-primary/60">مرحباً، {{ session('eyal_user.name', 'مدير المعهد') }}</span>
            <a href="{{ url('/admin/reports') }}" class="btn btn-outline btn-sm mr-auto">
                <x-icon name="chart-column" :size="14" /> التقارير
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($cards as [$icon, $value, $label])
                <div class="card flex items-center gap-3 p-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary-light">
                        <x-icon :name="$icon" :size="22" />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-xl font-extrabold text-primary">{{ $value }}</div>
                        <div class="text-xs font-bold text-primary/60">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <a href="{{ url('/admin/students') }}" class="card flex flex-col items-center gap-2 p-4 transition hover:-translate-y-0.5 hover:shadow-soft">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600"><x-icon name="users" :size="20" /></div>
                <span class="text-xs font-bold text-primary">إدارة الطلاب</span>
            </a>
            <a href="{{ url('/admin/attendance') }}" class="card flex flex-col items-center gap-2 p-4 transition hover:-translate-y-0.5 hover:shadow-soft">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><x-icon name="calendar-check" :size="20" /></div>
                <span class="text-xs font-bold text-primary">تسجيل الحضور</span>
            </a>
            <a href="{{ url('/admin/grades') }}" class="card flex flex-col items-center gap-2 p-4 transition hover:-translate-y-0.5 hover:shadow-soft">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600"><x-icon name="clipboard-list" :size="20" /></div>
                <span class="text-xs font-bold text-primary">إدخال الدرجات</span>
            </a>
            <a href="{{ url('/admin/payments') }}" class="card flex flex-col items-center gap-2 p-4 transition hover:-translate-y-0.5 hover:shadow-soft">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-50 text-violet-600"><x-icon name="wallet" :size="20" /></div>
                <span class="text-xs font-bold text-primary">تسجيل دفعة</span>
            </a>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-sm font-extrabold">آخر طلبات التسجيل</h2>
                    <a href="{{ url('/admin/registrations') }}" class="mr-auto text-[11px] font-bold text-secondary hover:underline">عرض الكل</a>
                </div>
                @forelse ($registrations as $item)
                    @php [$label, $tone] = $statusMap[$item['status']] ?? ['—', 'bg-primary/5 text-primary']; @endphp
                    <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-primary">
                            <x-icon name="user-plus" :size="17" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-primary">{{ $item['name'] }}</div>
                            <div class="text-[10px] text-primary/50">{{ $item['stage'] }} • {{ $item['date'] }}</div>
                        </div>
                        <span class="chip {{ $tone }}">{{ $label }}</span>
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">📭</div>
                        <p class="text-xs font-bold text-primary/60">لا توجد طلبات تسجيل جديدة</p>
                    </div>
                @endforelse
            </section>

            <section class="card p-5">
                <div class="mb-3 flex items-center gap-2">
                    <h2 class="text-sm font-extrabold">آخر الدفعات</h2>
                    <a href="{{ url('/admin/payments') }}" class="mr-auto text-[11px] font-bold text-secondary hover:underline">عرض الكل</a>
                </div>
                @forelse ($payments as $payment)
                    <div class="flex items-center gap-3 border-b border-primary/5 py-2.5 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <x-icon name="wallet" :size="17" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-extrabold text-primary">{{ $payment['student'] }}</div>
                            <div class="text-[10px] text-primary/50">{{ $payment['method'] }} • {{ $payment['date'] }}</div>
                        </div>
                        <span class="text-xs font-extrabold text-emerald-600">{{ number_format($payment['amount']) }}</span>
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-2 py-8 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">📭</div>
                        <p class="text-xs font-bold text-primary/60">لا توجد دفعات مسجلة بعد</p>
                    </div>
                @endforelse
            </section>
        </div>

        <section class="card mt-4 overflow-hidden">
            <div class="flex items-center gap-2 border-b border-primary/5 px-5 py-3">
                <h2 class="text-sm font-extrabold">أفضل الطلاب حضوراً</h2>
                <a href="{{ url('/admin/students') }}" class="mr-auto text-[11px] font-bold text-secondary hover:underline">كل الطلاب</a>
            </div>
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>المرحلة</th>
                        <th>الحضور</th>
                        <th>المتبقي</th>
                        <th>الحالة</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($students as $student)
                        <tr>
                            <td>
                                <div class="font-extrabold text-primary">{{ $student['name'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ $student['student_no'] }} • {{ $student['phone'] }}</div>
                            </td>
                            <td>{{ $student['stage_name'] }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="progress w-16"><span style="width: {{ $student['attendance'] }}%"></span></div>
                                    <span class="text-[11px] font-bold text-primary/60">{{ $student['attendance'] }}%</span>
                                </div>
                            </td>
                            <td>{{ number_format(\App\Support\Eyal::remaining($student)) }} د.ع</td>
                            <td><span class="chip bg-emerald-50 text-emerald-700">{{ $student['status'] }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

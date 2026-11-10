@extends('layouts.admin')

@section('title', 'التقارير')

@section('content')
    @php
        $cards = [
            ['users', $stats['students'], 'عدد الطلاب'],
            ['file-text', $stats['registrations'], 'التسجيلات'],
            ['calendar-check', $stats['attendance_records'], 'سجلات الحضور'],
            ['circle-x', $stats['absences'], 'الغيابات'],
            ['clock', $stats['lates'], 'حالات التأخير'],
            ['clipboard-list', $stats['exams'], 'الامتحانات'],
            ['wallet', number_format($stats['payments_total']).' د.ع', 'الإيرادات'],
            ['banknote', number_format($stats['remaining']).' د.ع', 'المبالغ المتبقية'],
        ];
    @endphp

    <div class="fade-in space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-extrabold">التقارير</h1>
            <button type="button" class="btn btn-outline btn-sm mr-auto" data-print>
                <x-icon name="printer" :size="14" /> طباعة
            </button>
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

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <h2 class="mb-4 text-sm font-extrabold">الطلاب حسب المرحلة</h2>
                <div class="space-y-3">
                    @foreach ($byStage as $row)
                        <div>
                            <div class="mb-1 flex items-center justify-between text-[11px] font-bold">
                                <span class="text-primary">{{ $row['name'] }}</span>
                                <span class="text-primary/50">{{ $row['count'] }} طالب ({{ $row['percent'] }}%)</span>
                            </div>
                            <div class="progress"><span style="width: {{ $row['percent'] }}%"></span></div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card p-5">
                <h2 class="mb-4 text-sm font-extrabold">الطلاب حسب الدورة</h2>
                @if (collect($byCourse)->sum('count') === 0)
                    <div class="flex flex-col items-center gap-2 py-6 text-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">📭</div>
                        <p class="text-xs font-bold text-primary/60">لا توجد دورات مضافة</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($byCourse as $row)
                            @php $percent = $stats['students'] ? (int) round(($row['count'] / $stats['students']) * 100) : 0; @endphp
                            <div>
                                <div class="mb-1 flex items-center justify-between text-[11px] font-bold">
                                    <span class="text-primary">{{ $row['name'] }}</span>
                                    <span class="text-primary/50">{{ $row['count'] }} طالب</span>
                                </div>
                                <div class="progress"><span style="width: {{ $percent }}%"></span></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <section class="card overflow-hidden">
            <div class="flex items-center gap-2 border-b border-primary/5 px-5 py-3">
                <h2 class="text-sm font-extrabold">تقرير المدرسين</h2>
                <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $staff->count() }} مدرس</span>
            </div>
            @if ($staff->isEmpty())
                <div class="flex flex-col items-center gap-2 py-10 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/5 text-xl">📭</div>
                    <p class="text-xs font-bold text-primary/60">لا يوجد كادر تدريسي مضاف</p>
                </div>
            @else
                <div class="table-wrap" style="border:0;border-radius:0">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>المدرس</th>
                            <th>المادة</th>
                            <th>المرحلة</th>
                            <th>الهاتف</th>
                            <th>الحالة</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($staff as $member)
                            <tr>
                                <td class="font-extrabold text-primary">{{ $member['name'] }}</td>
                                <td>{{ $member['subject'] }}</td>
                                <td>{{ $member['stage'] }}</td>
                                <td>{{ $member['phone'] }}</td>
                                <td><span class="chip bg-emerald-50 text-emerald-700">نشط</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="card p-5">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                <h2 class="text-sm font-extrabold">قائمة الطلاب (بحث وتصفية)</h2>
                <input class="input mr-auto max-w-xs" placeholder="ابحث بالاسم أو الرقم..." data-filter-input="#reportStudents" data-filter-empty="#reportEmpty">
            </div>
            <div id="reportStudents" class="space-y-2">
                @foreach ($students as $student)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-primary/5 p-3" data-filter-item
                         data-search="{{ $student['name'].' '.$student['student_no'].' '.$student['phone'].' '.$student['stage_name'] }}">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/5 text-xs font-extrabold text-primary">
                            {{ $student['student_no'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">{{ $student['name'] }}</div>
                            <div class="text-[10px] text-primary/50">{{ $student['stage_name'] }} • {{ $student['phone'] }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="progress w-16"><span style="width: {{ $student['attendance'] }}%"></span></div>
                            <span class="text-[10px] font-bold text-primary/50">{{ $student['attendance'] }}%</span>
                        </div>
                        <span class="chip bg-primary/5 text-primary">{{ number_format(\App\Support\Eyal::remaining($student)) }} د.ع</span>
                    </div>
                @endforeach
            </div>
            <div id="reportEmpty" class="is-hidden py-6 text-center text-xs font-bold text-primary/50">لا توجد نتائج مطابقة</div>
        </section>
    </div>
@endsection

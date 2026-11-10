@extends('layouts.admin')

@section('title', 'الحضور والغياب')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الحضور والغياب</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $records->count() }} سجل حديث</span>
            <button class="btn btn-outline btn-sm" data-print>
                <x-icon name="printer" :size="14" /> طباعة
            </button>
        </div>

        <div class="card mb-4 p-4">
            <form method="POST" action="{{ route('admin.attendance.store') }}">
                @csrf
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <x-icon name="calendar-check" :size="20" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-extrabold text-primary">تسجيل حضور اليوم</div>
                        <div class="text-[11px] text-primary/60">اختر الحالة لكل طالب ثم اضغط حفظ.</div>
                    </div>
                    <input type="date" name="date" class="input max-w-[10rem]" value="{{ now()->toDateString() }}">
                    <select class="input max-w-[10rem]" data-attendance-filter>
                        <option value="">كل المراحل</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2">
                    @foreach ($students as $student)
                        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-primary/5 p-2.5" data-attendance-group data-stage="{{ $student['stage'] }}">
                            <input type="hidden" name="status[{{ $student['id'] }}]" value="" data-attendance-status>
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-[11px] font-extrabold text-primary">
                                {{ $student['student_no'] }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">{{ $student['name'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ $student['stage_name'] }} • {{ implode('، ', $student['subjects']) }}</div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" class="att-btn" data-status="present">
                                    <x-icon name="check" :size="12" /> حاضر
                                </button>
                                <button type="button" class="att-btn" data-status="late">
                                    <x-icon name="clock" :size="12" /> متأخر
                                </button>
                                <button type="button" class="att-btn" data-status="absent">
                                    <x-icon name="x" :size="12" /> غائب
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-dark mt-3 w-full">
                    <x-icon name="save" :size="16" /> حفظ الحضور
                </button>
            </form>
        </div>

        <section class="card overflow-hidden">
            <div class="flex items-center gap-2 border-b border-primary/5 px-5 py-3">
                <h2 class="text-sm font-extrabold">آخر السجلات</h2>
                <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $records->count() }}</span>
            </div>
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>التاريخ</th>
                        <th>المادة</th>
                        <th>المرحلة</th>
                        <th>الحالة</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($records as $record)
                        @php
                            $tone = $record['status'] === 'present' ? 'bg-emerald-50 text-emerald-700' : ($record['status'] === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                            $label = $record['status'] === 'present' ? 'حاضر' : ($record['status'] === 'late' ? 'متأخر' : 'غائب');
                        @endphp
                        <tr>
                            <td>
                                <div class="font-extrabold text-primary">{{ $record['student'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ $record['student_no'] }}</div>
                            </td>
                            <td>{{ $record['date'] }}</td>
                            <td>{{ $record['subject'] }}</td>
                            <td>{{ $record['stage'] }}</td>
                            <td><span class="chip {{ $tone }}">{{ $label }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

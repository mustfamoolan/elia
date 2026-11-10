@extends('layouts.teacher')

@section('title', 'طلابي')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">طلابي</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $students->count() }} طالب</span>
        </div>

        @if ($students->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">👥</div>
                <p class="text-sm font-bold text-primary/60">لا يوجد طلاب مسجلون في دوراتك بعد.</p>
            </div>
        @else
            <section class="card overflow-hidden">
                <div class="table-wrap" style="border:0;border-radius:0">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>الطالب</th>
                            <th>الرقم الدراسي</th>
                            <th>المرحلة</th>
                            <th>دوراتي معه</th>
                            <th>الحضور</th>
                            <th>الدرجات</th>
                            <th>ولي الأمر</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($students as $student)
                            @php
                                $rate = $student->attendance_total > 0
                                    ? (int) round(($student->attendance_present / $student->attendance_total) * 100)
                                    : 0;
                                $tone = $rate >= 80 ? 'bg-emerald-50 text-emerald-700' : ($rate >= 50 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                            @endphp
                            <tr>
                                <td>
                                    <div class="font-extrabold text-primary">{{ $student->name }}</div>
                                    <div class="text-[10px] text-primary/50">{{ $student->study_type ?: '—' }} • {{ $student->shift ?: '—' }}</div>
                                </td>
                                <td class="text-[11px] font-bold text-primary/60">{{ $student->student_no }}</td>
                                <td class="text-[11px] text-primary/60">{{ $student->stage?->name ?? '—' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($student->courses as $course)
                                            <span class="chip bg-primary/5 text-primary/60">{{ $course->name }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td><span class="chip {{ $tone }}">{{ $rate }}%</span></td>
                                <td class="text-[11px] font-bold text-primary/60">{{ $student->grades_total }}</td>
                                <td>
                                    <div class="text-[11px] font-bold text-primary/70">{{ $student->guardian_name ?: '—' }}</div>
                                    <div class="text-[10px] text-primary/50" dir="ltr">{{ $student->guardian_phone ?: $student->phone ?: '' }}</div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection

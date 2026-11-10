@extends('layouts.teacher')

@section('title', 'الحضور')

@section('content')
    @php
        $statusMap = [
            'present' => ['حاضر', 'bg-emerald-50 text-emerald-700'],
            'late' => ['متأخر', 'bg-amber-50 text-amber-700'],
            'absent' => ['غائب', 'bg-rose-50 text-rose-700'],
        ];
    @endphp

    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">تسجيل الحضور</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $students->count() }} طالب</span>
        </div>

        <form method="GET" action="{{ route('teacher.attendance') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[12rem] flex-1">
                <label class="label">الدورة</label>
                <select name="course" class="input" onchange="this.form.submit()">
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected($selected && $selected->id === $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[10rem]">
                <label class="label">التاريخ</label>
                <input type="date" name="date" class="input" value="{{ $date }}" onchange="this.form.submit()">
            </div>
            <button type="submit" class="btn btn-outline btn-sm">
                <x-icon name="search" :size="13" /> عرض
            </button>
        </form>

        @if (! $selected)
            <div class="card px-6 py-10 text-center text-sm font-bold text-primary/60">لم يتم تعيين أي دورة لك بعد.</div>
        @elseif ($students->isEmpty())
            <div class="card px-6 py-10 text-center text-sm font-bold text-primary/60">لا يوجد طلاب في هذه الدورة.</div>
        @else
            <form method="POST" action="{{ route('teacher.attendance.store') }}">
                @csrf
                <input type="hidden" name="course_id" value="{{ $selected->id }}">
                <input type="hidden" name="date" value="{{ $date }}">

                <section class="card overflow-hidden">
                    <div class="flex flex-wrap items-center gap-2 border-b border-primary/5 px-5 py-3">
                        <h2 class="text-sm font-extrabold">{{ $selected->name }}</h2>
                        <span class="chip bg-primary/5 text-primary/60">{{ $date }}</span>
                        <button type="submit" class="btn btn-dark btn-sm mr-auto">
                            <x-icon name="save" :size="13" /> حفظ الحضور
                        </button>
                    </div>
                    <div class="table-wrap" style="border:0;border-radius:0">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>الرقم</th>
                                <th>الحالة الحالية</th>
                                <th>تسجيل الحضور</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($students as $student)
                                @php
                                    $record = $records[$student->id] ?? null;
                                    [$label, $tone] = $statusMap[$record?->status] ?? ['لم يُسجل', 'bg-primary/5 text-primary/60'];
                                @endphp
                                <tr>
                                    <td class="font-extrabold text-primary">{{ $student->name }}</td>
                                    <td class="text-[11px] text-primary/60">{{ $student->student_no }}</td>
                                    <td><span class="chip {{ $tone }}">{{ $label }}</span></td>
                                    <td>
                                        <select name="status[{{ $student->id }}]" class="input !w-auto min-w-[8rem]">
                                            <option value="">— بدون تغيير —</option>
                                            <option value="present" @selected($record?->status === 'present')>حاضر</option>
                                            <option value="late" @selected($record?->status === 'late')>متأخر</option>
                                            <option value="absent" @selected($record?->status === 'absent')>غائب</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </form>
        @endif
    </div>
@endsection

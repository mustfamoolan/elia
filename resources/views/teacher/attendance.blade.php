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

                <section class="card overflow-hidden" id="teacherAttendanceForm">
                    <div class="flex flex-wrap items-center gap-2 border-b border-primary/5 px-5 py-3">
                        <h2 class="text-sm font-extrabold">{{ $selected->name }}</h2>
                        @if ($selected->stage)
                            <span class="chip bg-indigo-50 text-indigo-700">{{ $selected->stage->name }}</span>
                        @endif
                        <span class="chip bg-primary/5 text-primary/60">{{ $date }}</span>
                        <div class="mr-auto flex flex-wrap items-center gap-1.5">
                            <button type="button" class="btn btn-outline btn-sm" onclick="setAllTeacherAtt('present')">
                                <x-icon name="check" :size="12" /> الكل حاضر
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="setAllTeacherAtt('absent')">
                                <x-icon name="x" :size="12" /> الكل غائب
                            </button>
                            <button type="submit" class="btn btn-dark btn-sm">
                                <x-icon name="save" :size="13" /> حفظ الحضور
                            </button>
                        </div>
                    </div>
                    <div class="table-wrap" style="border:0;border-radius:0">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>الطالب</th>
                                <th>الرقم</th>
                                <th>الحالة المسجلة</th>
                                <th>تسجيل / تحديث الحضور</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($students as $student)
                                @php
                                    $record = $records[$student->id] ?? null;
                                    [$label, $tone] = $statusMap[$record?->status] ?? ['لم يُسجل بعد', 'bg-primary/5 text-primary/60'];
                                    $currentVal = $record?->status ?? 'present';
                                @endphp
                                <tr data-attendance-group>
                                    <td class="font-extrabold text-primary">{{ $student->name }}</td>
                                    <td class="text-[11px] text-primary/60">{{ $student->student_no }}</td>
                                    <td><span class="chip {{ $tone }}">{{ $label }}</span></td>
                                    <td>
                                        <input type="hidden" name="status[{{ $student->id }}]" value="{{ $currentVal }}" data-attendance-status>
                                        <div class="flex items-center gap-1">
                                            <button type="button" class="att-btn" data-status="present" @if ($currentVal === 'present') data-active="present" @endif>
                                                <x-icon name="check" :size="12" /> حاضر
                                            </button>
                                            <button type="button" class="att-btn" data-status="late" @if ($currentVal === 'late') data-active="late" @endif>
                                                <x-icon name="clock" :size="12" /> متأخر
                                            </button>
                                            <button type="button" class="att-btn" data-status="absent" @if ($currentVal === 'absent') data-active="absent" @endif>
                                                <x-icon name="x" :size="12" /> غائب
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-primary/5 p-4">
                        <button type="submit" class="btn btn-dark w-full">
                            <x-icon name="save" :size="16" /> حفظ حضور جميع الطلاب ({{ $students->count() }} طالب)
                        </button>
                    </div>
                </section>
            </form>
        @endif
    </div>

    @push('scripts')
    <script>
        function setAllTeacherAtt(status) {
            document.querySelectorAll('#teacherAttendanceForm [data-attendance-group]').forEach(function (group) {
                var input = group.querySelector('[data-attendance-status]');
                if (input) input.value = status;
                group.querySelectorAll('[data-status]').forEach(function (btn) {
                    if (btn.getAttribute('data-status') === status) {
                        btn.setAttribute('data-active', status);
                    } else {
                        btn.removeAttribute('data-active');
                    }
                });
            });
        }
    </script>
    @endpush
@endsection

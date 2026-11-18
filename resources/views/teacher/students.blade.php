@extends('layouts.teacher')

@section('title', 'طلابي')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">طلابي المشتركون في كورساتي</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $students->count() }} طالب</span>
        </div>

        @if ($students->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">👥</div>
                <p class="text-sm font-bold text-primary/60">لا يوجد طلاب مشتركون في كورساتك بعد.</p>
            </div>
        @else
            <div class="mb-4">
                <input class="input" placeholder="بحث باسم الطالب أو الرقم الدراسي أو المرحلة..." data-filter-input="#teacherStudentsList" data-filter-empty="#teacherStudentsEmpty">
            </div>

            <div id="teacherStudentsList" class="space-y-2.5" data-paginate="12">
                @foreach ($students as $student)
                    @php
                        $rate = $student->attendance_total > 0
                            ? (int) round(($student->attendance_present / $student->attendance_total) * 100)
                            : 0;
                        $tone = $rate >= 80 ? 'bg-emerald-50 text-emerald-700' : ($rate >= 50 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                    @endphp
                    <div class="sc-card" data-filter-item
                         data-search="{{ $student->name.' '.$student->student_no.' '.($student->stage?->name ?? '').' '.$student->guardian_name }}">
                        <div class="sc-summary" data-collapse-trigger aria-expanded="false">
                            <div class="sc-summary-main">
                                <div class="sc-avatar">
                                    {{ mb_substr($student->name, 0, 1) }}
                                </div>
                                <div class="sc-title-wrap">
                                    <div class="sc-title-row">
                                        <span class="sc-title">{{ $student->name }}</span>
                                        <span class="chip bg-primary/5 text-primary/70 text-[10px]">#{{ $student->student_no }}</span>
                                        <span class="chip bg-blue-50 text-blue-700 text-[10px]">{{ $student->stage?->name ?? '—' }}</span>
                                        <span class="chip {{ $tone }} text-[10px]">حضور {{ $rate }}%</span>
                                    </div>
                                    <div class="sc-subtitle">
                                        <span>{{ $student->courses->count() }} كورس معك</span>
                                        <span>•</span>
                                        <span>{{ $student->grades_total }} امتحان مسجل</span>
                                    </div>
                                </div>
                            </div>

                            <div class="sc-summary-end">
                                <button type="button" class="sc-chevron-btn" data-collapse-btn>
                                    <span data-collapse-label>التفاصيل</span>
                                    <span class="sc-chevron-icon">⌄</span>
                                </button>
                            </div>
                        </div>

                        <div class="sc-details">
                            <div class="sc-meta-grid">
                                <div class="sc-kv">
                                    <div class="sc-kv-label">الرقم الدراسي</div>
                                    <div class="sc-kv-val">#{{ $student->student_no }}</div>
                                </div>
                                <div class="sc-kv">
                                    <div class="sc-kv-label">نوع الدراسة والفترة</div>
                                    <div class="sc-kv-val">{{ $student->study_type ?: '—' }} • {{ $student->shift ?: '—' }}</div>
                                </div>
                                <div class="sc-kv">
                                    <div class="sc-kv-label">ولي الأمر</div>
                                    <div class="sc-kv-val">{{ $student->guardian_name ?: '—' }}</div>
                                </div>
                                <div class="sc-kv">
                                    <div class="sc-kv-label">هاتف التواصل</div>
                                    <div class="sc-kv-val" dir="ltr">{{ $student->guardian_phone ?: $student->phone ?: '—' }}</div>
                                </div>
                            </div>

                            <div class="rounded-xl border border-primary/8 bg-white p-3">
                                <div class="mb-1.5 text-[11px] font-extrabold text-primary/70">كورساتي المشترك بها الطالب:</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($student->courses as $course)
                                        <a href="{{ route('teacher.courses.show', $course) }}" class="chip bg-blue-50 text-blue-700 text-[11px]">
                                            {{ $course->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div id="teacherStudentsEmpty" class="is-hidden card flex flex-col items-center gap-2 px-6 py-10 text-center">
                <p class="text-sm font-bold text-primary/60">لا توجد نتائج مطابقة للبحث</p>
            </div>
        @endif
    </div>
@endsection

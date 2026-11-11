@extends('layouts.teacher')

@section('title', 'الدرجات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الدرجات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $assessments->count() }} امتحان</span>
        </div>

        <form method="GET" action="{{ route('teacher.grades') }}" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-[12rem] flex-1">
                <label class="label">الدورة</label>
                <select name="course" class="input" onchange="this.form.submit()">
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}" @selected($selected && $selected->id === $course->id)>{{ $course->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-outline btn-sm">
                <x-icon name="search" :size="13" /> عرض
            </button>
        </form>

        @if (! $selected)
            <div class="card px-6 py-10 text-center text-sm font-bold text-primary/60">لم يتم تعيين أي دورة لك بعد.</div>
        @else
            <div class="grid gap-4 lg:grid-cols-3">
                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="plus" :size="16" class="text-primary" /> إضافة درجة
                    </h2>
                    <form method="POST" action="{{ route('teacher.grades.store') }}" class="space-y-3">
                        @csrf
                        <input type="hidden" name="course_id" value="{{ $selected->id }}">
                        <div>
                            <label class="label">الطالب *</label>
                            <select name="student_id" class="input" required>
                                <option value="">— اختر الطالب —</option>
                                @foreach ($students as $student)
                                    <option value="{{ $student->id }}">{{ $student->name }} ({{ $student->student_no }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">اسم الامتحان *</label>
                            <input name="exam" class="input" placeholder="مثال: الشهر الأول" required>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label">الدرجة *</label>
                                <input name="score" type="number" min="0" step="0.5" class="input" required>
                            </div>
                            <div>
                                <label class="label">الدرجة الكاملة *</label>
                                <input name="max" type="number" min="1" step="0.5" class="input" value="100" required>
                            </div>
                        </div>
                        <div>
                            <label class="label">التاريخ</label>
                            <input name="date" type="date" class="input" value="{{ now()->toDateString() }}">
                        </div>
                        <button type="submit" class="btn btn-dark w-full">
                            <x-icon name="save" :size="14" /> حفظ الدرجة
                        </button>
                    </form>
                </section>

                <section class="card p-5 lg:col-span-2">
                    <h2 class="mb-3 text-sm font-extrabold">امتحانات {{ $selected->name }}</h2>
                    @forelse ($assessments as $assessment)
                        <div class="mb-3 rounded-xl border border-primary/5 p-3">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <div class="text-xs font-extrabold text-primary">{{ $assessment->title }}</div>
                                <span class="chip bg-primary/5 text-primary/60">من {{ (int) $assessment->max_score }}</span>
                                <span class="chip bg-blue-50 text-blue-700">{{ $assessment->assessed_at?->format('Y/m/d') }}</span>
                                <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $assessment->grades->count() }} درجة</span>
                            </div>
                            @if ($assessment->grades->isEmpty())
                                <p class="py-2 text-center text-[11px] font-bold text-primary/50">لم تُسجل درجات لهذا الامتحان</p>
                            @else
                                <div class="space-y-1">
                                    @foreach ($assessment->grades as $grade)
                                        <div class="flex items-center gap-2 rounded-lg bg-primary/3 px-2 py-1.5">
                                            <span class="min-w-0 flex-1 truncate text-[11px] font-bold text-primary">{{ $grade->student?->name ?? 'طالب' }}</span>
                                            <span class="chip bg-emerald-50 text-emerald-700">{{ (float) $grade->score }} / {{ (int) $assessment->max_score }}</span>
                                            <button type="button" class="btn btn-outline btn-sm"
                                                    onclick="const f=document.querySelector('form[action=\'{{ route('teacher.grades.store') }}\']'); if(f){ f.querySelector('[name=student_id]').value='{{ $grade->student_id }}'; f.querySelector('[name=exam]').value=@js($assessment->title); f.querySelector('[name=score]').value='{{ (float) $grade->score }}'; f.querySelector('[name=max]').value='{{ (float) $assessment->max_score }}'; f.querySelector('[name=date]').value='{{ $assessment->assessed_at?->format('Y-m-d') ?? now()->toDateString() }}'; f.querySelector('[name=score]').focus(); }"
                                                    title="تعديل الدرجة">
                                                <x-icon name="pencil" :size="11" />
                                            </button>
                                            <form method="POST" action="{{ route('teacher.grades.destroy', $grade) }}" onsubmit="return confirm('هل تريد حذف هذه الدرجة؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <x-icon name="trash-2" :size="11" />
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="py-8 text-center text-xs font-bold text-primary/50">لا توجد امتحانات مسجلة لهذه الدورة</p>
                    @endforelse
                </section>
            </div>
        @endif
    </div>
@endsection

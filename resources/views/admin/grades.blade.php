@extends('layouts.admin')

@section('title', 'الدرجات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الدرجات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $grades->count() }} نتيجة</span>
            <button class="btn btn-dark btn-sm" data-modal-open="gradeModal" data-add="gradeModal" data-add-title="إضافة درجة">
                <x-icon name="plus" :size="14" /> إضافة درجة
            </button>
        </div>

        <div class="mb-4">
            <input class="input" placeholder="بحث بالطالب أو المادة..." data-filter-input="#gradesList" data-filter-empty="#gradesEmpty">
        </div>

        <section class="card overflow-hidden">
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>المادة</th>
                        <th>الامتحان</th>
                        <th>الدرجة</th>
                        <th>النسبة</th>
                        <th>التاريخ</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody id="gradesList">
                    @foreach ($grades as $grade)
                        @php
                            $percent = (int) round(($grade['score'] / max(1, $grade['max'])) * 100);
                            $tone = $percent >= 85 ? 'bg-emerald-50 text-emerald-700' : ($percent >= 60 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                        @endphp
                        <tr data-filter-item data-search="{{ $grade['student'].' '.$grade['student_no'].' '.$grade['course'].' '.$grade['exam'] }}">
                            <td>
                                <div class="font-extrabold text-primary">{{ $grade['student'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ $grade['student_no'] }} • {{ $grade['stage'] }}</div>
                            </td>
                            <td>{{ $grade['course'] }}</td>
                            <td>{{ $grade['exam'] }}</td>
                            <td class="font-extrabold text-primary">{{ $grade['score'] }} / {{ $grade['max'] }}</td>
                            <td><span class="chip {{ $tone }}">{{ $percent }}%</span></td>
                            <td class="text-[11px] text-primary/50">{{ $grade['date'] }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <button class="btn btn-outline btn-sm" data-modal="gradeModal" data-edit="{{ json_encode([
                                        'action' => route('admin.grades.update', $grade['id']),
                                        'modal_title' => 'تعديل الدرجة',
                                        'student' => $grade['student_no'],
                                        'course' => $grade['course_id'],
                                        'exam' => $grade['exam'],
                                        'score' => $grade['score'],
                                        'max' => $grade['max'],
                                        'date' => $grade['date'],
                                    ], JSON_UNESCAPED_UNICODE) }}">
                                        <x-icon name="pencil" :size="12" />
                                    </button>
                                    <form method="POST" action="{{ route('admin.grades.destroy', $grade['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف هذه الدرجة؟')">
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
            <div id="gradesEmpty" class="is-hidden py-6 text-center text-xs font-bold text-primary/50">لا توجد نتائج مطابقة</div>
        </section>
    </div>

    <div class="modal-overlay" id="gradeModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة درجة</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.grades.store') }}" data-store-action="{{ route('admin.grades.store') }}">
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
                <div>
                    <label class="label">المادة</label>
                    <select name="course" class="input" required>
                        <option value="">— اختر المادة —</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course['id'] }}">{{ $course['name'] }} — {{ $course['stage_name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">اسم الامتحان *</label>
                    <input name="exam" class="input" placeholder="مثال: الامتحان الشهري الأول" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">الدرجة</label>
                        <input name="score" type="number" min="0" class="input" placeholder="90">
                    </div>
                    <div>
                        <label class="label">الدرجة الكاملة</label>
                        <input name="max" type="number" min="1" class="input" value="100">
                    </div>
                </div>
                <div>
                    <label class="label">التاريخ</label>
                    <input name="date" type="date" class="input" value="{{ now()->toDateString() }}">
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

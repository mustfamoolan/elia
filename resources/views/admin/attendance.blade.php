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

        <div class="card mb-4 p-4" id="adminAttendanceCard">
            <form method="POST" action="{{ route('admin.attendance.store') }}">
                @csrf
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                        <x-icon name="calendar-check" :size="20" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-extrabold text-primary">تسجيل الحضور والغياب</div>
                        <div class="text-[11px] text-primary/60">اختر الحالة لكل طالب أو استخدم أزرار التحديد السريع ثم اضغط حفظ.</div>
                    </div>
                    <input type="date" name="date" class="input max-w-[10rem]" value="{{ $date }}"
                           onchange="window.location.href='{{ route('admin.attendance') }}?date='+this.value+'{{ $selectedCourseId ? '&course_id='.$selectedCourseId : '' }}'">
                    <select name="course_id" class="input max-w-[12rem]"
                            onchange="window.location.href='{{ route('admin.attendance') }}?date={{ $date }}&course_id='+this.value">
                        <option value="">كل الدورات (تلقائي)</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course['id'] }}" @selected($selectedCourseId === (int) $course['id'])>{{ $course['name'] }}</option>
                        @endforeach
                    </select>
                    <select class="input max-w-[10rem]" data-attendance-filter>
                        <option value="">كل المراحل</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-primary/[0.03] px-3 py-2">
                    <span class="text-xs font-bold text-primary/70">تحديد سريع للطلاب المعروضين:</span>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" class="btn btn-outline btn-sm" onclick="setAdminAllAttendance('present')">
                            <x-icon name="check" :size="12" /> الكل حاضر
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setAdminAllAttendance('late')">
                            <x-icon name="clock" :size="12" /> الكل متأخر
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="setAdminAllAttendance('absent')">
                            <x-icon name="x" :size="12" /> الكل غائب
                        </button>
                    </div>
                </div>

                <div class="space-y-2">
                    @forelse ($students as $student)
                        @php
                            $currentStatus = $todayStatuses[$student['id']] ?? '';
                        @endphp
                        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-primary/5 p-2.5" data-attendance-group data-stage="{{ $student['stage'] }}">
                            <input type="hidden" name="status[{{ $student['id'] }}]" value="{{ $currentStatus }}" data-attendance-status>
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/5 text-[11px] font-extrabold text-primary">
                                {{ $student['student_no'] }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-extrabold text-primary">{{ $student['name'] }}</span>
                                    @if ($currentStatus)
                                        <span class="chip bg-emerald-50 text-[10px] text-emerald-700">مسجل لهذا اليوم</span>
                                    @endif
                                </div>
                                <div class="text-[10px] text-primary/50">{{ $student['stage_name'] }} • {{ implode('، ', $student['subjects']) ?: 'عام' }}</div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" class="att-btn" data-status="present" @if ($currentStatus === 'present') data-active="present" @endif>
                                    <x-icon name="check" :size="12" /> حاضر
                                </button>
                                <button type="button" class="att-btn" data-status="late" @if ($currentStatus === 'late') data-active="late" @endif>
                                    <x-icon name="clock" :size="12" /> متأخر
                                </button>
                                <button type="button" class="att-btn" data-status="absent" @if ($currentStatus === 'absent') data-active="absent" @endif>
                                    <x-icon name="x" :size="12" /> غائب
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-primary/15 p-6 text-center text-xs font-bold text-primary/50">
                            لا يوجد طلاب مسجلون حالياً.
                        </div>
                    @endforelse
                </div>

                @if ($students->isNotEmpty())
                    <button type="submit" class="btn btn-dark mt-3 w-full">
                        <x-icon name="save" :size="16" /> حفظ الحضور لتاريخ ({{ $date }})
                    </button>
                @endif
            </form>
        </div>

        @push('scripts')
        <script>
            function setAdminAllAttendance(status) {
                document.querySelectorAll('#adminAttendanceCard [data-attendance-group]').forEach(function (group) {
                    if (group.classList.contains('is-hidden')) return;
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
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($records as $record)
                        @php
                            $tone = $record['status'] === 'present' ? 'bg-emerald-50 text-emerald-700' : ($record['status'] === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                            $label = $record['status'] === 'present' ? 'حاضر' : ($record['status'] === 'late' ? 'متأخر' : 'غائب');
                            $editAttPayload = [
                                'action' => route('admin.attendance.update', $record['id']),
                                'modal_title' => 'تعديل سجل حضور: '.$record['student'],
                                'date' => $record['date'],
                                'status' => $record['status'],
                            ];
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
                            <td>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="btn btn-outline btn-sm" data-modal="attendanceEditModal" data-edit="{{ json_encode($editAttPayload, JSON_UNESCAPED_UNICODE) }}">
                                        <x-icon name="pencil" :size="12" /> تعديل
                                    </button>
                                    <form method="POST" action="{{ route('admin.attendance.destroy', $record['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف سجل الحضور هذا؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm" type="submit" title="حذف">
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
        </section>
    </div>

    <div class="modal-overlay" id="attendanceEditModal">
        <div class="modal-panel modal-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-extrabold" data-modal-title>تعديل سجل الحضور</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="">
                @csrf
                <div>
                    <label class="label">التاريخ *</label>
                    <input type="date" name="date" class="input" required>
                </div>
                <div>
                    <label class="label">الحالة *</label>
                    <select name="status" class="input" required>
                        <option value="present">حاضر</option>
                        <option value="late">متأخر</option>
                        <option value="absent">غائب</option>
                    </select>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ التعديل
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

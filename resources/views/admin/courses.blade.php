@extends('layouts.admin')

@section('title', 'إدارة الكورسات والدورات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div>
                <h1 class="text-2xl font-extrabold">الكورسات والدورات التدريبية</h1>
                <p class="mt-0.5 text-xs text-primary/60">اختر مرحلة الكورس ومواده ليقوم النظام تلقائياً بتوزيع المواد على مدرسيها المختصين.</p>
            </div>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $courses->count() }} كورس</span>
            <a href="{{ route('admin.subjects') }}" class="btn btn-outline btn-sm">
                <x-icon name="book-marked" :size="14" /> المواد الدراسية
            </a>
            <button class="btn btn-dark btn-sm" data-modal-open="courseModal" data-add="courseModal" data-add-title="إنشاء كورس جديد">
                <x-icon name="plus" :size="14" /> إنشاء كورس
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($courses as $course)
                <div class="card course-card flex flex-col p-4">
                    <div class="mb-2 flex items-start gap-2">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                            <x-icon name="book-open" :size="20" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-extrabold leading-snug text-primary">{{ $course['name'] }}</div>
                            <div class="mt-0.5 text-[11px] font-bold text-primary/60">
                                {{ $course['stage_name'] }}
                            </div>
                        </div>
                        <span class="chip shrink-0 {{ $course['active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $course['active'] ? 'نشط' : 'متوقف' }}
                        </span>
                    </div>

                    {{-- عرض مواد الكورس مع مدرس كل مادة --}}
                    @if (! empty($course['course_subjects']))
                        <div class="my-2 rounded-xl bg-primary/3 p-2.5">
                            <div class="mb-1.5 text-[10px] font-extrabold text-primary/70">مواد الكورس وتوزيع المدرسين:</div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($course['course_subjects'] as $cs)
                                    <span class="chip border border-primary/10 bg-white text-[10px] text-primary">
                                        <strong>{{ $cs['name'] }}</strong>
                                        @if (!empty($cs['teacher_name']) && $cs['teacher_name'] !== '—')
                                            <span class="text-emerald-700">• {{ $cs['teacher_name'] }}</span>
                                        @else
                                            <span class="text-amber-600">• بدون مدرس</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @elseif (!empty($course['subject']))
                        <div class="my-2">
                            <span class="chip bg-primary/5 text-primary/70" style="white-space: normal; line-height: 1.4;">{{ $course['subject'] }}</span>
                        </div>
                    @endif

                    @if (!empty($course['description']))
                        <p class="mb-3 text-[11px] leading-relaxed text-primary/60">{{ $course['description'] }}</p>
                    @endif

                    <div class="space-y-1 text-[11px] text-primary/60">
                        @if (!empty($course['schedule']))
                            <div class="flex items-center gap-1.5"><x-icon name="calendar-days" :size="12" /> {{ $course['schedule'] }}</div>
                        @endif
                        <div class="flex items-center gap-1.5"><x-icon name="users" :size="12" /> {{ $course['enrolled'] }} طالب مشترك (من {{ $course['seats'] }} مقعد)</div>
                    </div>
                    <div class="mt-2"><div class="progress"><span style="width: {{ (int) round(($course['enrolled'] / max(1, $course['seats'])) * 100) }}%"></span></div></div>
                    <div class="mt-3 flex items-center gap-1 border-t border-primary/5 pt-3">
                        <div class="text-sm font-extrabold text-primary">{{ number_format($course['price']) }} <span class="text-[10px] text-primary/50">د.ع</span></div>
                        <div class="mr-auto flex items-center gap-1">
                            <button class="btn btn-outline btn-sm" data-modal="courseModal" data-edit="{{ json_encode([
                                'action' => route('admin.courses.update', $course['id']),
                                'modal_title' => 'تعديل الكورس وتوزيع المواد',
                                'name' => $course['name'],
                                'stage' => $course['stage'],
                                'subject_ids' => $course['subject_ids'] ?? [],
                                'price' => $course['price'],
                                'seats' => $course['seats'],
                                'schedule' => $course['schedule'],
                                'description' => $course['description'],
                                'active' => $course['active'],
                            ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" /> تعديل
                            </button>
                            <form method="POST" action="{{ route('admin.courses.destroy', $course['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف {{ $course['name'] }}؟')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm" type="submit">
                                    <x-icon name="trash-2" :size="12" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="courseModal">
        <div class="modal-panel modal-lg">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إنشاء كورس جديد</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form id="courseForm" class="space-y-3" method="POST" action="{{ route('admin.courses.store') }}" data-store-action="{{ route('admin.courses.store') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">اسم الكورس / الدورة *</label>
                        <input name="name" class="input" placeholder="مثال: كورس المراجعة الشاملة أو دورة الرياضيات" required>
                    </div>
                    <div>
                        <label class="label">1. المرحلة الدراسية للكورس *</label>
                        <select name="stage" id="courseStageSelect" class="input" required onchange="filterCourseSubjectsByStage()">
                            <option value="">— اختر المرحلة الدراسية —</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- الخطوة 2: اختيار مواد الكورس مع التوزيع التلقائي للمدرسين --}}
                <div class="rounded-2xl border border-primary/10 bg-primary/[0.02] p-3.5">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <label class="label !mb-0">2. اختر المواد المشمولة في هذا الكورس *</label>
                            <p class="text-[10px] text-primary/60">سيقوم النظام تلقائياً بتعيين المدرس المختص لكل مادة تختارها في هذه المرحلة.</p>
                        </div>
                        <button type="button" id="selectAllStageSubjectsBtn" class="btn btn-outline btn-sm is-hidden" onclick="toggleAllCourseStageSubjects()">
                            تحديد كل مواد المرحلة
                        </button>
                    </div>

                    <div id="courseSubjectsEmptyPrompt" class="rounded-xl border border-dashed border-primary/15 bg-white p-4 text-center text-xs font-bold text-primary/50">
                        اختر المرحلة الدراسية أولاً من الحقل أعلاه لتظهر لك موادها الدراسية ومدرسوها المختصون.
                    </div>

                    @foreach ($stages as $st)
                        @php
                            $stSubjects = $subjects->where('stage_id', $st['id'])->values();
                        @endphp
                        <div class="is-hidden space-y-2" data-course-stage-box="{{ $st['id'] }}">
                            @if ($stSubjects->isEmpty())
                                <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 p-3 text-center text-xs font-bold text-amber-800">
                                    لا توجد مواد مضافة لمرحلة «{{ $st['name'] }}» بعد.
                                    <a href="{{ route('admin.subjects', ['stage' => $st['id']]) }}" class="underline">+ أضف مواد لهذه المرحلة أولاً</a>.
                                </div>
                            @else
                                <div class="grid gap-2 sm:grid-cols-2">
                                    @foreach ($stSubjects as $sub)
                                        <label class="flex cursor-pointer items-center justify-between gap-2 rounded-xl border border-primary/10 bg-white p-2.5 text-xs transition hover:border-primary/30">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <input type="checkbox" name="subject_ids[]" value="{{ $sub['id'] }}"
                                                       class="h-4 w-4 rounded border-primary/25 text-primary">
                                                <span class="truncate font-extrabold text-primary">{{ $sub['name'] }}</span>
                                            </div>
                                            @if (!empty($sub['teacher_names']))
                                                <span class="chip shrink-0 bg-emerald-50 text-[10px] text-emerald-700" title="يُعيّن تلقائياً">
                                                    <x-icon name="user-round" :size="11" /> {{ $sub['teacher_names'] }}
                                                </span>
                                            @else
                                                <span class="chip shrink-0 bg-amber-50 text-[10px] text-amber-700">
                                                    بدون مدرس معيّن
                                                </span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="label">سعر الاشتراك بالكورس (د.ع)</label>
                        <input name="price" type="number" min="0" step="1000" class="input" placeholder="150000" value="0">
                    </div>
                    <div>
                        <label class="label">عدد المقاعد</label>
                        <input name="seats" type="number" min="1" class="input" placeholder="30" value="30">
                    </div>
                    <div>
                        <label class="label">الجدول / المواعيد</label>
                        <input name="schedule" class="input" placeholder="السبت – الخميس · 4:00 م">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="label">وصف الكورس</label>
                        <textarea name="description" class="input min-h-[70px]" placeholder="وصف مختصر للكورس..."></textarea>
                    </div>
                </div>
                <label class="flex items-center gap-2 rounded-xl bg-primary/4 p-3">
                    <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-primary/20">
                    <span class="text-xs font-extrabold text-primary">الكورس نشط ومتاح لاشتراك الطلاب</span>
                </label>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ الكورس وتوزيع المدرسين
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function filterCourseSubjectsByStage() {
        var form = document.getElementById('courseForm');
        var select = document.getElementById('courseStageSelect');
        if (!form || !select) return;

        var selectedStage = String(select.value || '');
        var emptyPrompt = document.getElementById('courseSubjectsEmptyPrompt');
        var selectAllBtn = document.getElementById('selectAllStageSubjectsBtn');

        if (emptyPrompt) {
            emptyPrompt.classList.toggle('is-hidden', selectedStage !== '');
        }
        if (selectAllBtn) {
            selectAllBtn.classList.toggle('is-hidden', selectedStage === '');
        }

        form.querySelectorAll('[data-course-stage-box]').forEach(function (box) {
            var stId = String(box.getAttribute('data-course-stage-box'));
            var active = stId === selectedStage;
            box.classList.toggle('is-hidden', !active);
            if (!active) {
                box.querySelectorAll('input[name="subject_ids[]"]').forEach(function (cb) {
                    cb.checked = false;
                });
            }
        });
    }

    function toggleAllCourseStageSubjects() {
        var form = document.getElementById('courseForm');
        var select = document.getElementById('courseStageSelect');
        if (!form || !select || !select.value) return;
        var activeBox = form.querySelector('[data-course-stage-box="' + select.value + '"]');
        if (!activeBox) return;
        var checkboxes = Array.from(activeBox.querySelectorAll('input[name="subject_ids[]"]'));
        var allChecked = checkboxes.length > 0 && checkboxes.every(function (cb) { return cb.checked; });
        checkboxes.forEach(function (cb) { cb.checked = !allChecked; });
    }

    (function () {
        var form = document.getElementById('courseForm');
        if (!form) return;
        form.addEventListener('eyal:edit-populated', function (e) {
            filterCourseSubjectsByStage();
            var data = (e && e.detail) ? e.detail : {};
            if (Array.isArray(data.subject_ids)) {
                var vals = data.subject_ids.map(String);
                form.querySelectorAll('input[name="subject_ids[]"]').forEach(function (cb) {
                    cb.checked = vals.indexOf(String(cb.value)) !== -1;
                });
            }
        });
        form.addEventListener('eyal:add-reset', function () {
            filterCourseSubjectsByStage();
        });
    })();
</script>
@endpush

@extends('layouts.admin')

@section('title', 'الكادر التدريسي وتوزيع المواد')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div>
                <h1 class="text-2xl font-extrabold">الكادر التدريسي وتوزيع المراحل والمواد</h1>
                <p class="mt-0.5 text-xs text-primary/60">اختر لكل مدرس المراحل التي يدرّسها ثم حدد المواد الخاصة به في تلك المراحل.</p>
            </div>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $staff->count() }} مدرس</span>
            <a href="{{ route('admin.subjects') }}" class="btn btn-outline btn-sm">
                <x-icon name="book-marked" :size="14" /> إدارة مواد المراحل
            </a>
            <button class="btn btn-dark btn-sm" data-modal-open="staffModal" data-add="staffModal" data-add-title="إضافة مدرس وتعيين مراحله ومواده">
                <x-icon name="plus" :size="14" /> إضافة مدرس
            </button>
        </div>

        <div class="card mb-4 flex flex-wrap items-center gap-3 p-4">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <x-icon name="user-round" :size="20" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-extrabold text-primary">ظهور قسم الكادر في الموقع العام</div>
                <div class="text-[11px] text-primary/60">عند التفعيل يظهر الكادر التدريسي في الصفحة العامة للمعهد.</div>
            </div>
            <button class="toggle" aria-checked="{{ $staffEnabled ? 'true' : 'false' }}"
                    data-toggle-url="{{ route('admin.staff.toggle') }}"
                    data-toggle-toast="تم تحديث ظهور الكادر" aria-label="إظهار الكادر"></button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($staff as $member)
                <div class="card flex flex-col p-4">
                    <div class="mb-3 flex items-start gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-base font-extrabold text-primary">
                            {{ mb_substr($member['name'], 0, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-extrabold text-primary">{{ $member['name'] }}</div>
                            <div class="text-[11px] text-primary/60">
                                {{ $member['subject'] ?: 'لم تحدد المواد' }} • {{ $member['stage'] ?: 'لم تحدد المراحل' }}
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1">
                                <span class="chip bg-blue-50 text-blue-700 text-[10px]">{{ count($member['subjects_list'] ?? []) }} مادة مسندة</span>
                                <span class="chip bg-violet-50 text-violet-700 text-[10px]">{{ $member['courses_count'] ?? 0 }} كورس</span>
                                <span class="chip bg-emerald-50 text-emerald-700 text-[10px]">{{ $member['students_count'] ?? 0 }} طالب مشترك</span>
                                <span class="chip bg-primary/5 text-primary/60 text-[10px]">المستخدم: {{ $member['username'] }}</span>
                            </div>
                        </div>
                    </div>

                    @if (! empty($member['subjects_list']))
                        <div class="mb-2.5 rounded-xl bg-primary/3 p-2.5">
                            <div class="mb-1.5 text-[10px] font-extrabold text-primary/70">المواد التي يدرّسها حسب المرحلة:</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($member['subjects_list'] as $sub)
                                    <span class="chip border border-primary/10 bg-white text-[10px] text-primary">
                                        {{ $sub['name'] }} <span class="text-primary/50">({{ $sub['stage_name'] }})</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mb-2.5 rounded-xl bg-amber-50 p-2.5 text-[10px] font-bold text-amber-700">
                            لم يتم تحديد مراحل ومواد لهذا المدرس بعد — اضغط «تعديل المراحل والمواد» لتحديدها.
                        </div>
                    @endif

                    @if (! empty($member['courses_list']))
                        <div class="mb-3 rounded-xl bg-blue-50/50 p-2.5">
                            <div class="mb-1 text-[10px] font-extrabold text-blue-800">الكورسات المشارك بتدريسها:</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($member['courses_list'] as $c)
                                    <span class="chip bg-white text-[10px] text-blue-700">
                                        {{ $c['name'] }} ({{ $c['students_count'] }} طالب)
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if ($member['bio'])
                        <p class="mb-3 text-[11px] leading-relaxed text-primary/60">{{ $member['bio'] }}</p>
                    @endif

                    <div class="mt-auto flex items-center gap-2 border-t border-primary/5 pt-3">
                        @if ($member['phone'])
                            <a href="tel:{{ $member['phone'] }}" class="chip bg-primary/5 text-primary">
                                <x-icon name="phone" :size="12" /> {{ $member['phone'] }}
                            </a>
                        @endif
                        <div class="mr-auto flex items-center gap-1">
                            <button class="btn btn-outline btn-sm" data-modal="staffModal" data-edit="{{ json_encode([
                                'action' => route('admin.staff.update', $member['id']),
                                'modal_title' => 'تعديل بيانات المدرس ومراحله ومواده',
                                'name' => $member['name'],
                                'username' => $member['username'],
                                'phone' => $member['phone'],
                                'bio' => $member['bio'],
                                'stage_ids' => $member['stage_ids'] ?? [],
                                'subject_ids' => $member['subject_ids'] ?? [],
                            ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" /> تعديل المراحل والمواد
                            </button>
                            <form method="POST" action="{{ route('admin.staff.destroy', $member['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف {{ $member['name'] }}؟')">
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

    <div class="modal-overlay" id="staffModal">
        <div class="modal-panel modal-lg">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة مدرس وتعيين مراحله ومواده</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form id="staffForm" class="space-y-3" method="POST" action="{{ route('admin.staff.store') }}" data-store-action="{{ route('admin.staff.store') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">اسم المدرس *</label>
                        <input name="name" class="input" placeholder="مثال: أ. حسين الكعبي" required>
                    </div>
                    <div>
                        <label class="label">رقم الهاتف</label>
                        <input name="phone" class="input" placeholder="07XXXXXXXXX" inputmode="tel">
                    </div>
                    <div>
                        <label class="label">اسم المستخدم للدخول *</label>
                        <input name="username" class="input" placeholder="مثال: hussein" required>
                    </div>
                    <div>
                        <label class="label">كلمة المرور</label>
                        <input name="password" type="password" class="input" placeholder="اتركه فارغاً عند التعديل">
                    </div>
                </div>

                {{-- الخطوة 1: اختيار المراحل الدراسية --}}
                <div class="rounded-2xl border border-primary/10 bg-primary/[0.02] p-3.5">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="label !mb-0">1. اختر المراحل الدراسية التي يدرّسها الأستاذ (مرحلة أو أكثر) *</label>
                        <a href="{{ route('admin.stages') }}" class="text-[11px] font-bold text-primary hover:underline">+ إدارة المراحل</a>
                    </div>
                    @if ($stages->isEmpty())
                        <p class="text-xs text-amber-700">لا توجد مراحل مضافة بعد. يرجى إضافة المراحل أولاً.</p>
                    @else
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" id="staffStagesBox">
                            @foreach ($stages as $st)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-primary/10 bg-white px-3 py-2 text-xs font-bold text-primary transition hover:border-primary/30">
                                    <input type="checkbox" name="stage_ids[]" value="{{ $st['id'] }}"
                                           class="h-4 w-4 rounded border-primary/20"
                                           onchange="filterStaffSubjectsByStages()">
                                    <span>{{ $st['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- الخطوة 2: اختيار المواد التابعة للمراحل المختارة فقط --}}
                <div class="rounded-2xl border border-primary/10 bg-primary/[0.02] p-3.5">
                    <div class="mb-2 flex items-center justify-between">
                        <label class="label !mb-0">2. اختر الدروس / المواد التي يدرّسها في المراحل المختارة *</label>
                        <a href="{{ route('admin.subjects') }}" class="text-[11px] font-bold text-primary hover:underline">+ إضافة مواد للمراحل</a>
                    </div>

                    <div id="staffSubjectsEmptyPrompt" class="rounded-xl border border-dashed border-primary/15 bg-white p-4 text-center text-xs font-bold text-primary/50">
                        اختر مرحلة دراسية أو أكثر من الخطوة (1) أعلاه لتظهر لك موادها الدراسية هنا.
                    </div>

                    <div id="staffSubjectsNoStageSubjects" class="is-hidden rounded-xl border border-dashed border-amber-300 bg-amber-50 p-3 text-center text-xs font-bold text-amber-800">
                        المراحل المختارة لا تحتوي على مواد دراسية بعد. <a href="{{ route('admin.subjects') }}" class="underline">اضغط هنا لإضافة مواد لهذه المرحلة</a>.
                    </div>

                    <div id="staffSubjectsGroups" class="space-y-2.5">
                        @foreach ($stages as $st)
                            @php
                                $stSubjects = $subjects->where('stage_id', $st['id'])->values();
                            @endphp
                            <div class="is-hidden rounded-xl border border-primary/10 bg-white p-3"
                                 data-staff-stage-group="{{ $st['id'] }}"
                                 data-has-subjects="{{ $stSubjects->isNotEmpty() ? '1' : '0' }}">
                                <div class="mb-2 flex items-center justify-between border-b border-primary/5 pb-1.5">
                                    <span class="text-xs font-extrabold text-primary">مواد مرحلة: {{ $st['name'] }}</span>
                                    <span class="text-[10px] font-bold text-primary/50">{{ $stSubjects->count() }} مادة</span>
                                </div>
                                @if ($stSubjects->isEmpty())
                                    <p class="text-[11px] text-amber-700">
                                        لا توجد مواد مضافة لهذه المرحلة.
                                        <a href="{{ route('admin.subjects', ['stage' => $st['id']]) }}" class="font-bold underline">+ أضف مواد لـ {{ $st['name'] }}</a>
                                    </p>
                                @else
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                        @foreach ($stSubjects as $sub)
                                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-primary/10 bg-primary/[0.02] px-2.5 py-2 text-xs font-bold text-primary hover:border-primary/30">
                                                <input type="checkbox" name="subject_ids[]" value="{{ $sub['id'] }}"
                                                       data-subject-stage="{{ $st['id'] }}"
                                                       class="h-4 w-4 rounded border-primary/20">
                                                <span class="truncate">{{ $sub['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="label">نبذة مختصرة (اختياري)</label>
                    <textarea name="bio" class="input min-h-[60px]" placeholder="نبذة مختصرة عن المدرس..."></textarea>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ المدرس ومواده
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function filterStaffSubjectsByStages() {
        var form = document.getElementById('staffForm');
        if (!form) return;

        var checkedStages = Array.from(form.querySelectorAll('input[name="stage_ids[]"]:checked')).map(function (cb) {
            return String(cb.value);
        });

        var emptyPrompt = document.getElementById('staffSubjectsEmptyPrompt');
        if (emptyPrompt) {
            emptyPrompt.classList.toggle('is-hidden', checkedStages.length > 0);
        }

        document.querySelectorAll('[data-staff-stage-group]').forEach(function (group) {
            var stageId = String(group.getAttribute('data-staff-stage-group'));
            var isSelected = checkedStages.indexOf(stageId) !== -1;
            group.classList.toggle('is-hidden', !isSelected);

            if (!isSelected) {
                group.querySelectorAll('input[name="subject_ids[]"]').forEach(function (subCb) {
                    subCb.checked = false;
                });
            }
        });
    }

    (function () {
        var form = document.getElementById('staffForm');
        if (!form) return;
        form.addEventListener('eyal:edit-populated', function () {
            // إذا كان هناك مادة محددة، نتأكد من تأشير مرحلتها أيضاً
            form.querySelectorAll('input[name="subject_ids[]"]:checked').forEach(function (subCb) {
                var stId = subCb.getAttribute('data-subject-stage');
                if (stId) {
                    var stCb = form.querySelector('input[name="stage_ids[]"][value="' + stId + '"]');
                    if (stCb) stCb.checked = true;
                }
            });
            filterStaffSubjectsByStages();
        });
        form.addEventListener('eyal:add-reset', function () {
            filterStaffSubjectsByStages();
        });
    })();
</script>
@endpush

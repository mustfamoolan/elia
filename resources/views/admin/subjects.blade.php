@extends('layouts.admin')

@section('title', 'المواد الدراسية لكل مرحلة')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div>
                <h1 class="text-2xl font-extrabold">المواد الدراسية لكل مرحلة</h1>
                <p class="mt-0.5 text-xs text-primary/60">أنشئ المواد الدراسية الخاصة بكل مرحلة ليتم ربط المدرسين والكورسات بها بشكل دقيق.</p>
            </div>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $subjects->count() }} مادة</span>
            <a href="{{ route('admin.stages') }}" class="btn btn-outline btn-sm">
                <x-icon name="layers" :size="14" /> المراحل الدراسية
            </a>
            <button class="btn btn-dark btn-sm" data-modal-open="subjectModal" data-add="subjectModal" data-add-title="إضافة مادة دراسية">
                <x-icon name="plus" :size="14" /> إضافة مادة
            </button>
        </div>

        {{-- شريط تصفية حسب المرحلة --}}
        <div class="hide-scrollbar mb-4 flex gap-2 overflow-x-auto pb-1">
            <a href="{{ route('admin.subjects') }}"
               class="chip whitespace-nowrap {{ ! $activeStage ? 'bg-primary text-white shadow-soft' : 'bg-white text-primary' }}">
                كل المراحل
            </a>
            @foreach ($stages as $stage)
                <a href="{{ route('admin.subjects', ['stage' => $stage['id']]) }}"
                   class="chip whitespace-nowrap {{ $activeStage === $stage['id'] ? 'bg-primary text-white shadow-soft' : 'bg-white text-primary' }}">
                    {{ $stage['name'] }} ({{ $stage['subjects_count'] ?? 0 }})
                </a>
            @endforeach
        </div>

        @if ($stages->isEmpty())
            <div class="card flex flex-col items-center gap-3 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📚</div>
                <p class="text-sm font-bold text-primary/60">يجب أولاً إنشاء المراحل الدراسية قبل إضافة المواد.</p>
                <a href="{{ route('admin.stages') }}" class="btn btn-dark btn-sm">انتقل إلى صفحة المراحل</a>
            </div>
        @elseif ($subjects->isEmpty())
            <div class="card flex flex-col items-center gap-3 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📖</div>
                <p class="text-sm font-bold text-primary/60">لا توجد مواد دراسية مضافة {{ $activeStage ? 'لهذه المرحلة' : 'حتى الآن' }}.</p>
                <button class="btn btn-dark btn-sm" data-modal-open="subjectModal" data-add="subjectModal" data-add-title="إضافة مادة دراسية">
                    <x-icon name="plus" :size="14" /> إضافة مواد الآن
                </button>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($stages as $stage)
                    @php
                        $stageSubjects = $subjects->where('stage_id', $stage['id'])->values();
                    @endphp
                    @if ($stageSubjects->isNotEmpty())
                        <section class="card p-5">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-primary/5 pb-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/8 text-primary">
                                        <x-icon name="layers" :size="18" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-extrabold text-primary">{{ $stage['name'] }}</h2>
                                        <div class="text-[11px] text-primary/50">{{ $stageSubjects->count() }} مادة دراسية في هذه المرحلة</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm"
                                        onclick="openAddSubjectForStage('{{ $stage['id'] }}', '{{ $stage['name'] }}')">
                                    <x-icon name="plus" :size="13" /> إضافة مادة لـ {{ $stage['name'] }}
                                </button>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($stageSubjects as $subject)
                                    <div class="flex flex-col justify-between rounded-2xl border border-primary/10 bg-primary/[0.015] p-3.5">
                                        <div>
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-sm font-extrabold text-primary">{{ $subject['name'] }}</div>
                                                    <div class="text-[11px] text-primary/50">{{ $subject['stage_name'] }}</div>
                                                </div>
                                                <span class="chip {{ $subject['active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                    {{ $subject['active'] ? 'فعالة' : 'متوقفة' }}
                                                </span>
                                            </div>

                                            @if (!empty($subject['description']))
                                                <p class="mt-1.5 text-[11px] text-primary/60">{{ $subject['description'] }}</p>
                                            @endif

                                            <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                                @if (!empty($subject['teacher_names']))
                                                    <span class="chip bg-blue-50 text-blue-700 text-[10px]">
                                                        <x-icon name="user-round" :size="11" /> {{ $subject['teacher_names'] }}
                                                    </span>
                                                @else
                                                    <span class="chip bg-amber-50 text-amber-700 text-[10px]">لم يُعيّن مدرس بعد</span>
                                                @endif
                                                <span class="chip bg-primary/5 text-primary/60 text-[10px]">{{ $subject['courses_count'] }} كورس</span>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center justify-end gap-1 border-t border-primary/5 pt-2.5">
                                            <button type="button" class="btn btn-outline btn-sm"
                                                    data-modal="subjectModal"
                                                    data-edit="{{ json_encode([
                                                        'action' => route('admin.subjects.update', $subject['id']),
                                                        'modal_title' => 'تعديل المادة الدراسية',
                                                        'stage_id' => $subject['stage_id'],
                                                        'name' => $subject['name'],
                                                        'description' => $subject['description'],
                                                        'order' => $subject['order'],
                                                        'active' => $subject['active'],
                                                    ], JSON_UNESCAPED_UNICODE) }}">
                                                <x-icon name="pencil" :size="12" /> تعديل
                                            </button>
                                            <form method="POST" action="{{ route('admin.subjects.destroy', $subject['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف مادة {{ $subject['name'] }}؟')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm" type="submit">
                                                    <x-icon name="trash-2" :size="12" />
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <div class="modal-overlay" id="subjectModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة مادة دراسية</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" id="subjectForm" method="POST" action="{{ route('admin.subjects.store') }}" data-store-action="{{ route('admin.subjects.store') }}">
                @csrf
                <div>
                    <label class="label">المرحلة الدراسية *</label>
                    <select name="stage_id" id="subjectStageSelect" class="input" required>
                        <option value="">— اختر المرحلة —</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage['id'] }}" @selected($activeStage === $stage['id'])>{{ $stage['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">اسم المادة (يمكنك كتابة أكثر من مادة مفصولة بفاصلة للإضافة السريعة) *</label>
                    <input name="name" class="input" placeholder="مثال: رياضيات، فيزياء، كيمياء، لغة إنكليزية" required>
                </div>
                <div>
                    <label class="label">وصف المادة (اختياري)</label>
                    <textarea name="description" class="input min-h-[70px]" placeholder="ملاحظات أو وصف مختصر للمنهج..."></textarea>
                </div>
                <div>
                    <label class="label">الترتيب</label>
                    <input name="order" type="number" min="1" class="input" value="1">
                </div>
                <label class="flex items-center gap-2 rounded-xl bg-primary/4 p-3">
                    <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-primary/20">
                    <span class="text-xs font-extrabold text-primary">المادة فعالة ومتاحة للكادر والكورسات</span>
                </label>
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

@push('scripts')
<script>
    function openAddSubjectForStage(stageId, stageName) {
        var modal = document.getElementById('subjectModal');
        var form = document.getElementById('subjectForm');
        if (!modal || !form) return;
        form.reset();
        form.action = form.getAttribute('data-store-action');
        var methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) methodInput.remove();
        var titleEl = modal.querySelector('[data-modal-title]');
        if (titleEl) titleEl.textContent = 'إضافة مادة لمرحلة ' + stageName;
        var select = document.getElementById('subjectStageSelect');
        if (select) select.value = stageId;
        modal.classList.add('open');
    }
</script>
@endpush

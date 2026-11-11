@extends('layouts.admin')

@section('title', 'إدارة أولياء الأمور')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div>
                <h1 class="text-2xl font-extrabold">إدارة أولياء الأمور</h1>
                <p class="text-xs text-primary/60">إضافة وتعديل حسابات الآباء وربط كل ولي أمر بابن واحد أو أكثر</p>
            </div>
            <span class="chip bg-primary/5 text-primary/70 mr-auto">{{ $parents->count() }} ولي أمر</span>
            <button class="btn btn-dark" data-modal-open="parentModal" data-add="parentModal" data-add-title="إضافة ولي أمر جديد">
                <x-icon name="plus" :size="16" />
                <span> إضافة ولي أمر</span>
            </button>
        </div>

        <div class="mb-4">
            <input class="input" placeholder="بحث باسم ولي الأمر أو الهاتف أو اسم الابن..." data-filter-input="#parentsList" data-filter-empty="#parentsEmpty">
        </div>

        <div id="parentsList" class="space-y-2.5">
            @foreach ($parents as $parent)
                @php
                    $childrenNames = collect($parent['children'] ?? [])->pluck('name')->implode(' ');
                    $editPayload = [
                        'action' => route('admin.parents.update', $parent['id']),
                        'modal_title' => 'تعديل بيانات ولي الأمر وربط الأبناء',
                        'name' => $parent['name'],
                        'phone' => $parent['phone'],
                        'username' => $parent['username'],
                        'active' => $parent['active'],
                        'student_ids' => $parent['student_ids'] ?? [],
                    ];
                @endphp
                <div class="card flex flex-wrap items-center gap-3 p-4" data-filter-item
                     data-search="{{ $parent['name'].' '.$parent['username'].' '.$parent['phone'].' '.$childrenNames }}">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                        <x-icon name="users" :size="22" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-extrabold text-primary">{{ $parent['name'] }}</span>
                            @if ($parent['active'])
                                <span class="chip bg-emerald-50 text-emerald-700">نشط</span>
                            @else
                                <span class="chip bg-rose-50 text-rose-600">موقوف</span>
                            @endif
                            <span class="chip bg-indigo-50 text-indigo-700">{{ $parent['children_count'] }} أبناء مرتبطين</span>
                        </div>

                        <div class="mt-1 text-xs text-primary/60">
                            <span>اسم الدخول: </span><b dir="ltr">{{ $parent['username'] }}</b>
                            @if ($parent['phone'])
                                <span> • الهاتف: </span><b dir="ltr">{{ $parent['phone'] }}</b>
                            @endif
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            @forelse ($parent['children'] as $child)
                                <span class="inline-flex items-center gap-1 rounded-lg border border-primary/10 bg-primary/[0.03] px-2.5 py-1 text-[11px] font-bold text-primary">
                                    <x-icon name="graduation-cap" :size="12" class="text-gold" />
                                    <span>{{ $child['name'] }}</span>
                                    <span class="text-[10px] text-primary/50">(#{{ $child['student_no'] }} • {{ $child['stage_name'] }})</span>
                                </span>
                            @empty
                                <span class="chip bg-amber-50 text-amber-700">لم يُربط بأي طالب بعد — اضغط تعديل لربط الأبناء</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <form method="POST" action="{{ route('chat.start') }}" class="inline">
                            @csrf
                            <input type="hidden" name="target_user_id" value="{{ $parent['id'] }}">
                            <button type="submit" class="btn btn-outline btn-sm" title="مراسلة ولي الأمر">
                                <x-icon name="message-square" :size="13" /> مراسلة
                            </button>
                        </form>

                        <button class="btn btn-outline btn-sm" data-modal="parentModal" data-edit="{{ json_encode($editPayload, JSON_UNESCAPED_UNICODE) }}">
                            <x-icon name="pencil" :size="13" /> تعديل وربط الأبناء
                        </button>

                        <form method="POST" action="{{ route('admin.parents.destroy', $parent['id']) }}" class="inline"
                              onsubmit="return confirm('هل أنت متأكد من حذف حساب ولي الأمر {{ $parent['name'] }}؟')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit" title="حذف">
                                <x-icon name="trash-2" :size="13" />
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($parents->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-10 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">👨‍👧‍👦</div>
                <p class="text-sm font-bold text-primary/60">لا توجد حسابات أولياء أمور مضافة بعد</p>
                <button class="btn btn-dark btn-sm mt-2" data-modal-open="parentModal" data-add="parentModal" data-add-title="إضافة ولي أمر جديد">
                    <x-icon name="plus" :size="14" /> إضافة أول ولي أمر
                </button>
            </div>
        @endif

        <div id="parentsEmpty" class="is-hidden card flex flex-col items-center gap-2 px-6 py-10 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
            <p class="text-sm font-bold text-primary/60">لا توجد نتائج مطابقة للبحث</p>
        </div>
    </div>

    <div class="modal-overlay" id="parentModal">
        <div class="modal-panel modal-lg">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة ولي أمر جديد</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-4" method="POST" action="{{ route('admin.parents.store') }}" data-store-action="{{ route('admin.parents.store') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">اسم ولي الأمر الكامل *</label>
                        <input name="name" class="input" placeholder="مثال: محمد علي حسن" required>
                    </div>
                    <div>
                        <label class="label">رقم الهاتف (يُستخدم للدخول أيضاً)</label>
                        <input name="phone" class="input" placeholder="07XXXXXXXXX" inputmode="tel">
                    </div>
                    <div>
                        <label class="label">اسم المستخدم (اختياري — يُعتمد رقم الهاتف إن تُرك فارغاً)</label>
                        <input name="username" class="input" placeholder="مثال: parent_mohammed أو رقم الهاتف">
                    </div>
                    <div>
                        <label class="label">كلمة المرور (تُترك فارغة للإبقاء على الحالية أو 123456 للجديد)</label>
                        <input name="password" type="password" class="input" placeholder="••••••">
                    </div>
                </div>

                <div class="rounded-2xl border border-primary/10 bg-primary/[0.02] p-3.5">
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <label class="label !mb-0">الأبناء (الطلاب المرتبطون بولي الأمر)</label>
                            <p class="text-[11px] text-primary/55">يمكنك اختيار طالب واحد أو عدة طلاب ليظهروا في حساب ولي الأمر.</p>
                        </div>
                        <input type="text" id="parentStudentsSearch" class="input max-w-[14rem] !py-1.5 text-xs"
                               placeholder="بحث سريع باسم الطالب أو رقمه..."
                               oninput="filterParentStudents(this.value)">
                    </div>

                    @if ($students->isEmpty())
                        <div class="rounded-xl border border-dashed border-primary/15 p-4 text-center text-xs text-primary/50">
                            لا يوجد طلاب مسجلون حالياً. يمكنك إضافة الطلاب أولاً من قسم الطلاب.
                        </div>
                    @else
                        <div class="grid max-h-56 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-2" id="parentStudentsChecklist">
                            @foreach ($students as $student)
                                <label class="parent-student-option flex cursor-pointer items-center gap-2.5 rounded-xl border border-primary/8 bg-white px-3 py-2 text-xs shadow-sm transition hover:border-primary/25"
                                       data-student-search="{{ mb_strtolower($student['name'].' '.$student['student_no'].' '.$student['stage_name'].' '.$student['phone']) }}">
                                    <input type="checkbox" name="student_ids[]" value="{{ $student['id'] }}" class="h-4 w-4 rounded border-primary/25 text-primary">
                                    <div class="min-w-0 flex-1 leading-tight">
                                        <div class="truncate font-extrabold text-primary">{{ $student['name'] }}</div>
                                        <div class="mt-0.5 text-[10px] text-primary/55">
                                            رقم: <b>{{ $student['student_no'] }}</b> • {{ $student['stage_name'] }}
                                            @if ($student['parent'] && $student['parent'] !== '—')
                                                • ولي الأمر الحالي: {{ $student['parent'] }}
                                            @endif
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="active" value="1" checked class="h-4 w-4 rounded border-primary/20">
                    <span class="text-xs font-extrabold text-primary">الحساب نشط (يسمح لولي الأمر بتسجيل الدخول ومتابعة أبنائه)</span>
                </label>

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ البيانات والربط
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function filterParentStudents(query) {
            var q = (query || '').trim().toLowerCase();
            document.querySelectorAll('#parentStudentsChecklist .parent-student-option').forEach(function (item) {
                var hay = (item.getAttribute('data-student-search') || '').toLowerCase();
                if (!q || hay.indexOf(q) !== -1) {
                    item.classList.remove('is-hidden');
                } else {
                    item.classList.add('is-hidden');
                }
            });
        }
    </script>
    @endpush
@endsection


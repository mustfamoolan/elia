@extends('layouts.admin')

@section('title', 'الكادر التدريسي')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الكادر التدريسي وتوزيع الدروس</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $staff->count() }} مدرس</span>
            <button class="btn btn-dark btn-sm" data-modal-open="staffModal" data-add="staffModal" data-add-title="إضافة مدرس وتعيين دروسه">
                <x-icon name="plus" :size="14" /> إضافة مدرس
            </button>
        </div>

        <div class="card mb-4 flex flex-wrap items-center gap-3 p-4">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <x-icon name="user-round" :size="20" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-extrabold text-primary">ظهور قسم الكادر في الموقع</div>
                <div class="text-[11px] text-primary/60">عند التفعيل يظهر الكادر التدريسي في الصفحة العامة.</div>
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
                                {{ $member['subject'] ?: 'لم تحدد المادة' }} • {{ $member['stage'] ?: 'لم تحدد المرحلة' }}
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                <span class="chip bg-blue-50 text-blue-700 text-[10px]">{{ $member['courses_count'] ?? 0 }} دورة/درس</span>
                                <span class="chip bg-emerald-50 text-emerald-700 text-[10px]">{{ $member['students_count'] ?? 0 }} طالب</span>
                                <span class="chip bg-primary/5 text-primary/60 text-[10px]">المستخدم: {{ $member['username'] }}</span>
                            </div>
                        </div>
                    </div>

                    @if (! empty($member['courses_list']))
                        <div class="mb-3 rounded-xl bg-primary/3 p-2.5">
                            <div class="mb-1.5 text-[10px] font-extrabold text-primary/70">الدروس والدورات المسندة للمدرس:</div>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($member['courses_list'] as $c)
                                    <span class="chip bg-white border border-primary/10 text-primary text-[10px]">
                                        {{ $c['name'] }} ({{ $c['stage_name'] }} • {{ $c['students_count'] }} طالب)
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mb-3 rounded-xl bg-amber-50 p-2.5 text-[10px] font-bold text-amber-700">
                            لم يتم إسناد دورات أو مراحل لهذا المدرس بعد — اضغط تعديل لتحديد دروسه ومراحله.
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
                                'modal_title' => 'تعديل بيانات المدرس وتوزيع دروسه',
                                'name' => $member['name'],
                                'username' => $member['username'],
                                'subject' => $member['subject'],
                                'stage' => $member['stage'],
                                'phone' => $member['phone'],
                                'bio' => $member['bio'],
                                'course_ids' => $member['course_ids'] ?? [],
                                'stage_ids' => $member['stage_ids'] ?? [],
                            ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" /> تعديل الدروس
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
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة مدرس</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.staff.store') }}" data-store-action="{{ route('admin.staff.store') }}">
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
                        <label class="label">اسم المستخدم *</label>
                        <input name="username" class="input" placeholder="مثال: hussein" required>
                    </div>
                    <div>
                        <label class="label">كلمة المرور</label>
                        <input name="password" type="password" class="input" placeholder="اتركه فارغاً عند التعديل">
                    </div>
                    <div>
                        <label class="label">المادة أو المواد (افصل بفاصلة لأكثر من مادة) *</label>
                        <input name="subject" class="input" placeholder="مثال: رياضيات، فيزياء">
                    </div>
                    <div>
                        <label class="label">وصف المرحلة الظاهر (اختياري — يُملأ تلقائياً)</label>
                        <input name="stage" class="input" placeholder="مثال: متوسط وإعدادي">
                    </div>
                </div>

                <div class="rounded-xl border border-primary/10 bg-primary/2 p-3">
                    <label class="label mb-1">المراحل الدراسية التي يدرّسها (يمكن اختيار مرحلة أو أكثر)</label>
                    <p class="mb-2 text-[10px] text-primary/60">
                        عند اختيار المرحلة وكتابة المادة أعلاه، سيتم تلقائياً ربط أو إنشاء درس المدرس في هذه المراحل وتوجيه طلابها إليه.
                    </p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($stages as $st)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-primary/10 bg-white px-2.5 py-2 text-xs font-bold text-primary hover:border-primary/30">
                                <input type="checkbox" name="stage_ids[]" value="{{ $st['id'] }}" class="h-4 w-4 rounded border-primary/20">
                                <span>{{ $st['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                @if ($courses->isNotEmpty())
                    <div class="rounded-xl border border-primary/10 bg-primary/2 p-3">
                        <label class="label mb-1">الدورات / الدروس المسندة للمدرس (يمكن اختيار درس أو أكثر)</label>
                        <p class="mb-2 text-[10px] text-primary/60">
                            اختر الدورات الموجودة التي يدرّسها هذا الأستاذ ليظهر له طلابها وواجباتها وسجل حضورها ودرجاتها فقط.
                        </p>
                        <div class="grid max-h-44 grid-cols-1 gap-1.5 overflow-y-auto sm:grid-cols-2">
                            @foreach ($courses as $course)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-primary/10 bg-white px-2.5 py-2 text-xs hover:border-primary/30">
                                    <input type="checkbox" name="course_ids[]" value="{{ $course['id'] }}" class="h-4 w-4 rounded border-primary/20">
                                    <span class="min-w-0 flex-1 truncate font-bold text-primary">
                                        {{ $course['name'] }}
                                        <span class="text-[10px] font-normal text-primary/60">({{ $course['stage_name'] }})</span>
                                    </span>
                                    @if ($course['teacher'] && $course['teacher'] !== '—')
                                        <span class="chip bg-primary/5 text-[9px] text-primary/60">{{ $course['teacher'] }}</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <label class="label">نبذة</label>
                    <textarea name="bio" class="input min-h-[65px]" placeholder="نبذة مختصرة عن المدرس..."></textarea>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ وتحديث التوزيع
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

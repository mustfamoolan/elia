@extends('layouts.admin')

@section('title', 'المراحل الدراسية')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">المراحل الدراسية</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $stages->count() }} مرحلة</span>
            <button class="btn btn-dark btn-sm" data-modal-open="stageModal" data-add="stageModal" data-add-title="إضافة مرحلة">
                <x-icon name="plus" :size="14" /> إضافة مرحلة
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($stages as $stage)
                <div class="card p-4">
                    <div class="mb-3 flex items-start gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                            <x-icon :name="$stage['icon']" :size="22" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-base font-extrabold text-primary">{{ $stage['name'] }}</div>
                            <div class="text-[11px] text-primary/50">الترتيب: {{ $stage['order'] }}</div>
                        </div>
                        <button class="toggle" aria-checked="{{ $stage['visible'] ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.stages.toggle', $stage['id']) }}"
                                data-toggle-toast="تم تحديث ظهور المرحلة" aria-label="إظهار المرحلة"></button>
                    </div>
                    <p class="mb-3 text-xs leading-relaxed text-primary/70">{{ $stage['description'] }}</p>
                    <div class="flex items-center gap-2 border-t border-primary/5 pt-3">
                        <span class="chip bg-primary/5 text-primary">{{ $stage['courses'] }} دورات</span>
                        <div class="mr-auto flex items-center gap-1">
                            <button class="btn btn-outline btn-sm" data-modal="stageModal" data-edit="{{ json_encode([
                                'action' => route('admin.stages.update', $stage['id']),
                                'modal_title' => 'تعديل المرحلة',
                                'name' => $stage['name'],
                                'description' => $stage['description'],
                                'order' => $stage['order'],
                                'visible' => $stage['visible'],
                            ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" /> تعديل
                            </button>
                            <form method="POST" action="{{ route('admin.stages.destroy', $stage['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف مرحلة {{ $stage['name'] }}؟')">
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

    <div class="modal-overlay" id="stageModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة مرحلة</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.stages.store') }}" data-store-action="{{ route('admin.stages.store') }}">
                @csrf
                <div>
                    <label class="label">اسم المرحلة *</label>
                    <input name="name" class="input" placeholder="مثال: سادس إعدادي" required>
                </div>
                <div>
                    <label class="label">وصف المرحلة</label>
                    <textarea name="description" class="input min-h-[80px]" placeholder="وصف مختصر للمرحلة..."></textarea>
                </div>
                <div>
                    <label class="label">ترتيب العرض</label>
                    <input name="order" type="number" min="1" class="input" placeholder="1">
                </div>
                <label class="flex items-center gap-2 rounded-xl bg-primary/4 p-3">
                    <input type="checkbox" name="visible" value="1" checked class="h-4 w-4 rounded border-primary/20">
                    <span class="text-xs font-extrabold text-primary">إظهار المرحلة في الموقع</span>
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

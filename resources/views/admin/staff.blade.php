@extends('layouts.admin')

@section('title', 'الكادر التدريسي')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الكادر التدريسي</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $staff->count() }} مدرس</span>
            <button class="btn btn-dark btn-sm" data-modal-open="staffModal" data-add="staffModal" data-add-title="إضافة مدرس">
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
                <div class="card p-4">
                    <div class="mb-3 flex items-start gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-base font-extrabold text-primary">
                            {{ mb_substr($member['name'], 3, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-extrabold text-primary">{{ $member['name'] }}</div>
                            <div class="text-[11px] text-primary/50">{{ $member['subject'] }} • {{ $member['stage'] }}</div>
                        </div>
                    </div>
                    <p class="mb-3 text-[11px] leading-relaxed text-primary/60">{{ $member['bio'] }}</p>
                    <div class="flex items-center gap-2 border-t border-primary/5 pt-3">
                        <a href="tel:+964{{ ltrim($member['phone'], '0') }}" class="chip bg-primary/5 text-primary">
                            <x-icon name="phone" :size="12" /> {{ $member['phone'] }}
                        </a>
                        <div class="mr-auto flex items-center gap-1">
                            <button class="btn btn-outline btn-sm" data-modal="staffModal" data-edit="{{ json_encode([
                                'action' => route('admin.staff.update', $member['id']),
                                'modal_title' => 'تعديل بيانات المدرس',
                                'name' => $member['name'],
                                'username' => $member['username'],
                                'subject' => $member['subject'],
                                'stage' => $member['stage'],
                                'phone' => $member['phone'],
                                'bio' => $member['bio'],
                            ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" />
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
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة مدرس</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.staff.store') }}" data-store-action="{{ route('admin.staff.store') }}">
                @csrf
                <div>
                    <label class="label">اسم المدرس *</label>
                    <input name="name" class="input" placeholder="مثال: أ. حسين الكعبي" required>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">اسم المستخدم *</label>
                        <input name="username" class="input" placeholder="مثال: hussein" required>
                    </div>
                    <div>
                        <label class="label">كلمة المرور</label>
                        <input name="password" type="password" class="input" placeholder="اتركه فارغاً عند التعديل">
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">المادة</label>
                        <input name="subject" class="input" placeholder="رياضيات">
                    </div>
                    <div>
                        <label class="label">المرحلة</label>
                        <input name="stage" class="input" placeholder="إعدادي">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">رقم الهاتف</label>
                        <input name="phone" class="input" placeholder="07XXXXXXXXX" inputmode="tel">
                    </div>
                </div>
                <div>
                    <label class="label">نبذة</label>
                    <textarea name="bio" class="input min-h-[80px]" placeholder="نبذة مختصرة عن المدرس..."></textarea>
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

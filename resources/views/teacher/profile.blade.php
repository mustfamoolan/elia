@extends('layouts.teacher')

@section('title', 'الملف الشخصي')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الملف الشخصي</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $user->username }}</span>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="card p-5">
                <div class="mb-3 flex items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                        <x-icon name="circle-user" :size="30" />
                    </div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-extrabold text-primary">{{ $user->name }}</div>
                        <div class="text-[11px] text-primary/50">{{ $user->roleLabel() }}</div>
                    </div>
                </div>
                <div class="space-y-2 text-[11px]">
                    <div class="flex items-center justify-between rounded-xl bg-primary/5 px-3 py-2">
                        <span class="font-bold text-primary/60">اسم المستخدم</span>
                        <span class="font-extrabold text-primary">{{ $user->username }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-primary/5 px-3 py-2">
                        <span class="font-bold text-primary/60">المادة</span>
                        <span class="font-extrabold text-primary">{{ $user->staff?->subject ?: '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl bg-primary/5 px-3 py-2">
                        <span class="font-bold text-primary/60">آخر دخول</span>
                        <span class="font-extrabold text-primary">{{ $user->last_login_at?->format('Y/m/d H:i') ?? '—' }}</span>
                    </div>
                </div>
            </section>

            <section class="card p-5 lg:col-span-2">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                    <x-icon name="pencil" :size="16" class="text-primary" /> تعديل البيانات
                </h2>
                <form method="POST" action="{{ route('teacher.profile.update') }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">الاسم الكامل *</label>
                            <input name="name" class="input" value="{{ old('name', $user->name) }}" required>
                        </div>
                        <div>
                            <label class="label">رقم الهاتف</label>
                            <input name="phone" class="input" value="{{ old('phone', $user->phone ?? $user->staff?->phone) }}" inputmode="tel">
                        </div>
                    </div>
                    <div>
                        <label class="label">المادة التدريسية</label>
                        <input name="subject" class="input" value="{{ old('subject', $user->staff?->subject) }}" placeholder="مثال: الرياضيات">
                    </div>
                    <div>
                        <label class="label">نبذة تعريفية</label>
                        <textarea name="bio" class="input min-h-[90px]" placeholder="نبذة تظهر في صفحة الكادر...">{{ old('bio', $user->staff?->bio) }}</textarea>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">كلمة مرور جديدة</label>
                            <input type="password" name="password" class="input" placeholder="اتركها فارغة لعدم التغيير">
                        </div>
                        <div>
                            <label class="label">تأكيد كلمة المرور</label>
                            <input type="password" name="password_confirmation" class="input" placeholder="أعد كتابة كلمة المرور">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-dark">
                        <x-icon name="save" :size="16" /> حفظ التغييرات
                    </button>
                </form>
            </section>
        </div>
    </div>
@endsection

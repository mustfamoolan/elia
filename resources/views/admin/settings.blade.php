@extends('layouts.admin')

@section('title', 'الإعدادات')

@section('content')
    @php
        $institute = $institute ?? \App\Support\Eyal::institute();
    @endphp

    <div class="fade-in space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الإعدادات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">إعدادات المعهد العامة</span>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="card p-5">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                    <x-icon name="school" :size="17" class="text-primary" /> بيانات المعهد
                </h2>
                <form class="space-y-3" method="POST" action="{{ route('admin.settings.institute') }}">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="label">اسم المعهد</label>
                        <input name="name" class="input" value="{{ $institute['name'] }}" required>
                    </div>
                    <div>
                        <label class="label">العنوان</label>
                        <input name="address" class="input" value="{{ $institute['address'] }}">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label">رقم الهاتف</label>
                            <input name="phone" class="input" value="{{ $institute['phone'] }}" inputmode="tel">
                        </div>
                        <div>
                            <label class="label">رقم الواتساب</label>
                            <input name="whatsapp" class="input" value="{{ $institute['whatsapp'] }}" inputmode="tel">
                        </div>
                    </div>
                    <div>
                        <label class="label">أوقات الدوام</label>
                        <input name="work_hours" class="input" value="{{ $institute['work_hours'] }}">
                    </div>
                    <div>
                        <label class="label">شريط الإعلان أعلى الموقع</label>
                        <textarea name="announcement" class="input min-h-[70px]">{{ $institute['announcement'] }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-dark w-full">
                        <x-icon name="save" :size="16" /> حفظ الإعدادات
                    </button>
                </form>
            </section>

            <div class="space-y-4">
                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="palette" :size="17" class="text-primary" /> ألوان الواجهة
                    </h2>
                    <form method="POST" action="{{ route('admin.settings.theme') }}">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label">اللون الأساسي</label>
                                <input type="color" name="primary_color" class="input h-11 p-1" value="{{ $institute['primary_color'] }}" data-color-input="--app-primary">
                            </div>
                            <div>
                                <label class="label">اللون الثانوي</label>
                                <input type="color" name="secondary_color" class="input h-11 p-1" value="{{ $institute['secondary_color'] }}" data-color-input="--app-secondary">
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button type="button" class="btn btn-outline btn-sm flex-1" data-reset-colors>
                                <x-icon name="refresh-cw" :size="13" /> استعادة الافتراضي
                            </button>
                            <button type="submit" class="btn btn-dark btn-sm flex-1">
                                <x-icon name="save" :size="13" /> حفظ
                            </button>
                        </div>
                    </form>
                </section>

                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="toggle-right" :size="17" class="text-primary" /> خيارات الظهور
                    </h2>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">قسم الكادر التدريسي</div>
                                <div class="text-[10px] text-primary/50">إظهار صفحة الكادر في الموقع</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['staff_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.staff.toggle') }}"
                                    data-toggle-toast="تم تحديث خيار الكادر" aria-label="الكادر"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">بوابة ولي الأمر (الأهل)</div>
                                <div class="text-[10px] text-primary/50">إظهار صفحة ولي الأمر في الموقع</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['parent_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.parent_enabled']) }}"
                                    data-toggle-toast="تم تحديث خيار ولي الأمر" aria-label="ولي الأمر"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text primaire">بوابة الطالب</div>
                                <div class="text-[10px] text-primary/50">إظهار صفحة الطالب في الموقع</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['student_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.student_enabled']) }}"
                                    data-toggle-toast="تم تحديث خيار الطالب" aria-label="طالب"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">شريط الإعلان</div>
                                <div class="text-[10px] text-primary/50">إظهار الشريط أعلى الصفحة</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['announcement_bar'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.announcement_bar']) }}"
                                    data-toggle-toast="تم تحديث شريط الإعلان" aria-label="شريط الإعلان"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">استقبال التسجيلات</div>
                                <div class="text-[10px] text-primary/50">فتح/إغلاق استقبال طلبات التسجيل</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['registrations_open'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.registrations_open']) }}"
                                    data-toggle-toast="تم تحديث استقبال التسجيلات" aria-label="التسجيل"></button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <section class="card overflow-hidden">
            <div class="flex items-center gap-2 border-b border-primary/5 px-5 py-3">
                <h2 class="text-sm font-extrabold">حسابات الدخول</h2>
                <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $users->count() }} حساب</span>
                <button class="btn btn-outline btn-sm" data-modal-open="userModal" data-add="userModal" data-add-title="إضافة حساب دخول">
                    <x-icon name="plus" :size="13" /> إضافة
                </button>
            </div>
            <div class="table-wrap" style="border:0;border-radius:0">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الدور</th>
                        <th>الحالة</th>
                        <th>تاريخ الإنشاء</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td class="font-extrabold text-primary">{{ $user['name'] }}</td>
                            <td class="text-[11px] text-primary/60">{{ $user['username'] }}</td>
                            <td>
                                <span class="chip {{ $user['role'] === 'admin' ? 'bg-primary/8 text-primary' : ($user['role'] === 'parent' ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700') }}">
                                    {{ $user['role_label'] }}
                                </span>
                            </td>
                            <td>
                                <span class="chip {{ $user['active'] ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $user['active'] ? 'نشط' : 'موقوف' }}
                                </span>
                            </td>
                            <td class="text-[11px] text-primary/50">{{ $user['created_at'] }}</td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <button class="btn btn-outline btn-sm" data-modal="userModal" data-edit="{{ json_encode([
                                        'action' => route('admin.users.update', $user['id']),
                                        'modal_title' => 'تعديل حساب الدخول',
                                        'name' => $user['name'],
                                        'username' => $user['username'],
                                        'role' => $user['role'],
                                        'phone' => $user['phone'],
                                        'active' => $user['active'],
                                    ], JSON_UNESCAPED_UNICODE) }}">
                                        <x-icon name="pencil" :size="12" />
                                    </button>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف الحساب {{ $user['username'] }}؟')">
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
        </section>

        <section class="card p-5">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                <x-icon name="layers" :size="17" class="text-primary" /> ظهور المراحل
            </h2>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($stages as $stage)
                    <div class="flex items-center gap-2 rounded-xl border border-primary/5 p-3">
                        <span class="text-xs font-extrabold text-primary">{{ $stage['name'] }}</span>
                        <button class="toggle mr-auto" aria-checked="{{ $stage['visible'] ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.stages.toggle', $stage['id']) }}"
                                data-toggle-toast="تم تحديث ظهور {{ $stage['name'] }}" aria-label="ظهور المرحلة"></button>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="card flex flex-wrap items-center gap-3 border-r-4 border-r-emerald-400 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                <x-icon name="database" :size="20" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-xs font-extrabold text-primary">قاعدة البيانات متصلة</div>
                <div class="text-[10px] leading-relaxed text-primary/50">
                    جميع الإعدادات والحسابات والبيانات محفوظة في قاعدة بيانات MySQL، وأي تعديل يظهر فوراً في الموقع العام.
                </div>
            </div>
            <span class="chip bg-emerald-50 text-emerald-700">MySQL</span>
        </section>
    </div>

    <div class="modal-overlay" id="userModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة حساب دخول</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.users.store') }}" data-store-action="{{ route('admin.users.store') }}">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">الاسم *</label>
                        <input name="name" class="input" placeholder="الاسم الكامل" required>
                    </div>
                    <div>
                        <label class="label">اسم المستخدم *</label>
                        <input name="username" class="input" placeholder="مثال: ali" required>
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="label">كلمة المرور</label>
                        <input name="password" type="password" class="input" placeholder="اتركه فارغاً عند التعديل">
                    </div>
                    <div>
                        <label class="label">الدور *</label>
                        <select name="role" class="input" required>
                            <option value="student">طالب</option>
                            <option value="parent">ولي أمر</option>
                            <option value="staff">مدرس</option>
                            <option value="admin">مدير</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="label">رقم الهاتف</label>
                    <input name="phone" class="input" placeholder="07XXXXXXXXX" inputmode="tel">
                </div>
                <label class="flex items-center gap-2 text-xs font-bold text-primary/70">
                    <input type="checkbox" name="active" value="1" class="h-4 w-4 rounded border-primary/20" checked>
                    حساب نشط
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

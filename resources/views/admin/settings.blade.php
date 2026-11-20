@extends('layouts.admin')

@section('title', 'الإعدادات')

@section('content')
    @php
        $institute = $institute ?? \App\Support\Eyal::institute();
        $activeTab = $activeTab ?? 'general';
        $tabs = [
            ['general', 'بيانات المعهد والألوان', 'school'],
            ['features', 'أقسام الموقع والشات', 'toggle-right'],
            ['ai', 'الذكاء الاصطناعي (AI)', 'sparkles'],
            ['whatsapp', 'بوابة الواتساب و QR', 'whatsapp'],
            ['push', 'إشعارات Firebase & PWA', 'bell'],
            ['users', 'حسابات المستخدمين', 'users'],
        ];
    @endphp

    <div class="fade-in space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الإعدادات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">إعدادات النظام المقسّمة حسب الاختصاص</span>
        </div>

        {{-- شريط أزرار التنقل الفوري بين أقسام الإعدادات (بدون ريلود) --}}
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" id="settingsTabNav">
            @foreach ($tabs as [$slug, $label, $icon])
                @php $isAct = $activeTab === $slug; @endphp
                <a href="{{ route('admin.settings', $slug) }}"
                   data-settings-tab="{{ $slug }}"
                   onclick="return switchSettingsTab('{{ $slug }}', this.href);"
                   class="card flex items-center gap-2 p-3 text-xs font-extrabold transition {{ $isAct ? 'bg-primary text-white shadow-soft' : 'text-primary hover:bg-primary/5' }}">
                    <x-icon :name="$icon" :size="16" class="shrink-0" />
                    <span class="truncate">{{ $label }}</span>
                </a>
            @endforeach
        </div>

        {{-- ===============================================================
             1. بيانات المعهد والألوان
        =============================================================== --}}
        <div data-settings-panel="general" class="{{ $activeTab === 'general' ? '' : 'hidden' }}">
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
                            <label class="label">ساعات العمل</label>
                            <input name="work_hours" class="input" value="{{ $institute['work_hours'] }}">
                        </div>
                        <div>
                            <label class="label">شريط الإعلان العلوي</label>
                            <input name="announcement" class="input" value="{{ $institute['announcement'] }}">
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
            </div>

        {{-- ===============================================================
             2. أقسام الموقع والشات
        =============================================================== --}}
        </div>
        <div data-settings-panel="features" class="{{ $activeTab === 'features' ? '' : 'hidden' }}">
            <div class="grid gap-4 lg:grid-cols-2">
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
                                <div class="text-xs font-extrabold text-primary">بوابة أولياء الأمور</div>
                                <div class="text-[10px] text-primary/50">إظهار صفحة ولي الأمر</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['parent_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.parent_enabled']) }}"
                                    data-toggle-toast="تم تحديث بوابة أولياء الأمور" aria-label="ولي الأمر"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">حساب الطالب</div>
                                <div class="text-[10px] text-primary/50">إتاحة دخول الطالب لحسابه</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['student_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.student_enabled']) }}"
                                    data-toggle-toast="تم تحديث حساب الطالب" aria-label="حساب الطالب"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">شريط الإعلانات</div>
                                <div class="text-[10px] text-primary/50">عرض الإعلان في أعلى الصفحات</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['announcement_bar'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.announcement_bar']) }}"
                                    data-toggle-toast="تم تحديث شريط الإعلان" aria-label="الإعلان"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">استقبال طلبات التسجيل</div>
                                <div class="text-[10px] text-primary/50">فتح استمارة تسجيل الطلاب الجدد</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['registrations_open'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.registrations_open']) }}"
                                    data-toggle-toast="تم تحديث حالة التسجيل" aria-label="التسجيل"></button>
                        </div>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="message-square" :size="17" class="text-primary" /> خيارات نظام المحادثات والدعم
                    </h2>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">نظام المحادثات العام</div>
                                <div class="text-[10px] text-primary/50">تشغيل أو إيقاف المحادثات في الموقع بالكامل</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['chat_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_enabled']) }}"
                                    data-toggle-toast="تم تحديث نظام المحادثات" aria-label="المحادثات"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">شات أولياء الأمور مع الإدارة</div>
                                <div class="text-[10px] text-primary/50">السماح لأولياء الأمور بمراسلة إدارة المعهد</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['chat_parent_admin_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_parent_admin_enabled']) }}"
                                    data-toggle-toast="تم تحديث شات الإدارة مع الأهل" aria-label="شات الإدارة والأهل"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">شات أولياء الأمور مع المدرسين</div>
                                <div class="text-[10px] text-primary/50">السماح لأولياء الأمور بمراسلة مدرسي أبنائهم</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['chat_parent_teacher_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_parent_teacher_enabled']) }}"
                                    data-toggle-toast="تم تحديث شات المدرسين مع الأهل" aria-label="شات المدرسين والأهل"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">شات الكادر التدريسي مع الإدارة</div>
                                <div class="text-[10px] text-primary/50">التواصل الداخلي بين المدرسين والإدارة</div>
                            </div>
                            <button class="toggle" aria-checked="{{ $institute['chat_staff_admin_enabled'] ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_staff_admin_enabled']) }}"
                                    data-toggle-toast="تم تحديث شات الكادر مع الإدارة" aria-label="شات الكادر والإدارة"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">كروب أولياء الأمور والإدارة</div>
                                <div class="text-[10px] text-primary/50">إظهار أو إخفاء مجموعة أولياء الأمور المعزولة عن الكادر</div>
                            </div>
                            <button class="toggle" aria-checked="{{ ($institute['chat_parents_group_enabled'] ?? true) ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_parents_group_enabled']) }}"
                                    data-toggle-toast="تم تحديث كروب أولياء الأمور" aria-label="كروب أولياء الأمور"></button>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">كروب الكادر التدريسي والإدارة</div>
                                <div class="text-[10px] text-primary/50">إظهار أو إخفاء مجموعة المدرسين المعزولة عن أولياء الأمور</div>
                            </div>
                            <button class="toggle" aria-checked="{{ ($institute['chat_staff_group_enabled'] ?? true) ? 'true' : 'false' }}"
                                    data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_staff_group_enabled']) }}"
                                    data-toggle-toast="تم تحديث كروب الكادر" aria-label="كروب الكادر"></button>
                        </div>
                    </div>
                </section>
            </div>

        {{-- ===============================================================
             3. الذكاء الاصطناعي (Google Gemini AI)
        =============================================================== --}}
        </div>
        <div data-settings-panel="ai" class="{{ $activeTab === 'ai' ? '' : 'hidden' }}">
            <section class="card border-2 border-blue-500/15 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold text-primary">
                        <span>✨</span> المساعد الذكي (Google Gemini AI)
                    </h2>
                    <span class="chip {{ ($institute['ai_has_key'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ ($institute['ai_has_key'] ?? false) ? 'مربوط وجاهز' : 'بانتظار مفتاح API' }}
                    </span>
                </div>
                <p class="mb-3 text-[11px] leading-relaxed text-primary/65">
                    يقوم المساعد الذكي بقراءة رسائل وصور أولياء الأمور في الجات، وتقسيم الواجبات تلقائياً وتوجيه كل مادة لمدرسها المختص، بالإضافة إلى الرد الآلي الذكي على استفسارات الأهالي حول درجات وحضور وأقساط أبنائهم.
                </p>

                <form method="POST" action="{{ route('admin.settings.ai') }}" class="mb-4 space-y-2.5 border-b border-primary/5 pb-4">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label">مفتاح Gemini API Key {{ ($institute['ai_has_key'] ?? false) ? '(محفوظ: '.$institute['ai_key_masked'].')' : '*' }}</label>
                            <input type="password" name="gemini_api_key" class="input" placeholder="{{ ($institute['ai_has_key'] ?? false) ? 'اتركه فارغاً للإبقاء على المفتاح الحالي أو أدخل مفتاحاً جديداً' : 'AIzaSy...' }}">
                        </div>
                        <div>
                            <label class="label">موديل الذكاء الاصطناعي</label>
                            <select name="ai_model" class="input">
                                <option value="gemini-2.5-flash" @selected(($institute['ai_model'] ?? '') === 'gemini-2.5-flash')>Gemini 2.5 Flash (سريع جداً ويدعم الصور — موصى به)</option>
                                <option value="gemini-2.5-pro" @selected(($institute['ai_model'] ?? '') === 'gemini-2.5-pro')>Gemini 2.5 Pro (تحليل متقدم)</option>
                                <option value="gemini-2.0-flash" @selected(($institute['ai_model'] ?? '') === 'gemini-2.0-flash')>Gemini 2.0 Flash</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm">
                        <x-icon name="save" :size="14" /> حفظ مفتاح وموديل الذكاء الاصطناعي
                    </button>
                </form>

                <div class="grid gap-2 sm:grid-cols-3">
                    <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">تفعيل المساعد الذكي العام</div>
                            <div class="text-[10px] text-primary/50">تشغيل أو إيقاف Gemini AI</div>
                        </div>
                        <button class="toggle" aria-checked="{{ ($institute['ai_enabled'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.ai_enabled']) }}"
                                data-toggle-toast="تم تحديث حالة المساعد الذكي"></button>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">توزيع الواجبات من الجات</div>
                            <div class="text-[10px] text-primary/50">تقسيم رسائل وصور الواجبات للمدرسين</div>
                        </div>
                        <button class="toggle" aria-checked="{{ ($institute['ai_auto_homework'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.ai_auto_homework']) }}"
                                data-toggle-toast="تم تحديث التوزيع التلقائي للواجبات"></button>
                    </div>
                    <div class="flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">الرد التلقائي نيابةً عن الكادر</div>
                            <div class="text-[10px] text-primary/50">الرد الفوري على استفسارات الأهالي</div>
                        </div>
                        <button class="toggle" aria-checked="{{ ($institute['ai_auto_reply'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.ai_auto_reply']) }}"
                                data-toggle-toast="تم تحديث الرد التلقائي الذكي"></button>
                    </div>
                </div>
            </section>

        {{-- ===============================================================
             4. بوابة الواتساب و QR Code و Webhook & AI
        =============================================================== --}}
        </div>
        <div data-settings-panel="whatsapp" class="{{ $activeTab === 'whatsapp' ? '' : 'hidden' }}">
            <div class="grid gap-4 lg:grid-cols-3">
                <section class="card border-2 border-emerald-600/20 p-5 lg:col-span-2">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="flex items-center gap-2 text-sm font-extrabold text-primary">
                            <x-icon name="whatsapp" :size="17" class="text-emerald-600" /> إعدادات بوابة WhatsApp API والـ Webhook
                        </h2>
                        <span class="chip {{ ($institute['wa_api_has_key'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                            {{ ($institute['wa_api_has_key'] ?? false) ? 'مربوط وجاهز' : 'بانتظار API Key' }}
                        </span>
                    </div>

                    <div class="mb-3 flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">تفعيل الإرسال التلقائي عبر WhatsApp API</div>
                            <div class="text-[10px] text-primary/50">تشغيل أو إيقاف الإرسال التلقائي بالكامل</div>
                        </div>
                        <button class="toggle" aria-checked="{{ ($institute['wa_api_enabled'] ?? false) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.wa_api_enabled']) }}"
                                data-toggle-toast="تم تحديث حالة بوابة الواتساب"></button>
                    </div>

                    <form method="POST" action="{{ route('admin.settings.whatsapp') }}" class="mb-4 space-y-3 border-b border-primary/5 pb-4">
                        @csrf
                        @method('PUT')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">رابط البوابة (API Base URL) *</label>
                                <input name="wa_api_url" class="input" dir="ltr" value="{{ $institute['wa_api_url'] ?? 'https://whtsappapi.almoq3.com/api/v1' }}" required>
                            </div>
                            <div>
                                <label class="label">
                                    مفتاح المشروع (X-API-Key)
                                    @if ($institute['wa_api_has_key'] ?? false)
                                        <span class="text-emerald-600">({{ $institute['wa_api_key_masked'] }})</span>
                                    @endif
                                </label>
                                <input type="password" name="wa_api_key" class="input" dir="ltr"
                                       placeholder="{{ ($institute['wa_api_has_key'] ?? false) ? 'اتركه فارغاً للإبقاء على المفتاح الحالي...' : 'wapi_...' }}">
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">
                                    سر التوقيع / Webhook Secret (HMAC-SHA256)
                                    @if ($institute['wa_has_webhook_secret'] ?? false)
                                        <span class="text-emerald-600">({{ $institute['wa_webhook_secret_masked'] }})</span>
                                    @endif
                                </label>
                                <input type="password" name="wa_webhook_secret" class="input" dir="ltr"
                                       placeholder="{{ ($institute['wa_has_webhook_secret'] ?? false) ? 'اتركه فارغاً للإبقاء على السر الحالي...' : 'whsec_...' }}">
                            </div>
                            <div>
                                <label class="label">رابط Webhook الخاص بموقعك (ضعه في لوحة الواتساب)</label>
                                <input type="text" class="input bg-primary/5 text-xs" dir="ltr" readonly value="{{ url('/webhooks/whatsapp') }}" onclick="this.select()">
                            </div>
                        </div>

                        <div class="rounded-xl border border-blue-500/15 bg-blue-50/40 p-3">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <label class="text-xs font-extrabold text-primary">مجموعة / قناة الواتساب المخصصة لقراءة الواجبات بالـ AI (اختياري)</label>
                                <button type="button" class="btn btn-outline btn-sm" onclick="fetchWaContacts(this)">
                                    <x-icon name="refresh-cw" :size="12" /> جلب المجموعات من الواتساب
                                </button>
                            </div>
                            <input name="wa_ai_group_jid" id="waAiGroupJidInput" class="input mb-2" dir="ltr"
                                   value="{{ $institute['wa_ai_group_jid'] ?? '' }}" placeholder="مثال: 120363012345678901@g.us">
                            <select id="waContactsSelect" class="input hidden text-xs" onchange="if(this.value) document.getElementById('waAiGroupJidInput').value = this.value;">
                                <option value="">— اختر مجموعة أو جهة اتصال —</option>
                            </select>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-white p-2">
                                    <span class="text-[11px] font-bold text-primary">تفعيل قراءة واجبات المجموعة بـ AI</span>
                                    <button type="button" class="toggle" aria-checked="{{ ($institute['wa_ai_group_enabled'] ?? false) ? 'true' : 'false' }}"
                                            data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.wa_ai_group_enabled']) }}"
                                            data-toggle-toast="تم تحديث AI لمجموعة الواتساب"></button>
                                </div>
                                <div class="flex items-center justify-between gap-2 rounded-lg bg-white p-2">
                                    <span class="text-[11px] font-bold text-primary">رد AI التلقائي على رسائل الأهالي بالواتساب</span>
                                    <button type="button" class="toggle" aria-checked="{{ ($institute['wa_ai_private_reply'] ?? false) ? 'true' : 'false' }}"
                                            data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.wa_ai_private_reply']) }}"
                                            data-toggle-toast="تم تحديث الرد التلقائي بالواتساب"></button>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-dark btn-sm flex-1">
                                <x-icon name="save" :size="14" /> حفظ إعدادات الواتساب والـ Webhook
                            </button>
                            <button type="button" class="btn btn-outline btn-sm" onclick="checkWaApiStatus(this)">
                                <x-icon name="refresh-cw" :size="14" /> فحص الاتصال
                            </button>
                        </div>
                    </form>

                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <input id="waTestPhoneInput" class="input min-w-[11rem] flex-1" dir="ltr" placeholder="رقم للتجربة: 0770xxxxxxx">
                        <button type="button" class="btn btn-outline btn-sm" onclick="sendWaTestMessage(this)">
                            <x-icon name="send" :size="14" /> إرسال رسالة تجريبية
                        </button>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ([
                            ['features.wa_notify_attendance', 'wa_notify_attendance', 'تنبيهات الحضور والغياب'],
                            ['features.wa_notify_payments', 'wa_notify_payments', 'إيصالات الدفع المالي'],
                            ['features.wa_notify_registrations', 'wa_notify_registrations', 'قبول/رفض طلبات التسجيل'],
                            ['features.wa_notify_grades', 'wa_notify_grades', 'إشعارات الدرجات'],
                            ['features.wa_notify_homework', 'wa_notify_homework', 'إشعارات الواجبات'],
                        ] as [$tKey, $instKey, $tLabel])
                            <div class="flex items-center justify-between gap-2 rounded-xl border border-primary/5 p-2.5">
                                <span class="text-[11px] font-extrabold text-primary">{{ $tLabel }}</span>
                                <button class="toggle" aria-checked="{{ ($institute[$instKey] ?? true) ? 'true' : 'false' }}"
                                        data-toggle-url="{{ route('admin.settings.toggle', ['key' => $tKey]) }}"
                                        data-toggle-toast="تم تحديث {{ $tLabel }}"></button>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- صندوق QR Code لتسجيل دخول الواتساب مباشرة من لوحة الإدارة --}}
                <section class="card flex flex-col items-center p-5 text-center">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-extrabold text-primary">
                        <x-icon name="qr-code" :size="17" class="text-emerald-600" /> ربط رقم الواتساب (QR Code)
                    </h2>
                    <p class="mb-3 text-[11px] text-primary/60">افتح واتساب على هاتفك ← الأجهزة المرتبطة ← ربط جهاز، وامسح الرمز:</p>

                    <div id="waQrBox" class="mb-3 flex h-56 w-56 items-center justify-center overflow-hidden rounded-2xl border border-dashed border-primary/20 bg-primary/3 p-2">
                        <span class="text-xs font-bold text-primary/50">اضغط «توليد / تحديث QR» لعرض الرمز أو حالة الاتصال</span>
                    </div>

                    <div id="waQrStatusText" class="mb-3 text-xs font-extrabold text-primary/70"></div>

                    <div class="flex w-full flex-col gap-2">
                        <button type="button" class="btn btn-dark btn-sm w-full" onclick="loadWaQr(this)">
                            <x-icon name="refresh-cw" :size="14" /> توليد / تحديث QR Code
                        </button>
                        <button type="button" class="btn btn-outline btn-sm w-full text-rose-600" onclick="logoutWaSession(this)">
                            <x-icon name="log-out" :size="14" /> قطع اتصال الجلسة الحالية
                        </button>
                    </div>
                </section>
            </div>

            <script>
                async function loadWaQr(btn) {
                    const box = document.getElementById('waQrBox');
                    const statusEl = document.getElementById('waQrStatusText');
                    if (btn) btn.disabled = true;
                    if (statusEl) statusEl.textContent = 'جاري الاتصال بالبوابة...';
                    try {
                        const res = await fetch('{{ route("admin.settings.whatsapp.qr") }}', {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!data.ok) {
                            if (statusEl) statusEl.textContent = data.message || 'تعذر جلب رمز QR';
                            return;
                        }
                        if (data.status === 'connected') {
                            box.innerHTML = '<div class="p-3 text-emerald-700"><div class="text-2xl mb-1">✅</div><div class="text-xs font-extrabold">الواتساب متصل الآن</div><div class="text-[11px] mt-1" dir="ltr">' + (data.phone_number || '') + '</div></div>';
                            if (statusEl) statusEl.textContent = 'متصل وجاهز للإرسال';
                        } else if (data.qr_base64) {
                            const src = data.qr_base64.startsWith('data:') ? data.qr_base64 : ('data:image/png;base64,' + data.qr_base64);
                            box.innerHTML = '<img src="' + src + '" alt="WhatsApp QR" class="h-full w-full object-contain rounded-xl">';
                            if (statusEl) statusEl.textContent = 'امسح الرمز من تطبيق الواتساب الآن';
                        } else {
                            if (statusEl) statusEl.textContent = 'الحالة: ' + (data.status || 'بانتظار التوليد');
                        }
                    } catch (e) {
                        if (statusEl) statusEl.textContent = 'تعذر الاتصال بالخادم';
                    } finally {
                        if (btn) btn.disabled = false;
                    }
                }

                async function logoutWaSession(btn) {
                    if (!confirm('هل تريد قطع اتصال جلسة الواتساب الحالية؟')) return;
                    btn.disabled = true;
                    try {
                        const res = await fetch('{{ route("admin.settings.whatsapp.logout") }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            credentials: 'same-origin'
                        });
                        const data = await res.json().catch(() => ({}));
                        if (window.eyalToast) window.eyalToast(data.message || 'تم التنفيذ', data.ok ? 'success' : 'danger');
                        loadWaQr(null);
                    } finally {
                        btn.disabled = false;
                    }
                }

                async function fetchWaContacts(btn) {
                    btn.disabled = true;
                    try {
                        const res = await fetch('{{ route("admin.settings.whatsapp.contacts") }}', {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const data = await res.json().catch(() => ({}));
                        const sel = document.getElementById('waContactsSelect');
                        if (!data.ok || !Array.isArray(data.contacts)) {
                            if (window.eyalToast) window.eyalToast(data.message || 'تعذر جلب المجموعات', 'danger');
                            return;
                        }
                        sel.innerHTML = '<option value="">— اختر مجموعة أو جهة اتصال (' + data.contacts.length + ') —</option>';
                        data.contacts.forEach((c) => {
                            const opt = document.createElement('option');
                            opt.value = c.jid;
                            const isGroup = String(c.jid || '').endsWith('@g.us');
                            opt.textContent = (isGroup ? '👥 [مجموعة] ' : '👤 ') + (c.name || c.push_name || c.jid);
                            sel.appendChild(opt);
                        });
                        sel.classList.remove('hidden');
                        if (window.eyalToast) window.eyalToast('تم جلب ' + data.contacts.length + ' مجموعة/جهة اتصال', 'success');
                    } finally {
                        btn.disabled = false;
                    }
                }

                async function checkWaApiStatus(btn) {
                    btn.disabled = true;
                    try {
                        const res = await fetch('{{ route("admin.settings.whatsapp.status") }}', {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin'
                        });
                        const data = await res.json().catch(() => ({}));
                        if (window.eyalToast) window.eyalToast(data.status_text || 'تم الفحص', data.connected ? 'success' : 'danger');
                    } catch (e) {
                        if (window.eyalToast) window.eyalToast('تعذر الاتصال بالبوابة', 'danger');
                    } finally {
                        btn.disabled = false;
                    }
                }

                async function sendWaTestMessage(btn) {
                    const phone = document.getElementById('waTestPhoneInput')?.value?.trim() || '';
                    if (!phone) {
                        if (window.eyalToast) window.eyalToast('يرجى كتابة رقم هاتف للتجربة أولاً', 'danger');
                        return;
                    }
                    btn.disabled = true;
                    try {
                        const res = await fetch('{{ route("admin.settings.whatsapp.test") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ phone })
                        });
                        const data = await res.json().catch(() => ({}));
                        if (window.eyalToast) window.eyalToast(data.message || 'تم التنفيذ', data.ok ? 'success' : 'danger');
                    } catch (e) {
                        if (window.eyalToast) window.eyalToast('تعذر الاتصال بالخادم', 'danger');
                    } finally {
                        btn.disabled = false;
                    }
                }
            </script>

        {{-- ===============================================================
             5. إشعارات الدفع الفورية (Firebase Push & PWA)
        =============================================================== --}}
        </div>
        <div data-settings-panel="push" class="{{ $activeTab === 'push' ? '' : 'hidden' }}">
            <section class="card border-2 border-emerald-500/15 p-5">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold text-primary">
                        <x-icon name="bell" :size="17" class="text-emerald-600" /> إشعارات الدفع الفورية (Firebase Push & PWA)
                    </h2>
                    @php
                        $pushReady = ($institute['firebase_client_configured'] ?? false) && ($institute['firebase_has_service_account'] ?? false);
                    @endphp
                    <span class="chip {{ $pushReady ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                        {{ $pushReady ? 'مربوط وجاهز' : 'بانتظار مفاتيح Firebase' }}
                    </span>
                </div>
                <p class="mb-3 text-[11px] leading-relaxed text-primary/65">
                    يُرسل تنبيهات فورية لأجهزة الإدارة والمدرسين والطلاب وأولياء الأمور عند وصول رسالة جديدة، تسجيل حضور/غياب، إشعار إداري، واجب، أو طلب تسجيل جديد.
                </p>

                <div class="mb-3 flex items-center gap-3 rounded-xl border border-primary/5 p-3">
                    <div class="min-w-0 flex-1">
                        <div class="text-xs font-extrabold text-primary">تفعيل إشعارات Firebase Push</div>
                        <div class="text-[10px] text-primary/50">إظهار طلب الإذن للمستخدمين وإرسال الإشعارات الفورية للأجهزة</div>
                    </div>
                    <button class="toggle" aria-checked="{{ ($institute['push_enabled'] ?? true) ? 'true' : 'false' }}"
                            data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.push_enabled']) }}"
                            data-toggle-toast="تم تحديث حالة إشعارات الدفع" aria-label="إشعارات الدفع"></button>
                </div>

                <form method="POST" action="{{ route('admin.settings.push') }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="label">Firebase API Key</label>
                            <input name="firebase_api_key" class="input" dir="ltr" value="{{ $institute['firebase_api_key'] ?? '' }}" placeholder="AIzaSy...">
                        </div>
                        <div>
                            <label class="label">Project ID</label>
                            <input name="firebase_project_id" class="input" dir="ltr" value="{{ $institute['firebase_project_id'] ?? '' }}" placeholder="my-app-12345">
                        </div>
                        <div>
                            <label class="label">Auth Domain</label>
                            <input name="firebase_auth_domain" class="input" dir="ltr" value="{{ $institute['firebase_auth_domain'] ?? '' }}" placeholder="my-app-12345.firebaseapp.com">
                        </div>
                        <div>
                            <label class="label">Messaging Sender ID</label>
                            <input name="firebase_messaging_sender_id" class="input" dir="ltr" value="{{ $institute['firebase_messaging_sender_id'] ?? '' }}" placeholder="123456789012">
                        </div>
                        <div>
                            <label class="label">App ID</label>
                            <input name="firebase_app_id" class="input" dir="ltr" value="{{ $institute['firebase_app_id'] ?? '' }}" placeholder="1:123456:web:abcdef">
                        </div>
                        <div>
                            <label class="label">Web Push VAPID Key</label>
                            <input name="firebase_vapid_key" class="input" dir="ltr" value="{{ $institute['firebase_vapid_key'] ?? '' }}" placeholder="BEl62i...">
                        </div>
                    </div>
                    <div>
                        <label class="label">
                            ملف حساب الخدمة (Service Account JSON)
                            @if ($institute['firebase_has_service_account'] ?? false)
                                <span class="text-emerald-600">(محفوظ وجاهز للإرسال من السيرفر)</span>
                            @endif
                        </label>
                        <textarea name="firebase_service_account_json" class="input min-h-[100px] text-[11px]" dir="ltr"
                                  placeholder='{{ ($institute["firebase_has_service_account"] ?? false) ? "اتركه فارغاً للإبقاء على الملف الحالي، أو الصق JSON جديد لتحديثه..." : "{\"type\": \"service_account\", \"project_id\": \"...\", ...}" }}'></textarea>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-dark btn-sm flex-1">
                            <x-icon name="save" :size="14" /> حفظ إعدادات Firebase
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="testFirebasePush(this)">
                            <x-icon name="send" :size="14" /> إرسال إشعار تجريبي لجهازي
                        </button>
                    </div>
                </form>
            </section>

            <script>
                async function testFirebasePush(btn) {
                    btn.disabled = true;
                    try {
                        const res = await fetch('{{ route("admin.settings.push.test") }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            credentials: 'same-origin'
                        });
                        const data = await res.json().catch(() => ({}));
                        if (window.eyalToast) window.eyalToast(data.message || 'تم التنفيذ', data.ok ? 'success' : 'danger');
                    } catch (e) {
                        if (window.eyalToast) window.eyalToast('تعذر الاتصال بالخادم', 'danger');
                    } finally {
                        btn.disabled = false;
                    }
                }
            </script>

        {{-- ===============================================================
             6. حسابات المستخدمين وظهور المراحل
        =============================================================== --}}
        </div>
        <div data-settings-panel="users" class="{{ $activeTab === 'users' ? '' : 'hidden' }} space-y-4">
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
                        <tbody data-paginate="15">
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
        </div>
    </div>

    <script>
        function switchSettingsTab(slug, url) {
            document.querySelectorAll('[data-settings-panel]').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-settings-panel') !== slug);
            });
            document.querySelectorAll('[data-settings-tab]').forEach(function (tab) {
                var active = tab.getAttribute('data-settings-tab') === slug;
                tab.classList.toggle('bg-primary', active);
                tab.classList.toggle('text-white', active);
                tab.classList.toggle('shadow-soft', active);
                tab.classList.toggle('text-primary', !active);
            });
            if (url && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', url);
            }
            return false;
        }
    </script>
@endsection

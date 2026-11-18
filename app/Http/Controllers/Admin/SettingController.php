<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhatsAppService;
use App\Support\Eyal;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const TOGGLES = [
        'features.staff_enabled',
        'features.parent_enabled',
        'features.student_enabled',
        'features.announcement_bar',
        'features.registrations_open',
        'features.chat_enabled',
        'features.chat_parent_teacher_enabled',
        'features.chat_parent_admin_enabled',
        'features.chat_staff_admin_enabled',
        'features.ai_enabled',
        'features.ai_auto_homework',
        'features.ai_auto_reply',
        'features.push_enabled',
        'features.wa_api_enabled',
        'features.wa_notify_attendance',
        'features.wa_notify_payments',
        'features.wa_notify_registrations',
        'features.wa_notify_grades',
        'features.wa_notify_homework',
        'features.wa_ai_group_enabled',
        'features.wa_ai_private_reply',
    ];

    public function index(?string $tab = 'general')
    {
        $activeTab = in_array($tab, ['general', 'features', 'ai', 'whatsapp', 'push', 'users'], true) ? $tab : 'general';

        return view('admin.settings', [
            'activeTab' => $activeTab,
            'users' => Eyal::usersList(),
            'stages' => Eyal::stages(),
        ]);
    }

    public function updateInstitute(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'work_hours' => ['nullable', 'string', 'max:255'],
            'announcement' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'يرجى إدخال اسم المعهد.',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set('institute.'.$key, $value, 'institute');
        }

        return back()->with('success', 'تم حفظ بيانات المعهد.');
    }

    public function updateTheme(Request $request)
    {
        $validated = $request->validate([
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'primary_color.regex' => 'صيغة اللون الأساسي غير صحيحة.',
            'secondary_color.regex' => 'صيغة اللون الثانوي غير صحيحة.',
        ]);

        Setting::set('theme.primary_color', $validated['primary_color'], 'theme');
        Setting::set('theme.secondary_color', $validated['secondary_color'], 'theme');

        return back()->with('success', 'تم حفظ ألوان الواجهة.');
    }

    public function updateAi(Request $request)
    {
        $validated = $request->validate([
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'ai_model' => ['nullable', 'string', 'in:gemini-2.5-flash,gemini-2.5-pro,gemini-2.0-flash'],
        ]);

        if (! empty($validated['gemini_api_key'])) {
            Setting::set('ai.gemini_api_key', trim($validated['gemini_api_key']), 'ai');
        }

        if (! empty($validated['ai_model'])) {
            Setting::set('ai.model', $validated['ai_model'], 'ai');
        }

        Eyal::flushCache();

        return back()->with('success', 'تم حفظ إعدادات ومفتاح المساعد الذكي (Gemini AI) بنجاح.');
    }

    public function updatePush(Request $request)
    {
        $validated = $request->validate([
            'firebase_api_key' => ['nullable', 'string', 'max:255'],
            'firebase_auth_domain' => ['nullable', 'string', 'max:255'],
            'firebase_project_id' => ['nullable', 'string', 'max:255'],
            'firebase_messaging_sender_id' => ['nullable', 'string', 'max:255'],
            'firebase_app_id' => ['nullable', 'string', 'max:255'],
            'firebase_vapid_key' => ['nullable', 'string', 'max:255'],
            'firebase_service_account_json' => ['nullable', 'string', 'max:20000'],
        ]);

        if (! empty($validated['firebase_service_account_json'])) {
            $decoded = json_decode(trim($validated['firebase_service_account_json']), true);
            if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
                return back()->with('error', 'صيغة ملف Service Account JSON غير صحيحة. يرجى لصق الملف كاملاً كما هو من Firebase.');
            }
            Setting::set('firebase.service_account_json', trim($validated['firebase_service_account_json']), 'firebase');
        }

        foreach ([
            'firebase_api_key' => 'firebase.api_key',
            'firebase_auth_domain' => 'firebase.auth_domain',
            'firebase_project_id' => 'firebase.project_id',
            'firebase_messaging_sender_id' => 'firebase.messaging_sender_id',
            'firebase_app_id' => 'firebase.app_id',
            'firebase_vapid_key' => 'firebase.vapid_key',
        ] as $inputKey => $settingKey) {
            if (array_key_exists($inputKey, $validated)) {
                Setting::set($settingKey, trim((string) ($validated[$inputKey] ?? '')), 'firebase');
            }
        }

        Eyal::flushCache();

        return back()->with('success', 'تم حفظ إعدادات إشعارات Firebase (Push Notifications) بنجاح.');
    }

    public function updateWhatsApp(Request $request)
    {
        $validated = $request->validate([
            'wa_api_url' => ['required', 'url', 'max:255'],
            'wa_api_key' => ['nullable', 'string', 'max:255'],
            'wa_webhook_secret' => ['nullable', 'string', 'max:255'],
            'wa_ai_group_jid' => ['nullable', 'string', 'max:120'],
        ], [
            'wa_api_url.required' => 'يرجى إدخال رابط بوابة الواتساب.',
            'wa_api_url.url' => 'رابط البوابة يجب أن يكون URL صحيحاً.',
        ]);

        Setting::set('wa.api_url', rtrim(trim($validated['wa_api_url']), '/'), 'wa');

        if (! empty($validated['wa_api_key'])) {
            Setting::set('wa.api_key', trim($validated['wa_api_key']), 'wa');
            if (Setting::get('features.wa_api_enabled') === null) {
                Setting::set('features.wa_api_enabled', true, 'features');
            }
        }

        if (! empty($validated['wa_webhook_secret'])) {
            Setting::set('wa.webhook_secret', trim($validated['wa_webhook_secret']), 'wa');
        }

        if (array_key_exists('wa_ai_group_jid', $validated)) {
            Setting::set('wa.ai_group_jid', trim((string) ($validated['wa_ai_group_jid'] ?? '')), 'wa');
        }

        Eyal::flushCache();

        return back()->with('success', 'تم حفظ إعدادات بوابة WhatsApp API والـ Webhook بنجاح.');
    }

    public function whatsAppStatus()
    {
        return response()->json(WhatsAppService::status());
    }

    public function whatsAppQr()
    {
        return response()->json(WhatsAppService::getQr());
    }

    public function whatsAppLogout()
    {
        return response()->json(WhatsAppService::logout());
    }

    public function whatsAppContacts()
    {
        return response()->json(WhatsAppService::listContacts());
    }

    public function testWhatsApp(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ], [
            'phone.required' => 'يرجى إدخال رقم الهاتف للتجربة.',
        ]);

        $institute = Eyal::institute();
        $res = WhatsAppService::sendText(
            $validated['phone'],
            'رسالة تجريبية من نظام '.$institute['name'].' — تم الربط ببوابة WhatsApp API بنجاح.',
            null,
            true
        );

        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'key' => ['required', 'in:'.implode(',', self::TOGGLES)],
        ]);

        $current = (bool) Setting::get($validated['key'], true);
        Setting::set($validated['key'], ! $current, 'features');

        return response()->json(['active' => ! $current]);
    }
}

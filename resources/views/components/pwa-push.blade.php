@php
    $isAuthenticated = auth()->check() || ! empty(session('eyal_user.id'));
    $firebaseConfig = [
        'apiKey' => $institute['firebase_api_key'] ?? '',
        'authDomain' => $institute['firebase_auth_domain'] ?? '',
        'projectId' => $institute['firebase_project_id'] ?? '',
        'messagingSenderId' => $institute['firebase_messaging_sender_id'] ?? '',
        'appId' => $institute['firebase_app_id'] ?? '',
    ];
    $vapidKey = $institute['firebase_vapid_key'] ?? '';
    $pushReady = $isAuthenticated && ! empty($institute['firebase_client_configured']);
@endphp

<style id="eyal-pwa-push-css">
    .eyal-float-popup {
        position: fixed;
        bottom: calc(4.75rem + env(safe-area-inset-bottom, 0px));
        left: 1rem;
        right: 1rem;
        max-width: 26rem;
        margin-inline: auto;
        z-index: 9950;
        background: #ffffff;
        border: 1px solid rgba(11, 58, 99, 0.14);
        border-radius: 1.15rem;
        padding: 0.9rem 1rem;
        box-shadow: 0 20px 50px -15px rgba(8, 24, 40, 0.35);
        display: none;
        animation: eyalPopUp 0.24s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    @media (min-width: 640px) {
        .eyal-float-popup {
            bottom: 1.25rem;
            left: 1.25rem;
            right: auto;
            margin-inline: 0;
        }
    }
    .eyal-float-popup.is-open {
        display: block;
    }
    @keyframes eyalPopUp {
        from { opacity: 0; transform: translateY(14px) scale(0.98); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
</style>

{{-- ببوب تثبيت التطبيق (Android & iOS PWA Install Popup) --}}
<div id="eyalPwaInstallPopup" class="eyal-float-popup" role="dialog" aria-label="تثبيت التطبيق">
    <div class="flex items-start gap-3">
        <img src="{{ asset('assets/logo.png') }}" alt="{{ $institute['short_name'] }}" class="h-11 w-11 shrink-0 rounded-xl border border-primary/10 bg-white p-1 object-contain">
        <div class="min-w-0 flex-1">
            <div class="text-xs font-extrabold text-primary">تثبيت تطبيق {{ $institute['short_name'] }}</div>
            <p id="eyalPwaInstallDesc" class="mt-0.5 text-[11px] leading-relaxed text-primary/70">
                ثبّت التطبيق على جهازك للوصول السريع وتلقي التنبيهات الفورية.
            </p>
            <div id="eyalPwaIosSteps" class="mt-2 hidden rounded-xl bg-primary/5 p-2.5 text-[11px] font-bold leading-relaxed text-primary">
                <div>1. اضغط على زر <b>المشاركة</b> <span class="inline-block rounded bg-white px-1.5 py-0.5 shadow-sm">⬆️</span> في شريط سفاري.</div>
                <div class="mt-1">2. اختر <b>«إضافة إلى الشاشة الرئيسية»</b> (Add to Home Screen).</div>
            </div>
            <div class="mt-2.5 flex items-center gap-2">
                <button type="button" id="eyalPwaInstallBtn" class="btn btn-dark btn-sm hidden">
                    <x-icon name="download" :size="13" /> تثبيت الآن
                </button>
                <button type="button" id="eyalPwaDismissBtn" class="btn btn-ghost btn-sm">لاحقاً</button>
            </div>
        </div>
    </div>
</div>

{{-- ببوب طلب إذن الإشعارات الفورية (Firebase Push Permission Popup) --}}
@if ($pushReady)
<div id="eyalPushPrompt" class="eyal-float-popup" role="dialog" aria-label="تفعيل الإشعارات">
    <div class="flex items-start gap-3">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
            <x-icon name="bell" :size="21" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="text-xs font-extrabold text-primary">تفعيل الإشعارات الفورية</div>
            <p class="mt-0.5 text-[11px] leading-relaxed text-primary/70">
                احصل على تنبيهات فورية عند وصول رسالة جديدة، تسجيل الحضور والغياب، الواجبات، أو طلبات التسجيل.
            </p>
            <div class="mt-2.5 flex items-center gap-2">
                <button type="button" id="eyalPushAllowBtn" class="btn btn-dark btn-sm">
                    <x-icon name="bell" :size="13" /> تفعيل الإشعارات
                </button>
                <button type="button" id="eyalPushLaterBtn" class="btn btn-ghost btn-sm">لاحقاً</button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
(function () {
    'use strict';

    const swUrl = @json(asset('sw.js'));
    const subscribeUrl = @json(route('push.subscribe'));
    const pushReady = @json($pushReady);
    const firebaseConfig = @json($firebaseConfig);
    const vapidKey = @json($vapidKey);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let swRegistrationPromise = null;
    if ('serviceWorker' in navigator) {
        swRegistrationPromise = navigator.serviceWorker.register(swUrl, { scope: '/' }).catch(() => null);
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    function detectPlatform() {
        const ua = (navigator.userAgent || '').toLowerCase();
        if (/iphone|ipad|ipod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) return 'ios';
        if (/android/.test(ua)) return 'android';
        return 'web';
    }

    // 1. إدارة ببوب تثبيت التطبيق (Android & iOS PWA Install Popup)
    (function setupPwaInstall() {
        if (isStandalone()) return;
        const popup = document.getElementById('eyalPwaInstallPopup');
        const installBtn = document.getElementById('eyalPwaInstallBtn');
        const dismissBtn = document.getElementById('eyalPwaDismissBtn');
        const iosSteps = document.getElementById('eyalPwaIosSteps');
        const desc = document.getElementById('eyalPwaInstallDesc');
        if (!popup || !dismissBtn) return;

        const dismissKey = 'eyal_pwa_dismissed_at';
        const lastDismissed = parseInt(localStorage.getItem(dismissKey) || '0', 10);
        if (lastDismissed && (Date.now() - lastDismissed) < 7 * 86400 * 1000) return;

        let deferredPrompt = null;
        const platform = detectPlatform();

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            if (installBtn) installBtn.classList.remove('hidden');
            if (iosSteps) iosSteps.classList.add('hidden');
            popup.classList.add('is-open');
        });

        window.addEventListener('appinstalled', () => {
            popup.classList.remove('is-open');
            deferredPrompt = null;
        });

        if (installBtn) {
            installBtn.addEventListener('click', async () => {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                await deferredPrompt.userChoice.catch(() => ({}));
                deferredPrompt = null;
                popup.classList.remove('is-open');
            });
        }

        // دعم إرشاد التثبيت لأجهزة iOS Safari
        if (platform === 'ios' && /safari/i.test(navigator.userAgent) && !/crios|fxios/i.test(navigator.userAgent)) {
            setTimeout(() => {
                if (isStandalone()) return;
                if (iosSteps) iosSteps.classList.remove('hidden');
                if (installBtn) installBtn.classList.add('hidden');
                if (desc) desc.textContent = 'أضف التطبيق إلى شاشتك الرئيسية في الآيفون لتجربة سريعة وإشعارات فورية:';
                popup.classList.add('is-open');
            }, 2200);
        }

        dismissBtn.addEventListener('click', () => {
            localStorage.setItem(dismissKey, String(Date.now()));
            popup.classList.remove('is-open');
        });
    })();

    // 2. إدارة إشعارات Firebase Push
    if (!pushReady || typeof Notification === 'undefined') return;

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            if (document.querySelector('script[src="' + src + '"]')) return resolve();
            const s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
    }

    async function registerFcmToken(showSuccessToast) {
        try {
            await loadScript('https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js');
            await loadScript('https://www.gstatic.com/firebasejs/10.14.1/firebase-messaging-compat.js');

            if (!window.firebase) return false;
            if (!window.firebase.apps.length) {
                window.firebase.initializeApp(firebaseConfig);
            }

            const messaging = window.firebase.messaging();
            const swReg = swRegistrationPromise ? await swRegistrationPromise : await navigator.serviceWorker.ready;
            const token = await messaging.getToken({
                vapidKey: vapidKey,
                serviceWorkerRegistration: swReg,
            });

            if (!token) return false;

            await fetch(subscribeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    token: token,
                    platform: detectPlatform(),
                }),
            });

            // استقبال الإشعارات أثناء فتح التطبيق وعرضها فوراً
            if (!window._eyalFcmOnMessageBound) {
                window._eyalFcmOnMessageBound = true;
                messaging.onMessage((payload) => {
                    const n = payload?.notification || {};
                    const d = payload?.data || {};
                    const msg = (n.title || d.title || 'إشعار جديد') + (n.body || d.body ? ': ' + (n.body || d.body) : '');
                    if (window.eyalToast) window.eyalToast(msg, 'success');
                });
            }

            if (showSuccessToast && window.eyalToast) {
                window.eyalToast('تم تفعيل الإشعارات الفورية بنجاح', 'success');
            }
            return true;
        } catch (err) {
            if (showSuccessToast && window.eyalToast) {
                window.eyalToast('تعذر تفعيل الإشعارات: تأكد من إعدادات المتصفح أو HTTPS', 'danger');
            }
            return false;
        }
    }

    const pushPrompt = document.getElementById('eyalPushPrompt');
    const allowBtn = document.getElementById('eyalPushAllowBtn');
    const laterBtn = document.getElementById('eyalPushLaterBtn');

    if (Notification.permission === 'granted') {
        if (!sessionStorage.getItem('eyal_fcm_synced')) {
            registerFcmToken(false).then((ok) => {
                if (ok) sessionStorage.setItem('eyal_fcm_synced', '1');
            });
        }
    } else if (Notification.permission === 'default' && pushPrompt) {
        const pushDismissKey = 'eyal_push_dismissed_at';
        const lastPushDismissed = parseInt(localStorage.getItem(pushDismissKey) || '0', 10);
        if (!lastPushDismissed || (Date.now() - lastPushDismissed) > 3 * 86400 * 1000) {
            setTimeout(() => {
                const installPopup = document.getElementById('eyalPwaInstallPopup');
                if (!installPopup || !installPopup.classList.contains('is-open')) {
                    pushPrompt.classList.add('is-open');
                }
            }, 3500);
        }

        if (allowBtn) {
            allowBtn.addEventListener('click', async () => {
                allowBtn.disabled = true;
                try {
                    const perm = await Notification.requestPermission();
                    pushPrompt.classList.remove('is-open');
                    if (perm === 'granted') {
                        await registerFcmToken(true);
                    }
                } finally {
                    allowBtn.disabled = false;
                }
            });
        }

        if (laterBtn) {
            laterBtn.addEventListener('click', () => {
                localStorage.setItem(pushDismissKey, String(Date.now()));
                pushPrompt.classList.remove('is-open');
            });
        }
    }
})();
</script>

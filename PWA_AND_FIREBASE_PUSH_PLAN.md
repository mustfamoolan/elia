# خطة تنفيذ تطبيق الويب التقدمي (PWA) وإشعارات الدفع الفورية عبر Firebase (FCM v1)

توثق هذه الخطة المعمارية والتنفيذية لتحويل نظام **معهد آيلا التعليمي** إلى تطبيق ويب تقدمي متكامل (**PWA**) يدعم التثبيت على **Android** و **iOS**، مع ربطه بنظام إشعارات الدفع الفورية (**Firebase Cloud Messaging HTTP v1**) لجميع الأدوار: (**الإدارة، الكادر التدريسي، الطلاب، وأولياء الأمور**).

---

## 1. الأهداف الرئيسية
1. **تجربة تطبيق أصلي (PWA):**
   - إمكانية تثبيت الموقع كتطبيق مستقل (`Standalone`) على شاشات الهواتف (Android و iPhone/iPad) وسطح المكتب، مع أيقونة المعهد وهويته البصرية (`#0B3A63`).
   - نافذة منبثقة ذكية (**Install Popup**) مخصصة لكل نظام:
     - **Android / Chrome / Edge:** زر تثبيت مباشر يستدعي `beforeinstallprompt`.
     - **iOS / Safari:** نافذة إرشادية بصرية واضحة تشرح خطوتي: «اضغط زر المشاركة ⬆️ ثم اختر إضافة إلى الشاشة الرئيسية».
2. **إشعارات الدفع الفورية عبر Firebase (FCM HTTP v1):**
   - نافذة منبثقة أنيقة (**Permission Popup**) تطلب إذن الإشعارات من المستخدم بعد تسجيل الدخول (للمدير، المدرس، الطالب، وولي الأمر) مع شرح الفائدة وزر تفعيل فوري.
   - استخدام أحدث واجهة رسمية من Google (**FCM HTTP v1 API**) عبر توقيع OAuth2 JWT محلياً بـ PHP (`openssl_sign`) بدون أي مكتبات خارجية ثقيلة.
   - توحيد **Service Worker (`public/sw.js`)** ليخدم كلاً من PWA وتلقي إشعارات Firebase في الخلفية (`push`) مع فتح الرابط المباشر عند النقر (`notificationclick`).
3. **تغطية شاملة لأحداث النظام:**
   - **رسالة جديدة في المحادثات** (من ولي الأمر، المدرس، الإدارة، أو الرد الآلي).
   - **إشعار جديد** من الإدارة (عام أو مخصص لمرحلة/دورة/طالب/ولي أمر).
   - **تسجيل حضور وغياب جديد** (حاضر / متأخر / مجاز / غائب) يُرسل للطالب وولي الأمر.
   - **طلب تسجيل طالب جديد** يُرسل فوراً لجميع حسابات الإدارة.
   - **الواجبات والدرجات** (إضافة واجب، تسليم واجب، مراجعة واجب، أو رصد درجة) عبر ربط `Notifier::send()` تلقائياً بـ `FcmService`.

---

## 2. مراحل التنفيذ

### المرحلة 1: البنية التحتية وقاعدة البيانات
- إنشاء جدول `push_tokens` لتخزين رموز الأجهزة (`user_id`, `token`, `platform`, `device_label`, `last_seen_at`) مع دعم تعدد الأجهزة لنفس المستخدم.
- إنشاء ملف الهجرة (`database/migrations/2026_11_19_000001_create_push_tokens_table.php`) وحارس الجداول التلقائي `Eyal::ensurePushTables()`.
- إنشاء الموديل `App\Models\PushToken` وعلاقته بالمستخدم.

### المرحلة 2: محرك Firebase الخلفي (`App\Services\FcmService`)
- بناء `FcmService` لتوليد Access Token لـ Google OAuth2 من ملف **Service Account JSON** وتخزينه مؤقتاً في كاش الملفات (`50 دقيقة`).
- إرسال الإشعارات عبر `FCM v1` إلى أجهزة المستخدمين المستهدفين، مع حذف الرموز المنتهية الصلاحية (`UNREGISTERED` / `NOT_FOUND`) تلقائياً.
- ربط `Notifier::send()` و `Admin\NotificationController` و `ChatController` و `AttendanceController` و `TeacherController` و `PageController::storeRegistration` بـ `FcmService`.

### المرحلة 3: إعدادات Firebase في لوحة الإدارة
- إضافة قسم متكامل في **إعدادات المعهد** (`resources/views/admin/settings.blade.php` و `SettingController.php`) لإدخال:
  - مفاتيح الويب (`apiKey`, `authDomain`, `projectId`, `messagingSenderId`, `appId`, `vapidKey`).
  - ملف حساب الخدمة (`Service Account JSON`) لإرسال الإشعارات من السيرفر بأمان.
  - زر تفعيل/تعطيل الإشعارات وزر اختبار الإشعارات.

### المرحلة 4: ملفات PWA و Service Worker والببوبات الذكية
- إنشاء `public/manifest.webmanifest` بهوية معهد آيلا.
- إنشاء `public/sw.js` الموحد (PWA + Background Push + Notification Click Deep-Link).
- إنشاء مكون الواجهة `resources/views/components/pwa-push.blade.php` ودمجه في القوالب الثلاثة (`layouts/admin.blade.php`, `layouts/teacher.blade.php`, `layouts/public.blade.php`) بحيث يعمل على **جميع الواجهات**:
  - ببوب تثبيت التطبيق (Android + iOS).
  - ببوب طلب إذن الإشعارات (`Notification.requestPermission`).
  - تسجيل رمز FCM وحفظه عبر `POST /push/subscribe`.
  - عرض الإشعارات الفورية داخل التطبيق (`onMessage`) عندما يكون الموقع مفتوحاً.

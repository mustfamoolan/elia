# خطة ربط معهد آيلا: قاعدة البيانات والمنطق والصلاحيات

> الهدف: تحويل الواجهات الحالية من عرض تجريبي ثابت إلى نظام حقيقي متكامل، مع الحفاظ على التصميم الحالي حرفياً، وإضافة بوابة الكادر التدريسي، وتفعيل صلاحيات كل دور.

---

## حالة التنفيذ

- ✅ **المرحلة 0 — التأسيس:** تم إنشاء قاعدة MySQL `eyla`، وتشغيل جميع الـ migrations، وإنشاء النماذج والعلاقات، وتشغيل `EyalSeeder` ببيانات الواجهة الحالية (11 مستخدم، 7 طلاب، 5 كادر، 6 دورات، 9 تسجيلات في دورات، حضور، درجات، واجبات، مدفوعات، إعلانات، عروض، إشعارات، إعدادات).
- ✅ **المرحلة 1 — المصادقة والأدوار:** تم استبدال الدخول التجريبي بـ Laravel Auth حقيقي، مع:
  - التحقق من `username` + `password` + `is_active`.
  - حماية من محاولات الدخول المتكررة (5 محاولات/دقيقة).
  - تحديث `last_login_at` عند كل دخول.
  - Middleware `role` للتحقق من الدور على مستوى المسار.
  - مزامنة جلسة `eyal_user` مع المستخدم الحقيقي للحفاظ على الواجهات الحالية.
  - تحويل كل دور إلى لوحته: `admin → /admin`، `staff → /teacher`، `student → /student`، `parent → /parent`.
  - صفحة 403 عربية بنفس هوية التصميم.
  - بوابة كادر أولية `/teacher` تعرض دورات المدرس وطلابه وواجباته وحصص اليوم من قاعدة البيانات.
- ✅ **المرحلة 2 — ربط الصفحات العامة بقاعدة البيانات:**
  - `app/Support/Eyal.php` أصبح يقرأ كل البيانات من Eloquent ويعيد نفس مفاتيح المصفوفات القديمة، لذلك بقيت واجهات Blade كما هي دون أي تغيير في التصميم.
  - `PageController` يستخدم `Eyal` في: الرئيسية، المراحل، الدورات، العروض، الجدول، الإعلانات، النتائج، الواجبات، حساب ولي الأمر، حساب الطالب، الكادر، الإشعارات، والتسجيل.
  - `Eyal::institute()` يقرأ جدول `settings` مع الرجوع إلى `config/eyal.php`، وMiddleware `ShareInstituteData` يشارك `$institute` مع كل الواجهات.
  - نموذج التسجيل أصبح يخزّن طلباً حقيقياً في `registration_requests` عبر `POST /register` ثم يفتح واتساب، مع تحقق من المدخلات وحد للمحاولات.
  - صفحات الطالب وولي الأمر تعرض تنبيهاً واضحاً عند عدم وجود طالب مرتبط، والإشعارات وعدّادها أصبحا من `notifications` و`notification_recipients`.
  - إضافة `tests/Feature/PublicPagesTest.php` (11 اختباراً) تغطي الصفحات العامة، التسجيل، بوابتي الطالب وولي الأمر، وتجاوز الإعدادات.
- ✅ **المرحلة 3 — لوحة الإدارة الحقيقية:**
  - تقسيم لوحة الإدارة إلى Controllers مستقلة داخل `app/Http/Controllers/Admin/` (Dashboard, Students, Registrations, Stages, Courses, Staff, Attendance, Grades, Homework, Payments, Announcements, Offers, Schedule, Notifications, Settings, Users).
  - 60 مسار إدارة مسجّل في `routes/web.php`، كلها محمية بـ `auth` + `role:admin`.
  - كل نماذج الإضافة والتعديل في واجهات الإدارة أصبحت ترسل إلى قاعدة البيانات فعلياً (POST/PUT/DELETE) بدل الوضع التجريبي.
  - نظام موحّد للنوافذ في `public/js/app.js`: `data-add` لفتح نموذج إضافة، `data-edit` لفتح نموذج تعديل مع تعبئة الحقول، و`data-toggle-url` لمفاتيح الحفظ الفوري.
  - الطلاب: إضافة/تعديل/حذف، إنشاء حساب دخول تلقائياً، ربط ولي الأمر، توليد رقم طالب، ربط المواد بالدورات، وإنشاء الاشتراك المالي.
  - التسجيلات: قبول الطلب ينشئ ملف الطالب ويربطه بالدورة والاشتراك تلقائياً، مع الرفض والحذف.
  - المدفوعات: تسجيل الدفعات مع رقم إيصال تلقائي وصفحة إيصال قابلة للطباعة `admin/payment-receipt.blade.php`.
  - الإشعارات: إرسال لجميع المستخدمين أو الطلاب أو أولياء الأمور أو طالب محدد، مع إنشاء صفوف `notification_recipients`.
  - الإعدادات: حفظ بيانات المعهد والألوان وخيارات الظهور، وإدارة حسابات الدخول وصلاحياتها.
  - إضافة `tests/Feature/AdminCrudTest.php` (11 اختباراً) تغطي فتح كل صفحات الإدارة، CRUD المراحل والطلاب والكادر والدورات والحسابات، قبول التسجيلات، الدفعات والإيصالات، الإشعارات، والإعدادات والصلاحيات.
- ✅ **المرحلة 4 — بوابات الطالب وولي الأمر:**\n  - `public/parent.blade.php` + `public/student.blade.php` += Homework sections: عرض قائمة واجبات، حالة التسليم، رفع جديد (وصول تلقائي للمدرس),\n  - `public/homework.blade.php` += عرض الكل + فلترة + `homework.submit` endpoint + `homework.download` الملفات الخاصة, `data-modal-open` للأيقونات.\n  - `Eyal::homeworkFor()` الآن يرجع `submission_status`/`submission_label`/`feedback`/`files`/`submission_files`.\n  - `Eyal::studentForUser()` يدعم `role===parent` عبر `parents` relationship + `studentForUser` fallback.\n  - `Eyal::mapStudent()` يشمل `courses` + `parent_id` + `guardian_name`.\n  - `PageController` يرسل `homework` مع `parentPage`/`studentPage`.\n  - `Notifier::sendToStudentAndParents()` يرسل للإشعار لطالب + أولياء الأمر.\n  - `Notifier::sendToCourse()` يرسل لإشعار لجميع طلاب الدورة + أولياء أمورهم.\n  - Layouts `layouts.teacher` تحتوي على تنقل كامل (8 روابط): dashboard, courses, students, attendance, grades, homework, schedule, profile.\n  - TeacherController full CRUD: dashboard, courses, course.show, students, attendance, grades, grades.store/destroy, homework (create/show/review), schedule, profile/update.\n  - `tests/Feature/AdminCrudTest.php` + `tests/Feature/PublicPagesTest.php` both pass (33/33, 152 assertions).\n- ⏳ **المرحلة التالية:** اختبارات إضافية، possible teacher review notification flows refinement.

### حسابات الدخول التجريبية

| الدور | اسم المستخدم | كلمة المرور |
|---|---|---|
| الإدارة | `admin` | `admin123` |
| الكادر | `hussein` أو `ali` أو `sara` أو `zainab` أو `mohammed` | `123456` |
| الطالب | `101` أو `102` أو `104` أو `106` | `123456` |
| ولي الأمر | `parent` | `123456` |

### أوامر مفيدة

```sh
php artisan migrate:fresh --seed   # إعادة بناء قاعدة البيانات ببيانات الواجهة
php artisan test                   # تشغيل الاختبارات (33 اختباراً)
```

---

## 1. المبادئ الأساسية

1. **لا نغيّر التصميم**: نفس الـ Blade views، نفس CSS، نفس الألوان والخطوط والمكونات. التغيير يكون في مصدر البيانات والمنطق فقط.
2. **كل دور يرى ما يخصه فقط**: إدارة، كادر، طالب، ولي أمر.
3. **الإدارة تتحكم بكل شيء**: المحتوى، الحسابات، التسجيلات، المدفوعات، الصلاحيات.
4. **البيانات الحالية لا تضيع**: نعيد إدخال بيانات العرض الحالية كبيانات أولية (Seeders) حتى تبدو الواجهات مطابقة تماماً بعد الربط.
5. **الأمان أولاً**: تحقق من الصلاحيات على مستوى الـ Route والـ Controller والـ Policy، وليس فقط إخفاء الروابط من الواجهة.

---

## 2. الأدوار

| الدور | الوصف | طريقة الدخول |
|---|---|---|
| `admin` | مدير المعهد — صلاحيات كاملة | اسم مستخدم وكلمة مرور |
| `staff` | مدرس/كادر تدريسي — يدير دوراته وطلابه وواجباتهم | اسم مستخدم وكلمة مرور |
| `student` | طالب — يرى بياناته فقط | رقم الطالب |
| `parent` | ولي أمر — يرى أبناءه فقط | اسم مستخدم |

> ملاحظة: يمكن لولي الأمر أن يكون مرتبطاً بأكثر من طالب، ويمكن للطالب أن يكون مسجلاً في أكثر من دورة.

---

## 3. مصفوفة الصلاحيات

| الوظيفة | الإدارة | الكادر | الطالب | ولي الأمر |
|---|---|---|---|---|
| لوحة معلومات | كاملة | دوراته | بياناته | أبناؤه |
| إدارة الطلاب | ✅ إنشاء/تعديل/حذف | 👁️ طلاب دوراته فقط | 👁️ ملفه | 👁️ أبناؤه |
| إدارة الدورات | ✅ | 👁️ دوراته | 👁️ دوراته | 👁️ دورات أبنائه |
| التسجيلات | ✅ | ❌ | ❌ | ❌ |
| الحضور والغياب | ✅ الكل | ✅ دوراته فقط | 👁️ حضوره | 👁️ حضور أبنائه |
| الدرجات | ✅ الكل | ✅ دوراته فقط | 👁️ درجاته | 👁️ درجات أبنائه |
| الواجبات | ✅ الكل | ✅ إنشاء ومراجعة دوراته | 👁️ + تسليم | 👁️ + رفع لأبنائه |
| المدفوعات | ✅ الكل | ❌ | 👁️ حسابه | 👁️ حسابات أبنائه |
| الإعلانات | ✅ نشر وإدارة | 👁️ الموجه له | 👁️ الموجه له | 👁️ الموجه له |
| العروض | ✅ | ❌ | 👁️ | 👁️ |
| الجدول | ✅ | 👁️ جدوله | 👁️ جدوله | 👁️ جدول أبنائه |
| التقارير | ✅ كاملة | ✅ دوراته (بدون مالية) | ❌ | ❌ |
| الإعدادات والمستخدمون | ✅ | ❌ | ❌ | ❌ |
| الإشعارات | ✅ إرسال | 👁️ استقبال | 👁️ استقبال | 👁️ استقبال |

---

## 4. قاعدة البيانات

### 4.1 المستخدمون والملفات الشخصية

**`users`**
- `id`
- `name`
- `username` (unique — للطالب يكون رقم الطالب)
- `password`
- `role` enum: `admin`, `staff`, `student`, `parent`
- `phone` nullable
- `email` nullable
- `is_active` boolean
- `last_login_at` nullable
- `remember_token`
- `timestamps`, `softDeletes`

**`students`** (ملف الطالب)
- `id`
- `user_id` FK unique → users
- `student_no` unique
- `stage_id` FK → stages
- `guardian_name`
- `guardian_phone`
- `address` nullable
- `birth_date` nullable
- `enrollment_date`
- `status` enum: `active`, `suspended`, `graduated`
- `notes` nullable
- `timestamps`, `softDeletes`

**`parent_student`** (ربط ولي الأمر بأبنائه)
- `id`
- `parent_id` FK → users
- `student_id` FK → students
- `relation` (أب/أم/وصي)
- `is_primary` boolean
- unique (`parent_id`, `student_id`)

**`staff`** (ملف الكادر)
- `id`
- `user_id` FK unique → users
- `subject`
- `bio`
- `phone`
- `hire_date` nullable
- `is_public` boolean (يظهر في صفحة الكادر العامة)
- `timestamps`

### 4.2 البنية الأكاديمية

**`stages`**
- `id`, `name`, `description`, `sort_order`, `is_visible`, `timestamps`

**`courses`**
- `id`
- `stage_id` FK
- `teacher_id` FK nullable → users (المدرس المسؤول)
- `name`
- `subject`
- `description`
- `price` decimal(12,0) — دينار عراقي
- `seats`
- `schedule_text`
- `is_active`
- `timestamps`

**`course_student`** (التسجيل في الدورات)
- `id`
- `course_id` FK
- `student_id` FK → students
- `fee` decimal(12,0) — رسوم هذه الدورة لهذا الطالب
- `enrolled_at`
- `status` enum: `active`, `completed`, `withdrawn`
- unique (`course_id`, `student_id`)

**`schedule_slots`**
- `id`
- `course_id` FK
- `day_of_week` tinyint (0=الأحد … 6=السبت)
- `start_time`, `end_time`
- `hall`
- `is_active`

### 4.3 الحضور والدرجات

**`attendance_records`**
- `id`
- `course_id` FK
- `student_id` FK
- `date`
- `status` enum: `present`, `late`, `absent`, `excused`
- `note` nullable
- `recorded_by` FK → users
- `timestamps`
- unique (`course_id`, `student_id`, `date`)

**`assessments`** (الامتحانات)
- `id`
- `course_id` FK
- `title`
- `type` enum: `monthly`, `quiz`, `final`, `other`
- `max_score`
- `assessed_at`
- `created_by` FK → users
- `timestamps`

**`grades`**
- `id`
- `assessment_id` FK
- `student_id` FK
- `score`
- `note` nullable
- `recorded_by` FK → users
- `timestamps`
- unique (`assessment_id`, `student_id`)

### 4.4 الواجبات (قلب النظام)

**`homework_tasks`**
- `id`
- `course_id` FK
- `teacher_id` FK nullable → users (المدرس المسؤول عن المراجعة)
- `student_id` FK nullable → students (يُحدد عند رفع ولي الأمر واجب ابنه)
- `title`
- `description`
- `due_date` nullable
- `source` enum: `teacher`, `parent`, `student`, `admin`
- `is_class_task` boolean (واجب عام لكل طلاب الدورة)
- `status` enum: `open`, `submitted`, `under_review`, `reviewed`, `needs_revision`, `closed`
- `created_by` FK → users
- `reviewed_by` FK nullable → users
- `reviewed_at` nullable
- `feedback` text nullable
- `timestamps`

**`homework_submissions`**
- `id`
- `homework_task_id` FK
- `student_id` FK
- `submitted_by` FK → users (الطالب أو ولي الأمر)
- `note` nullable
- `status` enum: `submitted`, `under_review`, `reviewed`, `needs_revision`
- `feedback` text nullable
- `reviewed_by` FK nullable → users
- `reviewed_at` nullable
- `timestamps`
- unique (`homework_task_id`, `student_id`)

**`homework_files`**
- `id`
- `homework_task_id` FK nullable
- `submission_id` FK nullable
- `uploaded_by` FK → users
- `disk` (private)
- `path`
- `original_name`
- `mime`
- `size`
- `timestamps`

> **مسار ولي الأمر:** يختار الابن ← يختار الدورة (من دورات ابنه) ← يرفع الملف/الصور ← يُنشأ `homework_task` بمصدر `parent` و`teacher_id` = مدرس الدورة تلقائياً ← تُنشأ `homework_submission` ← يُرسل إشعار للمدرس.
>
> **مسار المدرس:** يرى الواجب في «واجبات طلابي» ← يفتح الملف ← يكتب ملاحظاته ← يحوّل الحالة إلى `reviewed` أو `needs_revision` ← يُرسل إشعار لولي الأمر والطالب.
>
> **مسار المدرس (واجب عام):** ينشئ `homework_task` بـ `is_class_task = true` ← يظهر لطلاب الدورة ← كل طالب/ولي أمر يرفع `submission` ← المدرس يراجع كل تسليم.

### 4.5 المالية

**`student_charges`** (الالتزامات/الرسوم)
- `id`
- `student_id` FK
- `course_id` FK nullable
- `title`
- `amount` decimal(12,0)
- `due_date` nullable
- `created_by` FK → users
- `timestamps`

**`payments`**
- `id`
- `student_id` FK
- `charge_id` FK nullable
- `amount` decimal(12,0)
- `method` enum: `cash`, `zaincash`, `transfer`
- `receipt_no` unique
- `paid_at`
- `note` nullable
- `recorded_by` FK → users
- `timestamps`

> الرصيد = مجموع `student_charges` − مجموع `payments`. يمكن توزيع الدفعة على أكثر من التزام لاحقاً.

### 4.6 التسجيلات والمحتوى

**`registration_requests`**
- `id`
- `student_name`
- `birth_date` nullable
- `stage_id` FK
- `course_id` FK nullable
- `parent_name`
- `phone`
- `parent_phone`
- `address` nullable
- `note` nullable
- `status` enum: `new`, `contacted`, `accepted`, `rejected`, `enrolled`
- `processed_by` FK nullable → users
- `processed_at` nullable
- `timestamps`

**`announcements`**
- `id`, `title`, `body`, `category`
- `audience` enum: `all`, `students`, `parents`, `staff`
- `stage_id` FK nullable
- `is_pinned`, `is_published`
- `published_at`
- `created_by` FK → users
- `timestamps`

**`offers`**
- `id`, `title`, `description`
- `stage_id` FK nullable
- `discount_percent`
- `old_price`, `price`
- `starts_at`, `ends_at`
- `is_active`
- `created_by` FK → users
- `timestamps`

### 4.7 الإشعارات والإعدادات

**`notifications`**
- `id`, `title`, `body`
- `audience` enum: `all`, `students`, `parents`, `staff`, `user`
- `created_by` FK → users
- `timestamps`

**`notification_recipients`**
- `id`
- `notification_id` FK
- `user_id` FK
- `read_at` nullable
- unique (`notification_id`, `user_id`)

**`settings`**
- `id`, `key` unique, `value` text (JSON), `group`, `timestamps`

المفاتيح الأولية:
`institute.name`, `institute.address`, `institute.phone`, `institute.whatsapp`, `institute.work_hours`, `institute.announcement`, `theme.primary_color`, `theme.secondary_color`, `features.staff_enabled`, `features.registration_open`, `social.instagram`, `social.tiktok`.

**`activity_logs`** (اختياري لكن مفيد)
- `id`, `user_id`, `action`, `model_type`, `model_id`, `changes` JSON, `ip`, `created_at`

---

## 5. العلاقات المختصرة

```text
users 1—1 students
users 1—1 staff
users 1—N parent_student N—1 students
stages 1—N students
stages 1—N courses
users (staff) 1—N courses (teacher_id)
courses N—M students (course_student)
courses 1—N schedule_slots
courses 1—N assessments 1—N grades N—1 students
courses 1—N attendance_records N—1 students
courses 1—N homework_tasks 1—N homework_submissions N—1 students
homework_tasks/submissions 1—N homework_files
students 1—N student_charges 1—N payments
registration_requests N—1 stages
announcements/offers N—1 stages (اختياري)
notifications N—M users (notification_recipients)
```

---

## 6. المسارات والواجهات

### 6.1 الصفحات العامة (بدون تغيير في المسارات)
`/`, `/about`, `/stages`, `/courses`, `/offers`, `/schedule`, `/announcements`, `/results`, `/homework`, `/parent`, `/student`, `/contact`, `/location`, `/staff`, `/notifications`, `/login`, `/register`

- تُقرأ البيانات من قاعدة البيانات مع احترام شروط النشر:
  - المراحل: `is_visible = true`
  - الدورات: `is_active = true`
  - العروض: `is_active` + ضمن تاريخ الصلاحية
  - الإعلانات: `is_published` + الجمهور `all`
  - الكادر: `is_public` + `features.staff_enabled`
- صفحة `/results` تعمل بالبحث برقم الطالب (تعرض النتائج للعامة كما هو الحال الآن) أو تتطلب تسجيل دخول؟ **الافتراضي: تبقى عامة كما في التصميم الحالي.**

### 6.2 الإدارة — `/admin/*`
نفس الـ 16 صفحة الحالية + عمليات CRUD حقيقية:

| الصفحة | العمليات |
|---|---|
| `/admin` | إحصائيات حقيقية |
| `/admin/reports` | تقارير ورسوم بيانية |
| `/admin/students` | إضافة/تعديل/حذف/تعطيل/إعادة كلمة مرور/ربط ولي أمر |
| `/admin/registrations` | مراجعة الطلبات وقبولها → إنشاء حساب طالب |
| `/admin/stages` | CRUD + إظهار/إخفاء |
| `/admin/courses` | CRUD + تعيين مدرس + تسجيل طلاب |
| `/admin/staff` | CRUD + إنشاء حساب كادر |
| `/admin/attendance` | تسجيل/تعديل حضور أي دورة |
| `/admin/grades` | إنشاء امتحانات وإدخال درجات |
| `/admin/homework` | متابعة كل الواجبات والتسليمات |
| `/admin/payments` | رسوم ودفعات وإيصالات |
| `/admin/announcements` | نشر/تثبيت/إخفاء |
| `/admin/offers` | CRUD + تفعيل |
| `/admin/schedule` | إدارة المواعيد |
| `/admin/notifications` | إرسال إشعارات |
| `/admin/settings` | بيانات المعهد والألوان والخيارات |

### 6.3 الكادر — `/teacher/*` (جديد)
| المسار | الواجهة |
|---|---|
| `/teacher` | لوحة المدرس: دوراته، طلابه، واجبات بانتظار المراجعة، حضور اليوم |
| `/teacher/courses` | دوراته فقط |
| `/teacher/courses/{course}` | تفاصيل الدورة وطلابها |
| `/teacher/students` | طلاب دوراته |
| `/teacher/attendance` | تسجيل حضور دوراته |
| `/teacher/grades` | إنشاء امتحان وإدخال درجات |
| `/teacher/homework` | واجبات طلابه + مراجعة التسليمات |
| `/teacher/homework/{task}` | تفاصيل الواجب والملفات والملاحظات |
| `/teacher/schedule` | جدوله |
| `/teacher/profile` | ملفه وكلمة المروره |

> التصميم: نفس `layouts/admin` مع قائمة أقل وبنفس الهوية، أو layout جديد `layouts/portal` بنفس المكونات.

### 6.4 الطالب — `/student/*`
| المسار | الواجهة |
|---|---|
| `/student` | لوحة الطالب |
| `/student/courses` | دوراته |
| `/student/attendance` | حضوره |
| `/student/grades` | درجاته |
| `/student/homework` | واجباته وحالة كل واجب + رفع تسليم |
| `/student/payments` | حسابه ودفعاته |
| `/student/notifications` | إشعاراته |
| `/student/profile` | ملفه وكلمة مروره |

### 6.5 ولي الأمر — `/parent/*`
| المسار | الواجهة |
|---|---|
| `/parent` | لوحة ولي الأمر |
| `/parent/children/{student}` | نظرة شاملة على الابن |
| `/parent/homework` | واجبات الأبناء + رفع واجب جديد |
| `/parent/attendance` | حضور الأبناء |
| `/parent/grades` | درجات الأبناء |
| `/parent/payments` | حسابات الأبناء |
| `/parent/notifications` | إشعاراته |
| `/parent/profile` | ملفه وكلمة مروره |

---

## 7. سيناريوهات العمل

### 7.1 رفع واجب من ولي الأمر → المدرس المختص
1. ولي الأمر يدخل `/parent/homework` ويضغط «رفع واجب».
2. يختار الابن ← تظهر دورات الابن المسجل بها فقط.
3. يختار الدورة ← يكتب عنوان الواجب ووصفه ← يرفع صورة/PDF.
4. النظام يتحقق أن الابن مرتبط بولي الأمر وأنه مسجل في الدورة.
5. يُنشأ الواجب مع `teacher_id` = مدرس الدورة تلقائياً.
6. إذا لم يكن للدورة مدرس → يبقى `teacher_id = null` ويُرسل إشعار للمدير.
7. يُرسل إشعار للمدرس: «واجب جديد من ولي أمر الطالب …».
8. المدرس يراجع، يكتب الملاحظات، ويحوّل الحالة.
9. يُرسل إشعار لولي الأمر والطالب بالنتيجة.

### 7.2 واجب عام من المدرس
1. المدرس ينشئ واجباً لدورته ويحدد تاريخ التسليم.
2. يظهر لجميع طلاب الدورة في `/student/homework` و`/parent/homework`.
3. الطالب أو ولي الأمر يرفع التسليم.
4. المدرس يراجع كل تسليم على حدة ويكتب الملاحظات.
5. تظهر حالة التسليم للطالب وولي الأمر.

### 7.3 قبول تسجيل جديد
1. الزائر يملأ `/register` → `registration_requests` بحالة `new`.
2. الإدارة تراجع في `/admin/registrations` → «قبول».
3. النظام ينشئ حساب طالب + ملف طالب + ربطه بالمرحلة.
4. يمكن ربط ولي أمر موجود أو إنشاء حساب ولي أمر.
5. يُرسل إشعار/تظهر بيانات الدخول للإدارة لطباعتها.

### 7.4 الحضور
1. المدرس يختار الدورة والتاريخ.
2. تظهر قائمة الطلاب المسجلين.
3. يحدد حاضر/متأخر/غائب/بعذر ويحفظ.
4. `upsert` يمنع التكرار لنفس الطالب/الدورة/التاريخ.
5. تظهر النسبة للطالب وولي الأمر والإدارة.

### 7.5 الدرجات
1. المدرس/الإدارة ينشئ امتحاناً (شهري/قصير/نهائي) بدرجة كاملة.
2. تُدخل درجات الطلاب المسجلين في الدورة.
3. تُحسب النسبة والتقدير تلقائياً.
4. تظهر في صفحة الطالب وولي الأمر والتقارير.

### 7.6 المدفوعات
1. الإدارة تضيف رسوماً/التزاماً على الطالب (أو ترتبط برسوم الدورة).
2. تسجل دفعة نقدية/زين كاش/تحويل مع رقم إيصال تلقائي.
3. يُحسب المتبقي تلقائياً.
4. يظهر كشف الحساب للطالب وولي الأمر.

### 7.7 الإشعارات
- أحداث تلقائية: واجب جديد، مراجعة واجب، دفعة جديدة، قبول تسجيل، إعلان.
- إرسال يدوي من الإدارة إلى: الجميع، الطلاب، الأباء، الكادر، مرحلة معينة، دورة معينة، طالب معين.
- عداد غير المقروء في الهيدر.

---

## 8. الحفاظ على التصميم أثناء الربط

1. **لا نعيد كتابة الـ Views**: نستبدل مصادر البيانات فقط.
2. نضيف **Accessors** للنماذج لتوفير الحقول المحسوبة التي كانت تأتي من `config/eyal.php`:
   - `Course::stage_name`, `Course::teacher_name`, `Course::enrolled_count`
   - `Student::stage_name`, `Student::attendance_rate`, `Student::remaining_balance`
3. نحول المصفوفات الثابتة إلى **Collections** من Eloquent، والـ Blade يتعامل معها بنفس الطريقة (`$course['name']` يعمل مع Model).
4. النماذج الحالية (Modals) تبقى كما هي، لكن `action` يصبح مساراً حقيقياً و`method` POST/PUT/DELETE.
5. عند خطأ تحقق، نعيد فتح المودال تلقائياً مع عرض الأخطاء بنفس التصميم.
6. الرسائل تظهر بنفس نظام التوست الحالي عبر `session('success')` / `session('error')`.
7. نضيف صفحات جديدة بنفس الكلاسات والمكونات (`card`, `btn`, `chip`, `input`, `modal-overlay` …).

---

## 9. الأمان

- Laravel Auth الأصلي + جدول `users` + عمود `role`.
- Middleware: `auth`, `role:admin`, `role:staff`, `role:student`, `role:parent`, `active`.
- Policies:
  - `CoursePolicy`: المدرس لا يرى إلا دوراته.
  - `StudentPolicy`: ولي الأمر لا يرى إلا أبناءه؛ الطالب نفسه فقط.
  - `HomeworkPolicy`: المدرس يراجع واجبات دوراته فقط.
  - `PaymentPolicy`: الإدارة فقط تسجل؛ الطالب/ولي الأمر يشاهدون فقط.
- استعلامات مقيدة بالـ scope وليس فقط إخفاء الأزرار.
- رفع الملفات إلى `storage/app/private/homework` مع تحميل عبر Controller محمي، وليس رابطاً عاماً.
- التحقق من نوع وحجم الملف (PDF/JPG/PNG/DOCX، حد 10MB).
- Rate limiting لتسجيل الدخول + كلمات مرور مشفرة + CSRF.
- Soft Deletes للمستخدمين والطلاب للحفاظ على السجلات المالية.
- سجل نشاط `activity_logs` للعمليات الحساسة.

---

## 10. خطة التنفيذ على مراحل

### المرحلة 0 — التأسيس (قاعدة البيانات والنماذج)
- إنشاء قاعدة البيانات وتشغيل migrations.
- إنشاء جميع النماذج والعلاقات.
- Seeders تعيد بيانات العرض الحالية بالكامل (نفس المراحل، الدورات، الكادر، الطلاب، الدفعات، الدرجات، الحضور، الواجبات، الإعلانات، العروض، الجدول، الإشعارات، الإعدادات، المستخدمين).
- **معيار القبول:** الموقع يبدو كما هو تماماً لكن البيانات من قاعدة البيانات.

### المرحلة 1 — المصادقة والأدوار
- استبدال `AuthController` التجريبي بـ Auth حقيقي.
- `EnsureRole` middleware + Policies.
- تحويل كل دور للوحة الخاصة به.
- **معيار القبول:** admin → `/admin`، staff → `/teacher`، student → `/student`، parent → `/parent`، ومنع الوصول لغير المصرح (403).

### المرحلة 2 — ربط الصفحات العامة
- الرئيسية، المراحل، الدورات، العروض، الجدول، الإعلانات، الكادر، الاتصال، الموقع، التسجيل.
- **معيار القبول:** أي تعديل من الإدارة يظهر فوراً في الموقع العام.

### المرحلة 3 — لوحة الإدارة الحقيقية
- CRUD لكل الأقسام + التسجيلات + المدفوعات + الإعدادات.
- **معيار القبول:** كل زر في لوحة الإدارة يعمل ويحفظ في قاعدة البيانات.

### المرحلة 4 — بوابة الطالب وولي الأمر
- الصفحات الجديدة والبيانات الحقيقية + ربط الأبناء.
- **معيار القبول:** كل طالب يرى بياناته فقط، وكل ولي أمر يرى أبناءه فقط.

### المرحلة 5 — بوابة الكادر
- `/teacher/*` كاملة: الدورات، الطلاب، الحضور، الدرجات، الواجبات، الجدول.
- **معيار القبول:** المدرس يرى دوراته وطلابه فقط.

### المرحلة 6 — الواجبات والإشعارات
- مسار رفع ولي الأمر → مدرس الدورة → المراجعة → الإشعار.
- الواجبات العامة والتسليمات.
- **معيار القبول:** واجب ولي الأمر يصل للمدرس الصحيح خلال ثوانٍ مع إشعار.

### المرحلة 7 — التقارير والاختبارات والتحسين
- تقارير الإدارة والمدرس.
- Feature Tests لكل دور وسيناريو.
- تحسين الأداء والفهارس.
- **معيار القبول:** كل الاختبارات ناجحة، ولا يمكن تجاوز الصلاحيات.

---

## 11. الاختبارات المطلوبة

- تسجيل الدخول لكل دور والتحويل الصحيح.
- منع الطالب من فتح درجات طالب آخر.
- منع ولي الأمر من فتح ابن ليس له.
- منع المدرس من فتح دورة ليست له.
- منع الكادر من الوصول للمدفوعات والإعدادات.
- رفع واجب من ولي الأمر → يظهر عند مدرس الدورة الصحيح.
- مراجعة المدرس → تتغير الحالة ويصل الإشعار.
- تسجيل الحضور مرة واحدة لكل طالب/دورة/تاريخ.
- حساب المتبقي المالي بشكل صحيح.
- ظهور/إخفاء المحتوى العام حسب إعدادات الإدارة.

---

## 12. ما الذي سيتغير في الكود الحالي

| الملف الحالي | التغيير |
|---|---|
| `config/eyal.php` | يبقى كبيانات أولية للـ Seeders ثم يصبح غير مستخدم في العرض |
| `app/Support/Eyal.php` | يُستبدل تدريجياً بـ Services/Query Objects |
| `app/Http/Controllers/AuthController.php` | Auth حقيقي |
| `app/Http/Middleware/EnsureEyalAdmin.php` | يُستبدل بـ `EnsureRole` |
| `routes/web.php` | إضافة مجموعات الأدوار ومسارات CRUD |
| `resources/views/public/*` | تعديل مصادر البيانات فقط + صفحات جديدة |
| `resources/views/admin/*` | ربط النماذج بالمسارات الحقيقية |
| `public/js/app.js` | إبقاء المودال/التوست + إضافة إرسال النماذج والتحقق |
| `public/css/*` | بدون تغيير |

---

## 13. قرارات مطلوبة قبل البدء

1. **قاعدة البيانات:** MySQL (الموجودة في `.env`) أم SQLite للتطوير؟
2. **إنشاء الحسابات:** الإدارة فقط تنشئ حسابات الطلاب/الأباء/الكادر، أم نضيف تسجيلاً ذاتياً لولي الأمر؟
3. **ملفات الواجبات:** PDF + صور + Word بحد 10MB؟ وهل نحتاج معاينة داخل الموقع أم تحميل فقط؟
4. **المدرس والمالية:** تأكيد أن المدرس لا يرى المدفوعات (الافتراضي: لا).
5. **البدء:** هل نبدأ بالمرحلة 0 و1 مباشرة؟

# وثيقة تحسين التنسيق بأسلوب Shadcn القابل للتوسع ونظام الترقيم الذكي (Expandable Cards & Smart Pagination Plan)

توثق هذه الخطة المراحل التنفيذية لتحسين عرض الكروت والجداول في الموبايل والويب دون تغيير أو تخريب التصميم الأصلي أو الهوية البصرية للمعهد (`#0B3A63`).

---

## القاعدة الذهبية الصارمة (Golden Rule)

1. **عدم تغيير التصميم الأصلي أو الألوان أو النوافذ المنبثقة (`Modals`) نهائياً**.
2. **معالجة الازدحام وتكدس النصوص فقط**: الكارت الذي يحتوي على تفاصيل كثيرة (مواد متعددة، كورسات، أبناء، نبذة طويلة، ملاحظات، أزرار متعددة) يعرض الملخص الأساسي الواضح في الأعلى، وعند الضغط عليه (أو على زر `التفاصيل ⌄`) يتوسع بنعومة (`Shadcn Collapsible`) ليُظهر باقي المعلومات والأزرار.
3. **نظام ترقيم صفحات ذكي وفوري (`Smart Pagination — بيجينك`)**:
   - أي قائمة كروت أو جدول بيانات (`Data Table`) يحتوي على بيانات كثيرة يتم تقسيمه تلقائياً إلى صفحات منظمة مع شريط ترقيم بأسلوب Shadcn (`السابق`، أرقام الصفحات، `التالي`، وعداد `عرض X–Y من أصل Z` مع خيار تحديد عدد العناصر في الصفحة `12 / 24 / 50`)، ومتوافق 100% مع البحث الفوري (`data-filter-input`) والتبويبات (`data-tabs`).

---

## مراحل التنفيذ (Execution Phases)

### المرحلة 1: بناء المحرك الأساسي للكروت المتوسعة والبيجينك في (`custom.css` و `app.js`)
- [x] إضافة تنسيقات **Shadcn Collapsible Card** (`.sc-card`, `.sc-summary`, `.sc-chevron`, `.sc-details`, `.sc-meta-grid`) في `public/css/custom.css`.
- [x] إضافة تنسيقات **Shadcn Pagination Bar** (`.sc-pagination`, `.sc-page-btn`, `.sc-page-info`, `.sc-page-size`) في `public/css/custom.css`.
- [x] برمجة محرك التوسيع السلس (`[data-collapse-trigger]`) ومحرك البيجينك الذكي المتوافق مع البحث والتبويبات (`[data-paginate]`) في `public/js/app.js`.

### المرحلة 2: صفحات لوحة الإدارة (`Admin Portal`)
- [x] **1. إدارة الطلاب (`resources/views/admin/students.blade.php`)**:
  - تحويل كارت الطالب إلى كارت قابل للتوسع (الملخص: الاسم، الرقم، المرحلة، الحالة المالية + عند التوسيع: الكورسات، المواد، ولي الأمر، الهاتف، نسبة الحضور، الملاحظات، وأزرار الإجراءات).
  - تفعيل البيجينك الذكي على قائمة الطلاب (`#studentsList`).
- [x] **2. إدارة أولياء الأمور (`resources/views/admin/parents.blade.php`)**:
  - تحويل كارت ولي الأمر إلى كارت قابل للتوسع (الملخص: الاسم، الهاتف، عدد الأبناء، الحالة + عند التوسيع: قائمة الأبناء المفصلة، اسم المستخدم، وأزرار المراسلة والتعديل والحذف).
  - تفعيل البيجينك الذكي على قائمة أولياء الأمور (`#parentsList`).
- [x] **3. إدارة الكادر التدريسي (`resources/views/admin/staff.blade.php`)**:
  - تحويل كارت المدرس إلى كارت قابل للتوسع (الملخص: الاسم، التخصص، عدد المواد والطلاب + عند التوسيع: المواد حسب المرحلة، الكورسات، النبذة، الهاتف، وأزرار التعديل والحذف).
  - تفعيل البيجينك الذكي على قائمة الكادر.
- [x] **4. إدارة الكورسات (`resources/views/admin/courses.blade.php`)**:
  - تحويل كارت الكورس إلى كارت قابل للتوسع (الملخص: اسم الكورس، المرحلة، السعر، الحالة، عدد المشتركين + عند التوسيع: توزيع المواد والمدرسين، الوصف، الجدول، المقاعد، وأزرار التعديل والحذف).
  - تفعيل البيجينك الذكي على شبكة الكورسات.
- [x] **5. طلبات التسجيل (`resources/views/admin/registrations.blade.php`)**:
  - تحويل كارت طلب التسجيل إلى كارت قابل للتوسع وتفعيل البيجينك الذكي المتوافق مع تبويبات الحالة (`الكل / جديدة / مقبولة / مرفوضة`).
- [x] **6. الحسابات والدفعات (`resources/views/admin/payments.blade.php`) والدرجات (`resources/views/admin/grades.blade.php`) والحضور (`resources/views/admin/attendance.blade.php`) والواجبات (`resources/views/admin/homework.blade.php`) والإعدادات (`resources/views/admin/settings.blade.php`)**:
  - تفعيل البيجينك الذكي على جداول الدفعات والأقساط والدرجات والحضور وحسابات المستخدمين وكروت الواجبات.
- [x] **7. الإعلانات (`resources/views/admin/announcements.blade.php`) والإشعارات (`resources/views/admin/notifications.blade.php`) والعروض (`resources/views/admin/offers.blade.php`) والمراحل (`resources/views/admin/stages.blade.php`)**:
  - إضافة شريط بحث فوري وتفعيل البيجينك الذكي (`data-paginate`) على قوائم الإعلانات والإشعارات وشبكات العروض والمراحل.

### المرحلة 3: صفحات بوابة المدرس (`Teacher Portal`)
- [x] **1. طلابي (`resources/views/teacher/students.blade.php`)**:
  - إضافة عرض كروت قابلة للتوسع للموبايل والويب، وتفعيل البيجينك الذكي.
- [x] **2. واجباتي (`resources/views/teacher/homework.blade.php`) وتفاصيل تسليمات الواجب (`resources/views/teacher/homework-show.blade.php`)**:
  - تنظيم كروت الواجبات والتسليمات وتفعيل البيجينك الذكي.
- [x] **3. دوراتي (`resources/views/teacher/courses.blade.php`) وتفاصيل الدورة (`resources/views/teacher/course.blade.php`) والحضور (`resources/views/teacher/attendance.blade.php`) والدرجات (`resources/views/teacher/grades.blade.php`)**:
  - تفعيل البيجينك الذكي على جداول طلاب الدورة، كشف الحضور، وقائمة الامتحانات والدرجات.

### المرحلة 4: بوابة ولي الأمر والطالب والصفحات العامة (`Parent, Student & Public`)
- [x] **1. صفحة ولي الأمر (`resources/views/public/parent.blade.php`)**:
  - تفعيل البيجينك الذكي في أقسام الواجبات، الدرجات، سجل الحضور، وسجل الدفعات المالية.
- [x] **2. صفحة الدورات العامة (`resources/views/public/courses.blade.php`) والكادر العام (`resources/views/public/staff.blade.php`)**:
  - تفعيل البيجينك الذكي عند كثرة الدورات والكادر.
- [x] **3. صفحات الإعلانات العامة (`resources/views/public/announcements.blade.php`) والإشعارات (`resources/views/public/notifications.blade.php`) والواجبات (`resources/views/public/homework.blade.php`) والعروض (`resources/views/public/offers.blade.php`)**:
  - تفعيل البيجينك الذكي على جميع القوائم العامة لضمان سرعة وأناقة التصفح مهما زاد عدد السجلات.

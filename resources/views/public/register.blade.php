@extends('layouts.public')

@section('title', 'تسجيل طالب جديد')

@section('content')
    <div class="fade-in mx-auto max-w-lg">
        <h1 class="mb-1 text-2xl font-extrabold">تسجيل طالب جديد</h1>
        <p class="mb-4 text-sm text-primary/70">املأ النموذج ثم اضغط "إرسال طلب التسجيل" – سيتم إرسال طلبك مباشرة إلى واتساب المعهد.</p>

        <div class="card p-5">
            @if ($errors->any())
                <div class="mb-3 rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form id="registerForm" class="space-y-4" method="POST" action="{{ route('register.store') }}" novalidate>
                @csrf
                <div>
                    <label class="label" for="student_name">اسم الطالب الكامل *</label>
                    <input id="student_name" name="student_name" class="input" placeholder="مثال: أحمد محمد علي" value="{{ old('student_name') }}" required>
                </div>
                <div>
                    <label class="label" for="parent_name">اسم ولي الأمر *</label>
                    <input id="parent_name" name="parent_name" class="input" placeholder="اسم ولي الأمر" value="{{ old('parent_name') }}" required>
                </div>
                <div>
                    <label class="label" for="phone">رقم الهاتف *</label>
                    <input id="phone" name="phone" class="input" placeholder="07XXXXXXXXX" inputmode="tel" value="{{ old('phone') }}" required>
                </div>
                <div>
                    <label class="label" for="stage">المرحلة الدراسية *</label>
                    <select id="stage" name="stage" class="input" required>
                        <option value="">— اختر المرحلة —</option>
                        @foreach ($stages as $stage)
                            <option value="{{ $stage['id'] }}" @selected((string) old('stage') === (string) $stage['id'])>{{ $stage['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="course">الدورة / المادة (اختياري)</label>
                    <select id="course" name="course" class="input">
                        <option value="">— اختر الدورة —</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course['id'] }}" @selected((string) old('course') === (string) $course['id'])>{{ $course['name'] }} — {{ $course['stage_name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="notes">ملاحظات (اختياري)</label>
                    <textarea id="notes" name="notes" class="input min-h-[90px]" placeholder="أي معلومات إضافية تود إخبارنا بها...">{{ old('notes') }}</textarea>
                </div>

                <div class="rounded-xl bg-primary/4 p-3 text-[11px] leading-relaxed text-primary/60">
                    <div class="mb-1 flex items-center gap-1 font-extrabold text-primary">
                        <x-icon name="info" :size="13" /> ملاحظة
                    </div>
                    سيتم فتح واتساب المعهد مع رسالة جاهزة تحتوي بيانات الطلب. يمكنك مراجعة الرسالة قبل الإرسال.
                </div>

                <button type="submit" class="btn btn-wa w-full">
                    <x-icon name="whatsapp" :size="17" /> إرسال طلب التسجيل
                </button>
            </form>
        </div>

        <div class="mt-4 grid gap-2 sm:grid-cols-3">
            <div class="card p-3 text-center">
                <div class="mx-auto mb-1 flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <x-icon name="file-text" :size="17" />
                </div>
                <div class="text-[11px] font-bold text-primary">1. أرسل الطلب</div>
            </div>
            <div class="card p-3 text-center">
                <div class="mx-auto mb-1 flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <x-icon name="phone" :size="17" />
                </div>
                <div class="text-[11px] font-bold text-primary">2. تواصل معك الإدارة</div>
            </div>
            <div class="card p-3 text-center">
                <div class="mx-auto mb-1 flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <x-icon name="check-circle" :size="17" />
                </div>
                <div class="text-[11px] font-bold text-primary">3. تأكيد التسجيل</div>
            </div>
        </div>
    </div>
@endsection

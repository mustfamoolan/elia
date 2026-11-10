@extends('layouts.public')

@section('title', 'نتائج الطلاب')

@section('content')
    @php $user = session('eyal_user'); @endphp

    <div class="fade-in">
        <h1 class="mb-1 text-2xl font-extrabold">نتائج الطلاب</h1>
        <p class="mb-4 text-sm text-primary/70">لعرض النتائج: سجّل دخولك كطالب أو ولي أمر، أو ابحث برقم الطالب</p>

        <div class="card mb-4 space-y-3 p-4">
            <form method="GET" action="{{ url('/results') }}" class="space-y-3">
                <div>
                    <label class="label" for="q">ابحث باسم الطالب أو رقمه</label>
                    <input id="q" name="q" class="input" placeholder="اسم الطالب أو رقم الطالب..." value="{{ $query }}">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="search" :size="16" /> بحث
                    </button>
                    @if ($user && in_array($user['role'], ['student', 'parent']))
                        <a href="{{ url($user['role'] === 'parent' ? '/parent' : '/student') }}" class="btn btn-outline">حسابي</a>
                    @else
                        <a href="{{ url('/login') }}" class="btn btn-outline">تسجيل الدخول</a>
                    @endif
                </div>
            </form>
        </div>

        @if ($student)
            <div class="card mb-4 bg-primary p-5 text-white">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="circle-user" :size="26" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-lg font-extrabold">{{ $student['name'] }}</div>
                        <div class="text-xs opacity-80">رقم الطالب: {{ $student['student_no'] }} • {{ $student['stage_name'] }} • {{ $student['study_type'] }}</div>
                    </div>
                    <div class="chip bg-white/15 text-white">{{ $grades->count() }} نتيجة</div>
                </div>
            </div>

            @if ($grades->isEmpty())
                <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                    <p class="text-sm font-bold text-primary/60">لا توجد درجات مسجلة لهذا الطالب بعد</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach ($grades as $grade)
                        @php
                            $percent = (int) round(($grade['score'] / max(1, $grade['max'])) * 100);
                            $tone = $percent >= 85 ? 'bg-emerald-50 text-emerald-700' : ($percent >= 60 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                        @endphp
                        <div class="card flex flex-wrap items-center gap-3 p-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-sm font-extrabold text-primary">
                                {{ $percent }}%
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-extrabold text-primary">{{ $grade['exam'] }}</div>
                                <div class="text-[11px] text-primary/60">{{ $grade['course'] }} • {{ \Illuminate\Support\Carbon::parse($grade['date'])->format('Y/m/d') }}</div>
                            </div>
                            <div class="chip {{ $tone }}">{{ $grade['score'] }} / {{ $grade['max'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        @elseif ($query !== '')
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">🔎</div>
                <p class="text-sm font-bold text-primary/60">لم يتم العثور على طالب بهذا الاسم أو الرقم</p>
                <p class="text-xs text-primary/50">جرّب البحث برقم الطالب أو تواصل مع الإدارة.</p>
            </div>
        @else
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                <p class="text-sm font-bold text-primary/60">لا توجد نتائج لعرضها حالياً</p>
                <p class="text-xs text-primary/50">أدخل اسم الطالب أو رقمه في البحث، أو سجّل الدخول لعرض نتائجك تلقائياً.</p>
            </div>
        @endif
    </div>
@endsection

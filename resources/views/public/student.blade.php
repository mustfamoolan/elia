@extends('layouts.public')

@section('title', 'حساب الطالب')

@section('content')
    <div class="fade-in">
        @if ($notice)
            <div class="mx-auto max-w-lg">
                <div class="card flex flex-col items-center gap-3 p-8 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/5 text-primary">
                        <x-icon name="graduation-cap" :size="32" />
                    </div>
                    <h1 class="text-xl font-extrabold">حساب الطالب</h1>
                    @if ($notice === 'guest')
                        <p class="text-sm leading-relaxed text-primary/70">سجّل الدخول برقم الطالب لعرض درجاتك، واجباتك، وحضورك.</p>
                        <div class="mt-2 flex w-full flex-col gap-2 sm:flex-row">
                            <a href="{{ url('/login') }}" class="btn btn-dark flex-1">تسجيل الدخول</a>
                            <a href="{{ url('/results') }}" class="btn btn-outline flex-1">نتائج الطلاب</a>
                        </div>
                        <p class="mt-2 text-[11px] text-primary/50">حساب تجريبي: 101 / 123456</p>
                    @elseif ($notice === 'no_student')
                        <p class="text-sm leading-relaxed text-primary/70">لا يوجد ملف طالب مرتبط بهذا الحساب حالياً. يرجى التواصل مع إدارة المعهد.</p>
                        <a href="{{ url('/contact') }}" class="btn btn-dark mt-2">تواصل مع الإدارة</a>
                    @else
                        <p class="text-sm leading-relaxed text-primary/70">
                            أنت مسجّل الدخول بحساب مدير المعهد. صفحة الطالب متاحة لحسابات الطلاب وأولياء الأمور فقط.
                        </p>
                        <a href="{{ url('/admin') }}" class="btn btn-dark mt-2">الانتقال إلى لوحة الإدارة</a>
                    @endif
                </div>
            </div>
        @elseif ($student)
            @php
                $avg = $grades->count() ? (int) round($grades->avg(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;
                $attendanceRate = $student['attendance'];
            @endphp

            <div class="card mb-4 bg-primary p-5 text-white">
                <div class="flex items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="circle-user" :size="30" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-lg font-extrabold">{{ $student['name'] }}</div>
                        <div class="text-xs opacity-80">
                            <span>رقم الطالب: </span>{{ $student['student_no'] }}<span> • </span>{{ $student['study_type'] }}<span> • </span>{{ $student['phone'] }}
                        </div>
                    </div>
                    <span class="chip bg-white/15 text-white">{{ $student['stage_name'] }}</span>
                </div>
            </div>

            <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ $student['stage_name'] }}</div>
                    <div class="text-[11px] font-bold text-primary/60">المرحلة</div>
                </div>
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ count($student['subjects']) }}</div>
                    <div class="text-[11px] font-bold text-primary/60">عدد المواد</div>
                </div>
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ $grades->count() }}</div>
                    <div class="text-[11px] font-bold text-primary/60">الامتحانات</div>
                </div>
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ $attendance->count() }}</div>
                    <div class="text-[11px] font-bold text-primary/60">سجلات الحضور</div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="book-open" :size="17" class="text-primary" /> المواد المسجلة
                    </h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($student['subjects'] as $subject)
                            <span class="chip bg-primary/5 text-primary">{{ $subject }}</span>
                        @endforeach
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div class="rounded-xl bg-emerald-50 p-3 text-center">
                            <div class="text-sm font-extrabold text-emerald-700">{{ $attendanceRate }}%</div>
                            <div class="text-[10px] text-emerald-600">نسبة الحضور</div>
                        </div>
                        <div class="rounded-xl bg-blue-50 p-3 text-center">
                            <div class="text-sm font-extrabold text-blue-700">{{ $avg }}%</div>
                            <div class="text-[10px] text-blue-600">معدل الدرجات</div>
                        </div>
                    </div>
                </section>

                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="clipboard-list" :size="17" class="text-primary" /> آخر الدرجات
                    </h2>
                    @forelse ($grades->take(4) as $grade)
                        <div class="flex items-center gap-3 border-b border-primary/5 py-2 last:border-0">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">{{ $grade['exam'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ $grade['course'] }} • {{ \Illuminate\Support\Carbon::parse($grade['date'])->format('Y/m/d') }}</div>
                            </div>
                            <span class="chip bg-primary/5 text-primary">{{ $grade['score'] }}/{{ $grade['max'] }}</span>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد درجات بعد</p>
                    @endforelse
                </section>
            </div>

            <section class="card mt-4 p-5">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-extrabold">
                    <x-icon name="info" :size="17" class="text-primary" /> ملاحظات
                </h2>
                <p class="text-sm leading-relaxed text-primary/70">{{ $student['notes'] ?: 'لا توجد ملاحظات حالياً.' }}</p>
            </section>

            <section class="card mt-4 p-5">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="list-todo" :size="17" class="text-primary" /> واجباتي
                    </h2>
                    <span class="chip bg-primary/5 text-primary/60">{{ $homework->count() }} واجب</span>
                    <a href="{{ url('/homework') }}" class="btn btn-outline btn-sm mr-auto">
                        <x-icon name="arrow-left" :size="13" /> عرض الكل
                    </a>
                </div>
                @forelse ($homework->take(4) as $item)
                    @php
                        $tone = match ($item['submission_status'] ?? null) {
                            'submitted' => 'bg-amber-50 text-amber-700',
                            'under_review' => 'bg-violet-50 text-violet-700',
                            'reviewed' => 'bg-emerald-50 text-emerald-700',
                            'needs_revision' => 'bg-rose-50 text-rose-700',
                            default => 'bg-primary/5 text-primary/60',
                        };
                    @endphp
                    <div class="flex flex-wrap items-center gap-2 border-b border-primary/5 py-2 last:border-0">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">{{ $item['title'] }}</div>
                            <div class="text-[10px] text-primary/50">
                                {{ $item['course'] }}
                                @if ($item['due']) • التسليم: {{ \Illuminate\Support\Carbon::parse($item['due'])->format('Y/m/d') }} @endif
                            </div>
                        </div>
                        <span class="chip {{ $tone }}">{{ $item['submission_label'] ?? 'لم يُسلَّم' }}</span>
                        <button type="button" class="btn btn-outline btn-sm" data-modal-open="studentSubmit{{ $item['id'] }}">
                            <x-icon name="upload" :size="12" /> {{ ! empty($item['submission_status']) ? 'إعادة رفع' : 'رفع' }}
                        </button>
                    </div>

                    <div class="modal-overlay" id="studentSubmit{{ $item['id'] }}">
                        <div class="modal-panel modal-md">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-extrabold">رفع واجب: {{ $item['title'] }}</h3>
                                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                                    <x-icon name="x" :size="20" class="text-primary" />
                                </button>
                            </div>
                            <form class="space-y-3" method="POST" action="{{ route('homework.submit', $item['id']) }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $student['id'] }}">
                                <div>
                                    <label class="label">ملف الواجب *</label>
                                    <input type="file" name="file" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" required>
                                    <p class="mt-1 text-[10px] text-primary/50">PDF أو صورة أو Word — الحد الأقصى 10 ميغابايت.</p>
                                </div>
                                <div>
                                    <label class="label">ملاحظة (اختياري)</label>
                                    <textarea name="note" class="input min-h-[80px]" placeholder="اكتب ملاحظة للمدرس..."></textarea>
                                </div>
                                <div class="flex gap-2 pt-1">
                                    <button type="submit" class="btn btn-dark flex-1">
                                        <x-icon name="send" :size="16" /> إرسال للمدرس
                                    </button>
                                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد واجبات حالياً</p>
                @endforelse
            </section>

            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <a href="{{ url('/homework') }}" class="card flex flex-col items-center gap-1 p-3 text-center transition hover:shadow-soft">
                    <x-icon name="list-todo" :size="20" class="text-primary" />
                    <span class="text-[11px] font-bold text-primary">الواجبات</span>
                </a>
                <a href="{{ url('/results') }}" class="card flex flex-col items-center gap-1 p-3 text-center transition hover:shadow-soft">
                    <x-icon name="clipboard-list" :size="20" class="text-primary" />
                    <span class="text-[11px] font-bold text-primary">النتائج</span>
                </a>
                <a href="{{ url('/schedule') }}" class="card flex flex-col items-center gap-1 p-3 text-center transition hover:shadow-soft">
                    <x-icon name="calendar-days" :size="20" class="text-primary" />
                    <span class="text-[11px] font-bold text-primary">الجدول</span>
                </a>
                <a href="{{ url('/notifications') }}" class="card flex flex-col items-center gap-1 p-3 text-center transition hover:shadow-soft">
                    <x-icon name="bell" :size="20" class="text-primary" />
                    <span class="text-[11px] font-bold text-primary">الإشعارات</span>
                </a>
            </div>
        @endif
    </div>
@endsection

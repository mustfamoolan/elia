@extends('layouts.public')

@section('title', 'حساب ولي الأمر')

@section('content')
    @php $user = session('eyal_user'); @endphp

    <div class="fade-in">
        @if ($notice)
            <div class="mx-auto max-w-lg">
                <div class="card flex flex-col items-center gap-3 p-8 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/5 text-primary">
                        <x-icon name="circle-user" :size="32" />
                    </div>
                    <h1 class="text-xl font-extrabold">حساب ولي الأمر</h1>
                    @if ($notice === 'guest')
                        <p class="text-sm leading-relaxed text-primary/70">سجّل الدخول بحساب ولي الأمر لمتابعة حضور ابنك، درجاته، ودفعاته.</p>
                        <div class="mt-2 flex w-full flex-col gap-2 sm:flex-row">
                            <a href="{{ url('/login') }}" class="btn btn-dark flex-1">تسجيل الدخول</a>
                            <a href="{{ url('/register') }}" class="btn btn-outline flex-1">تسجيل طالب</a>
                        </div>
                        <p class="mt-2 text-[11px] text-primary/50">حساب تجريبي: parent / 123456</p>
                    @elseif ($notice === 'no_child')
                        <p class="text-sm leading-relaxed text-primary/70">لا يوجد طالب مرتبط بحساب ولي الأمر حالياً. يرجى التواصل مع إدارة المعهد لربط حساب ابنك.</p>
                        <a href="{{ url('/contact') }}" class="btn btn-dark mt-2">تواصل مع الإدارة</a>
                    @else
                        <p class="text-sm leading-relaxed text-primary/70">
                            أنت مسجّل الدخول بحساب {{ $notice === 'student' ? 'طالب' : 'مدير المعهد' }}. صفحة ولي الأمر متاحة لحسابات أولياء الأمور فقط.
                        </p>
                        <a href="{{ url($notice === 'student' ? '/student' : '/admin') }}" class="btn btn-dark mt-2">الانتقال إلى حسابي</a>
                    @endif
                </div>
            </div>
        @elseif ($child)
            @php
                $attendanceRate = $child['attendance'];
                $totalPaid = $payments->sum('amount');
                $remaining = max(0, $child['fee'] - $totalPaid);
                $avg = $grades->count() ? (int) round($grades->avg(fn ($g) => ($g['score'] / max(1, $g['max'])) * 100)) : 0;
            @endphp

            <div class="card mb-4 bg-primary p-5 text-white">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="circle-user" :size="30" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-lg font-extrabold">{{ $child['name'] }}</div>
                        <div class="text-xs opacity-80">رقم الطالب: {{ $child['student_no'] }} • {{ $child['stage_name'] }} • ولي الأمر: {{ $child['parent'] }}</div>
                    </div>
                    <span class="chip bg-white/15 text-white">حساب ولي الأمر</span>
                </div>
            </div>

            <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ $attendanceRate }}%</div>
                    <div class="text-[11px] font-bold text-primary/60">نسبة الحضور</div>
                </div>
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ $avg }}%</div>
                    <div class="text-[11px] font-bold text-primary/60">معدل الدرجات</div>
                </div>
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ $grades->count() }}</div>
                    <div class="text-[11px] font-bold text-primary/60">الامتحانات</div>
                </div>
                <div class="card p-3 text-center">
                    <div class="text-lg font-extrabold text-primary">{{ number_format($remaining) }}</div>
                    <div class="text-[11px] font-bold text-primary/60">المتبقي (د.ع)</div>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="clipboard-list" :size="17" class="text-primary" /> آخر الدرجات
                    </h2>
                    @forelse ($grades as $grade)
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

                <section class="card p-5">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="calendar-check" :size="17" class="text-primary" /> سجل الحضور
                    </h2>
                    @forelse ($attendance as $record)
                        @php
                            $tone = $record['status'] === 'present' ? 'bg-emerald-50 text-emerald-700' : ($record['status'] === 'late' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');
                            $label = $record['status'] === 'present' ? 'حاضر' : ($record['status'] === 'late' ? 'متأخر' : 'غائب');
                        @endphp
                        <div class="flex items-center gap-3 border-b border-primary/5 py-2 last:border-0">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">{{ $record['subject'] }}</div>
                                <div class="text-[10px] text-primary/50">{{ \Illuminate\Support\Carbon::parse($record['date'])->format('Y/m/d') }}</div>
                            </div>
                            <span class="chip {{ $tone }}">{{ $label }}</span>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs font-bold text-primary/50">لا يوجد سجل حضور بعد</p>
                    @endforelse
                </section>
            </div>

            <section class="card mt-4 p-5">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-extrabold">
                    <x-icon name="wallet" :size="17" class="text-primary" /> الحسابات والدفعات
                </h2>
                <div class="mb-3 grid grid-cols-3 gap-2">
                    <div class="rounded-xl bg-primary/5 p-3 text-center">
                        <div class="text-xs font-extrabold text-primary">{{ number_format($child['fee']) }}</div>
                        <div class="text-[10px] text-primary/50">قيمة الاشتراك</div>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-3 text-center">
                        <div class="text-xs font-extrabold text-emerald-700">{{ number_format($totalPaid) }}</div>
                        <div class="text-[10px] text-emerald-600">المدفوع</div>
                    </div>
                    <div class="rounded-xl bg-rose-50 p-3 text-center">
                        <div class="text-xs font-extrabold text-rose-700">{{ number_format($remaining) }}</div>
                        <div class="text-[10px] text-rose-600">المتبقي</div>
                    </div>
                </div>
                @forelse ($payments as $payment)
                    <div class="flex items-center gap-3 border-b border-primary/5 py-2 last:border-0">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">{{ number_format($payment['amount']) }} د.ع</div>
                            <div class="text-[10px] text-primary/50">{{ $payment['method'] }} • {{ \Illuminate\Support\Carbon::parse($payment['date'])->format('Y/m/d') }}</div>
                        </div>
                        <span class="chip bg-emerald-50 text-emerald-700">{{ $payment['receipt'] }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد دفعات مسجلة</p>
                @endforelse
            </section>

            <section class="card mt-4 p-5">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold">
                        <x-icon name="book-open" :size="17" class="text-primary" /> واجبات {{ $child['name'] }}
                    </h2>
                    <span class="chip bg-primary/5 text-primary/60">{{ $homework->count() }} واجب</span>
                    <button type="button" class="btn btn-dark btn-sm mr-auto" data-modal-open="parentHomeworkModal">
                        <x-icon name="upload" :size="14" /> رفع واجب جديد
                    </button>
                </div>

                @forelse ($homework as $item)
                    @php
                        $submitted = ! empty($item['submission_status']);
                        $tone = match ($item['submission_status'] ?? null) {
                            'submitted' => 'bg-amber-50 text-amber-700',
                            'under_review' => 'bg-violet-50 text-violet-700',
                            'reviewed' => 'bg-emerald-50 text-emerald-700',
                            'needs_revision' => 'bg-rose-50 text-rose-700',
                            default => 'bg-primary/5 text-primary/60',
                        };
                    @endphp
                    <div class="border-b border-primary/5 py-3 last:border-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-extrabold text-primary">{{ $item['title'] }}</div>
                                <div class="text-[10px] text-primary/50">
                                    {{ $item['course'] }} • {{ $item['teacher'] }}
                                    @if ($item['due']) • التسليم: {{ \Illuminate\Support\Carbon::parse($item['due'])->format('Y/m/d') }} @endif
                                </div>
                            </div>
                            <span class="chip {{ $tone }}">{{ $item['submission_label'] ?? 'لم يُسلَّم' }}</span>
                            <button type="button" class="btn btn-outline btn-sm" data-modal-open="parentSubmit{{ $item['id'] }}">
                                <x-icon name="upload" :size="12" /> {{ $submitted ? 'إعادة رفع' : 'رفع' }}
                            </button>
                        </div>

                        @if (! empty($item['files']))
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($item['files'] as $file)
                                    <a href="{{ route('homework.download', $file['id']) }}" class="chip bg-primary/5 text-primary">
                                        <x-icon name="download" :size="11" /> {{ $file['name'] }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if (! empty($item['feedback']))
                            <div class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[10px] font-bold text-amber-700">
                                ملاحظات المدرس: {{ $item['feedback'] }}
                            </div>
                        @endif
                    </div>

                    <div class="modal-overlay" id="parentSubmit{{ $item['id'] }}">
                        <div class="modal-panel modal-md">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-extrabold">رفع واجب: {{ $item['title'] }}</h3>
                                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                                    <x-icon name="x" :size="20" class="text-primary" />
                                </button>
                            </div>
                            <form class="space-y-3" method="POST" action="{{ route('homework.submit', $item['id']) }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $child['id'] }}">
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
                    <p class="py-6 text-center text-xs font-bold text-primary/50">لا توجد واجبات مرتبطة بابنك حالياً</p>
                @endforelse
            </section>

            <div class="modal-overlay" id="parentHomeworkModal">
                <div class="modal-panel modal-md">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-extrabold">رفع واجب جديد</h3>
                        <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                            <x-icon name="x" :size="20" class="text-primary" />
                        </button>
                    </div>
                    <form class="space-y-3" method="POST" action="{{ route('parent.homework.store') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $child['id'] }}">
                        <div>
                            <label class="label">الدورة *</label>
                            <select name="course_id" class="input" required>
                                <option value="">— اختر الدورة —</option>
                                @foreach ($child['courses'] as $course)
                                    <option value="{{ $course['id'] }}">{{ $course['name'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[10px] text-primary/50">يُرسل الواجب تلقائياً إلى مدرس الدورة المختارة.</p>
                        </div>
                        <div>
                            <label class="label">عنوان الواجب *</label>
                            <input name="title" class="input" placeholder="مثال: حل تمارين الفصل الثاني" required>
                        </div>
                        <div>
                            <label class="label">وصف الواجب</label>
                            <textarea name="description" class="input min-h-[70px]" placeholder="تفاصيل إضافية للمدرس..."></textarea>
                        </div>
                        <div>
                            <label class="label">تاريخ التسليم</label>
                            <input type="date" name="due_date" class="input">
                        </div>
                        <div>
                            <label class="label">ملف الواجب *</label>
                            <input type="file" name="file" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" required>
                            <p class="mt-1 text-[10px] text-primary/50">PDF أو صورة أو Word — الحد الأقصى 10 ميغابايت.</p>
                        </div>
                        <div>
                            <label class="label">ملاحظة (اختياري)</label>
                            <textarea name="note" class="input min-h-[70px]" placeholder="ملاحظة للمدرس..."></textarea>
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
        @endif
    </div>
@endsection

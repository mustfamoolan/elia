@extends('layouts.public')

@section('title', 'الواجبات')

@section('content')
    @php
        $colors = [
            'blue' => ['bg-blue-50', 'text-blue-600'],
            'violet' => ['bg-violet-50', 'text-violet-600'],
            'emerald' => ['bg-emerald-50', 'text-emerald-600'],
            'amber' => ['bg-amber-50', 'text-amber-600'],
        ];
    @endphp

    <div class="fade-in">
        <h1 class="mb-1 text-2xl font-extrabold">الواجبات</h1>
        <p class="mb-4 text-sm text-primary/70">
            @if ($student)
                واجبات مرحلة {{ $student['stage_name'] }} — {{ $student['name'] }}
            @else
                جميع الواجبات المنشورة من قبل المدرسين والإدارة
            @endif
        </p>

        @if ($homework->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                <p class="text-sm font-bold text-primary/60">لا توجد واجبات منشورة حالياً</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($homework as $item)
                    @php
                        [$bg, $color] = $colors[$item['color']] ?? ['bg-primary/5', 'text-primary'];
                        $days = (int) floor((strtotime($item['due']) - strtotime(now()->toDateString())) / 86400);
                        $status = $days < 0 ? ['منتهي', 'bg-slate-100 text-slate-600'] : ($days <= 2 ? ['قريب الانتهاء', 'bg-amber-50 text-amber-700'] : ['متاح', 'bg-emerald-50 text-emerald-700']);
                    @endphp
                    <article class="card p-5">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="chip {{ $bg }} {{ $color }}">
                                <x-icon name="book-open" :size="13" /> {{ $item['course'] }}
                            </span>
                            <span class="chip {{ $status[1] }}">{{ $status[0] }}</span>
                            @if (! empty($item['submission_label']))
                                <span class="chip bg-violet-50 text-violet-700">{{ $item['submission_label'] }}</span>
                            @endif
                            <span class="mr-auto flex items-center gap-1 text-[11px] font-bold text-primary/50">
                                <x-icon name="calendar" :size="13" />
                                التسليم: {{ \Illuminate\Support\Carbon::parse($item['due'])->format('Y/m/d') }}
                            </span>
                        </div>
                        <h2 class="text-base font-extrabold text-primary">{{ $item['title'] }}</h2>
                        <p class="mt-1 text-sm leading-relaxed text-primary/70">{{ $item['description'] }}</p>

                        @if (! empty($item['files']))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($item['files'] as $file)
                                    <a href="{{ route('homework.download', $file['id']) }}" class="chip bg-primary/5 text-primary">
                                        <x-icon name="download" :size="12" /> {{ $file['name'] }} ({{ $file['size'] }})
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if (! empty($item['feedback']))
                            <div class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-[11px] font-bold text-amber-700">
                                ملاحظات المدرس: {{ $item['feedback'] }}
                            </div>
                        @endif

                        @if (! empty($item['submission_files']))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($item['submission_files'] as $file)
                                    <a href="{{ route('homework.download', $file['id']) }}" class="chip bg-emerald-50 text-emerald-700">
                                        <x-icon name="file-text" :size="12" /> {{ $file['name'] }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-primary/5 pt-3 text-[11px] font-bold text-primary/50">
                            <span class="flex items-center gap-1"><x-icon name="user-round" :size="13" /> {{ $item['teacher'] }}</span>
                            <span class="flex items-center gap-1"><x-icon name="layers" :size="13" /> {{ $item['stage'] }}</span>
                            @if ($student)
                                <button type="button" class="btn btn-outline btn-sm mr-auto" data-modal-open="homeworkSubmit{{ $item['id'] }}">
                                    <x-icon name="upload" :size="14" /> {{ ! empty($item['submission_status']) ? 'إعادة رفع الواجب' : 'رفع الواجب' }}
                                </button>
                            @endif
                        </div>
                    </article>

                    @if ($student)
                        <div class="modal-overlay" id="homeworkSubmit{{ $item['id'] }}">
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
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endsection

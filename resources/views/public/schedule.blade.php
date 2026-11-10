@extends('layouts.public')

@section('title', 'الجدول الدراسي')

@section('content')
    <div class="fade-in">
        <h1 class="mb-1 text-2xl font-extrabold">الجدول الدراسي</h1>
        <p class="mb-4 text-sm text-primary/70">⏰ الدوام: {{ $institute['work_hours'] }}</p>

        <div class="card mb-4 flex items-center gap-3 border-r-4 border-r-amber-400 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                <x-icon name="clock" :size="20" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-extrabold text-primary">أوقات الدوام</div>
                <div class="text-xs text-primary/60">{{ $institute['work_hours'] }} — يوم الجمعة عطلة رسمية.</div>
            </div>
        </div>

        <div class="space-y-3">
            @foreach ($days as $day)
                @php $items = $schedule->get($day, collect()); @endphp
                @if ($day === 'الجمعة' || $items->isEmpty())
                    <div class="card flex items-center gap-3 bg-slate-50/60 p-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                            <x-icon name="{{ $day === 'الجمعة' ? 'calendar-x' : 'calendar' }}" :size="20" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-extrabold text-primary">{{ $day }}</div>
                            <div class="text-xs text-primary/50">{{ $day === 'الجمعة' ? 'عطلة رسمية – لا توجد دروس' : 'لا توجد مواعيد مسجلة لهذا اليوم' }}</div>
                        </div>
                    </div>
                @else
                    <div class="card overflow-hidden">
                        <div class="flex items-center gap-2 border-b border-primary/5 bg-primary/4 px-4 py-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary text-xs font-extrabold text-white">{{ $loop->iteration }}</span>
                            <span class="text-sm font-extrabold text-primary">{{ $day }}</span>
                            <span class="chip bg-white text-primary/60 mr-auto">{{ $items->count() }} موعد</span>
                        </div>
                        <div class="divide-y divide-primary/5">
                            @foreach ($items as $item)
                                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                                    <div class="w-24 shrink-0">
                                        <div class="chip bg-primary/5 text-primary">{{ $item['time'] }}</div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-extrabold text-primary">{{ $item['course'] }}</div>
                                        <div class="text-[11px] text-primary/60">{{ $item['stage'] }} • {{ $item['teacher'] }}</div>
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] font-bold text-primary/50">
                                        <x-icon name="map-pin" :size="13" /> {{ $item['hall'] }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endsection

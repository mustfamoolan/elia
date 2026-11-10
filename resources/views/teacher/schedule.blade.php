@extends('layouts.teacher')

@section('title', 'الجدول')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">جدولي الأسبوعي</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $courses->count() }} دورة</span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($days as $day => $dayName)
                @php $slots = $byDay[$day] ?? collect(); @endphp
                <section class="card p-4">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary/8 text-primary">
                            <x-icon name="calendar" :size="15" />
                        </span>
                        <h2 class="text-sm font-extrabold">{{ $dayName }}</h2>
                        <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $slots->count() }}</span>
                    </div>

                    @forelse ($slots as $slot)
                        <div class="mb-2 rounded-xl border border-primary/5 p-2.5 last:mb-0">
                            <div class="text-xs font-extrabold text-primary">{{ $slot->course?->name ?? $slot->course_name }}</div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-1 text-[10px] text-primary/50">
                                <span class="flex items-center gap-1"><x-icon name="clock" :size="11" /> {{ substr($slot->start_time, 0, 5) }} – {{ substr($slot->end_time, 0, 5) }}</span>
                                @if ($slot->hall)
                                    <span class="flex items-center gap-1"><x-icon name="map-pin" :size="11" /> {{ $slot->hall }}</span>
                                @endif
                            </div>
                            @if ($slot->course?->stage)
                                <span class="chip mt-1.5 bg-blue-50 text-blue-700">{{ $slot->course->stage->name }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="py-4 text-center text-[11px] font-bold text-primary/40">لا توجد حصص</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
@endsection

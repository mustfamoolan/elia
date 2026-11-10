@extends('layouts.public')

@section('title', 'الكادر التدريسي')

@section('content')
    @php
        $palette = [
            'blue' => ['bg-blue-50', 'text-blue-600'],
            'violet' => ['bg-violet-50', 'text-violet-600'],
            'emerald' => ['bg-emerald-50', 'text-emerald-600'],
            'amber' => ['bg-amber-50', 'text-amber-600'],
        ];
    @endphp

    <div class="fade-in">
        @if (! $institute['staff_enabled'])
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                <p class="text-sm font-bold text-primary/60">قسم الكادر التدريسي غير مفعّل حالياً</p>
            </div>
        @else
            <h1 class="mb-1 text-2xl font-extrabold">الكادر التدريسي</h1>
            <p class="mb-4 text-sm text-primary/70">نخبة من المدرسين ذوي الخبرة في جميع المواد والمراحل</p>

            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($staff as $member)
                    @php [$bg, $color] = $palette[$member['color']] ?? ['bg-primary/5', 'text-primary']; @endphp
                    <article class="card flex items-start gap-4 p-5">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $bg }} {{ $color }} text-lg font-extrabold">
                            {{ mb_substr($member['name'], 3, 1) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-base font-extrabold text-primary">{{ $member['name'] }}</div>
                            <div class="text-xs font-bold text-primary/60">{{ $member['subject'] }} • {{ $member['stage'] }}</div>
                            <p class="mt-1 text-xs leading-relaxed text-primary/70">{{ $member['bio'] }}</p>
                            <a href="tel:+964{{ ltrim($member['phone'], '0') }}" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-secondary">
                                <x-icon name="phone" :size="13" /> {{ $member['phone'] }}
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

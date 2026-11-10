@extends('layouts.public')

@section('title', 'العروض والأسعار')

@section('content')
    @php
        $palette = [
            'rose' => ['bg-rose-50', 'text-rose-600', 'from-rose-500'],
            'blue' => ['bg-blue-50', 'text-blue-600', 'from-blue-600'],
            'emerald' => ['bg-emerald-50', 'text-emerald-600', 'from-emerald-500'],
        ];
    @endphp

    <div class="fade-in">
        <h1 class="mb-1 text-2xl font-extrabold">العروض والأسعار</h1>
        <p class="mb-4 text-sm text-primary/70">العروض النشطة حالياً في {{ $institute['short_name'] }}</p>

        @if ($offers->isEmpty())
            <div class="card flex flex-col items-center gap-2 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-2xl">📭</div>
                <p class="text-sm font-bold text-primary/60">لا توجد عروض نشطة حالياً – تابعنا قريباً</p>
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($offers as $offer)
                    @php [$bg, $color] = $palette[$offer['color']] ?? ['bg-primary/5', 'text-primary']; @endphp
                    <article class="card relative overflow-hidden p-5">
                        <div class="absolute left-0 top-0 h-24 w-24 -translate-x-6 -translate-y-6 rounded-full {{ $bg }}"></div>
                        <div class="relative">
                            <div class="mb-2 flex items-center gap-2">
                                <span class="chip bg-rose-50 text-rose-600">
                                    <x-icon name="percent" :size="13" /> خصم {{ $offer['discount'] }}
                                </span>
                                <span class="chip bg-primary/5 text-primary/60">{{ $offer['stage'] }}</span>
                            </div>
                            <h2 class="text-base font-extrabold text-primary">{{ $offer['title'] }}</h2>
                            <p class="mt-1 text-xs leading-relaxed text-primary/70">{{ $offer['description'] }}</p>

                            <div class="mt-3 flex items-end gap-2">
                                @if ($offer['price'] > 0)
                                    <div class="text-xl font-extrabold text-primary">{{ number_format($offer['price']) }} <span class="text-[10px] font-bold text-primary/50">د.ع</span></div>
                                    @if ($offer['old_price'] > 0)
                                        <div class="text-xs font-bold text-primary/40 line-through">{{ number_format($offer['old_price']) }} د.ع</div>
                                    @endif
                                @else
                                    <div class="text-sm font-extrabold text-primary">خصم خاص عند التسجيل</div>
                                @endif
                            </div>

                            <div class="mt-3 flex items-center gap-2 border-t border-primary/5 pt-3">
                                <div class="flex items-center gap-1 text-[11px] font-bold text-primary/50">
                                    <x-icon name="clock" :size="13" />
                                    ينتهي {{ \Illuminate\Support\Carbon::parse($offer['expires'])->format('Y/m/d') }}
                                </div>
                                <a href="https://wa.me/{{ $institute['whatsapp_intl'] }}?text={{ urlencode('السلام عليكم، أرغب بالاستفادة من عرض: '.$offer['title']) }}"
                                   target="_blank" rel="noopener" class="btn btn-dark btn-sm mr-auto">
                                    <x-icon name="whatsapp" :size="14" /> استفد من العرض
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

@extends('layouts.public')

@section('title', 'موقع المعهد')

@section('content')
    <div class="fade-in mx-auto max-w-2xl">
        <h1 class="mb-1 text-2xl font-extrabold">موقع المعهد</h1>
        <p class="mb-4 text-sm text-primary/70">يمكنك الوصول إلينا بسهولة عبر خرائط جوجل</p>

        <div class="card overflow-hidden">
            <div class="map-canvas">
                <div class="map-pin">
                    <x-icon name="map-pin" :size="28" />
                </div>
                <div class="absolute bottom-3 right-3 rounded-xl bg-white/90 px-3 py-2 text-[11px] font-extrabold text-primary shadow-soft">
                    {{ $institute['address'] }}
                </div>
            </div>

            <div class="p-5 text-center">
                <img src="{{ asset('assets/logo.png') }}" alt="شعار {{ $institute['short_name'] }}" class="brand-logo mx-auto mb-2 h-16 w-16 rounded-2xl bg-primary/5 p-1 object-contain" draggable="false">
                <div class="text-lg font-extrabold text-primary">{{ $institute['name'] }}</div>
                <div class="mt-1 text-sm text-primary/60">{{ $institute['address'] }}</div>
                <div class="mt-1 flex items-center justify-center gap-1 text-xs font-bold text-primary/50">
                    <x-icon name="clock" :size="13" /> {{ $institute['work_hours'] }}
                </div>

                <a href="{{ $institute['maps_url'] }}" target="_blank" rel="noopener" class="btn btn-dark mt-4 w-full sm:w-auto">
                    <x-icon name="external-link" :size="16" /> فتح موقع المعهد في خرائط جوجل
                </a>

                <p class="mt-3 text-[11px] leading-relaxed text-primary/50">
                    سيتم فتح Google Maps لتحديد موقع المعهد في {{ $institute['address'] }}.
                </p>
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <a href="tel:+{{ $institute['phone_intl'] }}" class="card flex items-center gap-3 p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <x-icon name="phone" :size="18" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-extrabold text-primary">اتصل بنا</div>
                    <div class="text-[11px] text-primary/60">{{ $institute['phone'] }}</div>
                </div>
            </a>
            <a href="https://wa.me/{{ $institute['whatsapp_intl'] }}" target="_blank" rel="noopener" class="card flex items-center gap-3 p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <x-icon name="whatsapp" :size="18" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-extrabold text-primary">واتساب</div>
                    <div class="text-[11px] text-primary/60">{{ $institute['whatsapp'] }}</div>
                </div>
            </a>
        </div>
    </div>
@endsection

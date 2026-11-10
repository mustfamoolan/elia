@extends('layouts.public')

@section('title', 'تواصل معنا')

@section('content')
    <div class="fade-in mx-auto max-w-lg">
        <h1 class="mb-1 text-2xl font-extrabold">تواصل معنا</h1>
        <p class="mb-4 text-sm text-primary/70">{{ $institute['name'] }}<span> – </span>{{ $institute['address'] }}</p>

        <div class="space-y-3">
            <a href="tel:+{{ $institute['phone_intl'] }}" class="card flex items-center gap-4 p-4 transition hover:shadow-soft">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                    <x-icon name="phone" :size="22" />
                </div>
                <div>
                    <div class="font-extrabold text-primary">اتصال مباشر</div>
                    <div class="text-sm text-primary/60">{{ $institute['phone'] }}</div>
                </div>
                <x-icon name="chevron-left" :size="18" class="mr-auto text-primary/30" />
            </a>

            <a href="https://wa.me/{{ $institute['whatsapp_intl'] }}?text={{ urlencode('السلام عليكم، أرغب بالاستفسار عن معهد آيلا التعليمي') }}"
               target="_blank" rel="noopener" class="card flex items-center gap-4 p-4 transition hover:shadow-soft">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <x-icon name="whatsapp" :size="22" />
                </div>
                <div>
                    <div class="font-extrabold text-primary">واتساب</div>
                    <div class="text-sm text-primary/60">{{ $institute['whatsapp'] }}</div>
                </div>
                <x-icon name="chevron-left" :size="18" class="mr-auto text-primary/30" />
            </a>

            <a href="https://instagram.com/{{ $institute['instagram'] }}" target="_blank" rel="noopener" class="card flex items-center gap-4 p-4 transition hover:shadow-soft">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-pink-50 text-pink-600">
                    <x-icon name="instagram" :size="22" />
                </div>
                <div>
                    <div class="font-extrabold text-primary">إنستغرام</div>
                    <div class="text-sm text-primary/60">@{{ $institute['instagram'] }}</div>
                </div>
                <x-icon name="chevron-left" :size="18" class="mr-auto text-primary/30" />
            </a>

            <a href="https://www.tiktok.com/@{{ $institute['tiktok'] }}" target="_blank" rel="noopener" class="card flex items-center gap-4 p-4 transition hover:shadow-soft">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                    <x-icon name="tiktok" :size="22" />
                </div>
                <div>
                    <div class="font-extrabold text-primary">تيك توك</div>
                    <div class="text-sm text-primary/60">@{{ $institute['tiktok'] }}</div>
                </div>
                <x-icon name="chevron-left" :size="18" class="mr-auto text-primary/30" />
            </a>

            <a href="{{ $institute['facebook_url'] }}" target="_blank" rel="noopener" class="card flex items-center gap-4 p-4 transition hover:shadow-soft">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-700">
                    <x-icon name="facebook" :size="22" />
                </div>
                <div>
                    <div class="font-extrabold text-primary">فيسبوك</div>
                    <div class="text-sm text-primary/60">{{ $institute['facebook'] }}</div>
                </div>
                <x-icon name="chevron-left" :size="18" class="mr-auto text-primary/30" />
            </a>

            <div class="card flex items-center gap-4 p-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                    <x-icon name="clock" :size="22" />
                </div>
                <div>
                    <div class="font-extrabold text-primary">أوقات الدوام</div>
                    <div class="text-sm text-primary/60">{{ $institute['work_hours'] }}</div>
                </div>
            </div>

            <a href="{{ url('/location') }}" class="card flex items-center gap-4 p-4 transition hover:shadow-soft">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                    <x-icon name="map-pin" :size="22" />
                </div>
                <div>
                    <div class="font-extrabold text-primary">الموقع على الخريطة</div>
                    <div class="text-sm text-primary/60">{{ $institute['address'] }}</div>
                </div>
                <x-icon name="chevron-left" :size="18" class="mr-auto text-primary/30" />
            </a>
        </div>
    </div>
@endsection

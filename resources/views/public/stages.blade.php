@extends('layouts.public')

@section('title', 'المراحل الدراسية')

@section('content')
    @php
        $palette = [
            'blue' => ['bg-blue-50', 'text-blue-600'],
            'indigo' => ['bg-indigo-50', 'text-indigo-600'],
            'emerald' => ['bg-emerald-50', 'text-emerald-600'],
            'amber' => ['bg-amber-50', 'text-amber-600'],
            'rose' => ['bg-rose-50', 'text-rose-600'],
        ];
    @endphp

    <div class="fade-in">
        <h1 class="mb-1 text-2xl font-extrabold">المراحل الدراسية</h1>
        <p class="mb-4 text-sm text-primary/70">المراحل المتوفرة في {{ $institute['short_name'] }}</p>

        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ($stages as $stage)
                @php [$bg, $color] = $palette[$stage['color']] ?? ['bg-primary/5', 'text-primary']; @endphp
                <div class="card flex items-center gap-4 p-5">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl {{ $bg }} {{ $color }}">
                        <x-icon :name="$stage['icon']" :size="24" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-lg font-extrabold text-primary">{{ $stage['name'] }}</div>
                        <div class="text-xs text-primary/60">{{ $stage['description'] }}</div>
                        <div class="mt-1 text-[11px] font-bold text-primary/50">{{ $stage['courses'] }} دورات متاحة</div>
                    </div>
                    <a class="btn btn-outline btn-sm shrink-0" href="{{ url('/courses?stage='.$stage['id']) }}">الدورات</a>
                </div>
            @endforeach
        </div>

        <div class="card mt-4 flex flex-col items-center gap-3 p-5 text-center sm:flex-row sm:text-right">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary/8 text-primary-light">
                <x-icon name="info" :size="22" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-extrabold text-primary">لم تجد المرحلة التي تبحث عنها؟</div>
                <div class="text-xs text-primary/60">تواصل مع الإدارة لمعرفة الدورات المتاحة والمواعيد المناسبة.</div>
            </div>
            <a href="{{ url('/contact') }}" class="btn btn-dark btn-sm">تواصل معنا</a>
        </div>
    </div>
@endsection

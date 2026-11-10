@php
    $institute = $institute ?? \App\Support\Eyal::institute();
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إيصال قبض {{ $payment->receipt_no }} — {{ $institute['short_name'] }}</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .receipt { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
        }
    </style>
</head>
<body class="bg-cloud text-ink">
    <main class="mx-auto max-w-3xl px-4 py-8">
        <div class="no-print mb-4 flex items-center gap-2">
            <a href="{{ url('/admin/payments') }}" class="btn btn-outline btn-sm">
                <x-icon name="arrow-right" :size="14" /> رجوع
            </a>
            <button type="button" class="btn btn-dark btn-sm mr-auto" onclick="window.print()">
                <x-icon name="printer" :size="14" /> طباعة الإيصال
            </button>
        </div>

        <div class="receipt card overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-primary/10 bg-primary px-6 py-5 text-white">
                <img src="{{ asset('assets/logo.png') }}" alt="{{ $institute['name'] }}" class="h-12 w-12 rounded-xl bg-white object-contain p-1">
                <div class="min-w-0 flex-1">
                    <div class="text-lg font-extrabold">{{ $institute['name'] }}</div>
                    <div class="text-[11px] text-white/70">{{ $institute['address'] }} • {{ $institute['phone'] }}</div>
                </div>
                <div class="text-left">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-white/60">إيصال قبض</div>
                    <div class="text-sm font-extrabold">{{ $payment->receipt_no }}</div>
                </div>
            </div>

            <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                <div>
                    <div class="text-[10px] font-bold text-primary/50">اسم الطالب</div>
                    <div class="text-sm font-extrabold text-primary">{{ $student?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-primary/50">الرقم الدراسي</div>
                    <div class="text-sm font-extrabold text-primary">{{ $student?->student_no ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-primary/50">المرحلة</div>
                    <div class="text-sm font-extrabold text-primary">{{ $student?->stage?->name ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-primary/50">تاريخ الدفع</div>
                    <div class="text-sm font-extrabold text-primary">{{ $payment->paid_at?->format('Y-m-d') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-primary/50">طريقة الدفع</div>
                    <div class="text-sm font-extrabold text-primary">{{ $payment->method_label }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-primary/50">المبلغ المستلم</div>
                    <div class="text-sm font-extrabold text-emerald-600">{{ number_format((int) $payment->amount) }} د.ع</div>
                </div>
            </div>

            @if ($payment->note)
                <div class="mx-6 mb-4 rounded-xl bg-primary/4 px-4 py-3 text-xs text-primary/70">
                    <span class="font-extrabold text-primary">ملاحظات:</span> {{ $payment->note }}
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-6 border-t border-dashed border-primary/15 px-6 py-6">
                <div class="min-w-[10rem] flex-1">
                    <div class="mb-6 text-[10px] font-bold text-primary/50">توقيع المستلم</div>
                    <div class="border-t border-primary/20"></div>
                </div>
                <div class="min-w-[10rem] flex-1">
                    <div class="mb-6 text-[10px] font-bold text-primary/50">ختم المعهد</div>
                    <div class="border-t border-primary/20"></div>
                </div>
            </div>

            <div class="border-t border-primary/10 bg-primary/3 px-6 py-3 text-center text-[10px] text-primary/50">
                هذا الإيصال صادر إلكترونياً من نظام {{ $institute['short_name'] }} — {{ now()->format('Y-m-d H:i') }}
            </div>
        </div>
    </main>
</body>
</html>

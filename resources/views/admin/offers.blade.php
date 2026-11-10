@extends('layouts.admin')

@section('title', 'العروض')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">العروض والأسعار</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $offers->count() }} عرض</span>
            <button class="btn btn-dark btn-sm" data-modal-open="offerModal" data-add="offerModal" data-add-title="إضافة عرض">
                <x-icon name="plus" :size="14" /> إضافة عرض
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($offers as $offer)
                <article class="card relative overflow-hidden p-4">
                    <div class="absolute left-0 top-0 h-20 w-20 -translate-x-5 -translate-y-5 rounded-full bg-primary/5"></div>
                    <div class="relative">
                        <div class="mb-2 flex items-center gap-1.5">
                            <span class="chip bg-rose-50 text-rose-600">
                                <x-icon name="percent" :size="12" /> {{ $offer['discount'] }}
                            </span>
                            <span class="chip bg-primary/5 text-primary/60">{{ $offer['stage'] }}</span>
                        </div>
                        <h2 class="text-sm font-extrabold text-primary">{{ $offer['title'] }}</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-primary/60">{{ $offer['description'] }}</p>
                        <div class="mt-3 flex items-end gap-2">
                            @if ($offer['price'] > 0)
                                <div class="text-lg font-extrabold text-primary">{{ number_format($offer['price']) }} <span class="text-[10px] text-primary/50">د.ع</span></div>
                                @if ($offer['old_price'] > 0)
                                    <div class="text-[11px] font-bold text-primary/40 line-through">{{ number_format($offer['old_price']) }}</div>
                                @endif
                            @else
                                <div class="text-xs font-extrabold text-primary">خصم خاص</div>
                            @endif
                        </div>
                        <div class="mt-3 flex items-center gap-1 border-t border-primary/5 pt-3">
                            <span class="text-[10px] font-bold text-primary/40">ينتهي {{ $offer['expires'] }}</span>
                            <div class="mr-auto flex items-center gap-1">
                                <button class="toggle" aria-checked="{{ $offer['active'] ? 'true' : 'false' }}"
                                        data-toggle-url="{{ route('admin.offers.toggle', $offer['id']) }}"
                                        data-toggle-toast="تم تحديث حالة العرض" aria-label="تفعيل العرض"></button>
                                <button class="btn btn-outline btn-sm" data-modal="offerModal" data-edit="{{ json_encode([
                                    'action' => route('admin.offers.update', $offer['id']),
                                    'modal_title' => 'تعديل العرض',
                                    'title' => $offer['title'],
                                    'stage' => $offer['stage_id'],
                                    'discount' => $offer['discount'],
                                    'old_price' => $offer['old_price'],
                                    'price' => $offer['price'],
                                    'expires' => $offer['expires'],
                                    'description' => $offer['description'],
                                ], JSON_UNESCAPED_UNICODE) }}">
                                    <x-icon name="pencil" :size="12" />
                                </button>
                                <form method="POST" action="{{ route('admin.offers.destroy', $offer['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف هذا العرض؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" type="submit">
                                        <x-icon name="trash-2" :size="12" />
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="offerModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة عرض</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.offers.store') }}" data-store-action="{{ route('admin.offers.store') }}">
                @csrf
                <div>
                    <label class="label">عنوان العرض *</label>
                    <input name="title" class="input" placeholder="مثال: خصم 20% على التسجيل" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">المرحلة</label>
                        <select name="stage" class="input">
                            <option value="">جميع المراحل</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage['id'] }}">{{ $stage['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">نسبة الخصم</label>
                        <input name="discount" class="input" placeholder="20%">
                    </div>
                    <div>
                        <label class="label">السعر قبل الخصم</label>
                        <input name="old_price" type="number" min="0" step="1000" class="input" placeholder="150000">
                    </div>
                    <div>
                        <label class="label">السعر بعد الخصم</label>
                        <input name="price" type="number" min="0" step="1000" class="input" placeholder="120000">
                    </div>
                </div>
                <div>
                    <label class="label">تاريخ الانتهاء</label>
                    <input name="expires" type="date" class="input" value="{{ now()->addDays(30)->toDateString() }}">
                </div>
                <div>
                    <label class="label">تفاصيل العرض</label>
                    <textarea name="description" class="input min-h-[80px]" placeholder="اشرح تفاصيل العرض..."></textarea>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> حفظ
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

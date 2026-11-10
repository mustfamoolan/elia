@extends('layouts.admin')

@section('title', 'الإعلانات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الإعلانات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $announcements->count() }} إعلان</span>
            <button class="btn btn-dark btn-sm" data-modal-open="announcementModal" data-add="announcementModal" data-add-title="إضافة إعلان">
                <x-icon name="plus" :size="14" /> إضافة إعلان
            </button>
        </div>

        <div class="space-y-3">
            @foreach ($announcements as $item)
                <article class="card p-4">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <span class="chip bg-primary/5 text-primary">
                            <x-icon name="megaphone" :size="12" /> {{ $item['category'] }}
                        </span>
                        @if ($item['pinned'])
                            <span class="chip bg-rose-50 text-rose-600">
                                <x-icon name="star" :size="11" /> مثبّت
                            </span>
                        @endif
                        <span class="mr-auto text-[10px] font-bold text-primary/40">{{ $item['date'] }}</span>
                    </div>
                    <h2 class="text-sm font-extrabold text-primary">{{ $item['title'] }}</h2>
                    <p class="mt-1 text-xs leading-relaxed text-primary/70">{{ $item['body'] }}</p>
                    <div class="mt-3 flex items-center gap-1 border-t border-primary/5 pt-3">
                        <button class="toggle" aria-checked="{{ $item['published'] ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.announcements.toggle', $item['id']) }}"
                                data-toggle-toast="تم تحديث حالة الإعلان" aria-label="تفعيل الإعلان"></button>
                        <span class="text-[10px] font-bold text-primary/50">{{ $item['published'] ? 'منشور' : 'مسودة' }}</span>
                        <div class="mr-auto flex items-center gap-1">
                            <button class="btn btn-outline btn-sm" data-modal="announcementModal" data-edit="{{ json_encode([
                                'action' => route('admin.announcements.update', $item['id']),
                                'modal_title' => 'تعديل الإعلان',
                                'title' => $item['title'],
                                'category' => $item['category'],
                                'body' => $item['body'],
                                'pinned' => $item['pinned'],
                            ], JSON_UNESCAPED_UNICODE) }}">
                                <x-icon name="pencil" :size="12" /> تعديل
                            </button>
                            <form method="POST" action="{{ route('admin.announcements.destroy', $item['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف هذا الإعلان؟')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm" type="submit">
                                    <x-icon name="trash-2" :size="12" />
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="announcementModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إضافة إعلان</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.announcements.store') }}" data-store-action="{{ route('admin.announcements.store') }}">
                @csrf
                <div>
                    <label class="label">عنوان الإعلان *</label>
                    <input name="title" class="input" placeholder="مثال: فتح باب التسجيل" required>
                </div>
                <div>
                    <label class="label">التصنيف</label>
                    <select name="category" class="input">
                        <option value="تسجيل">تسجيل</option>
                        <option value="امتحانات">امتحانات</option>
                        <option value="عطلة">عطلة</option>
                        <option value="عام">عام</option>
                    </select>
                </div>
                <div>
                    <label class="label">نص الإعلان *</label>
                    <textarea name="body" class="input min-h-[100px]" placeholder="اكتب تفاصيل الإعلان..." required></textarea>
                </div>
                <label class="flex items-center gap-2 rounded-xl bg-primary/4 p-3">
                    <input type="checkbox" name="pinned" value="1" class="h-4 w-4 rounded border-primary/20">
                    <span class="text-xs font-extrabold text-primary">تثبيت الإعلان في الأعلى</span>
                </label>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="save" :size="16" /> نشر
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

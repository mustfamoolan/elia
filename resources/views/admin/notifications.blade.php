@extends('layouts.admin')

@section('title', 'الإشعارات')

@section('content')
    <div class="fade-in">
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-extrabold">الإشعارات</h1>
            <span class="chip bg-primary/5 text-primary/60 mr-auto">{{ $notifications->count() }} إشعار</span>
            <button class="btn btn-dark btn-sm" data-modal-open="notificationModal" data-add="notificationModal" data-add-title="إرسال إشعار">
                <x-icon name="plus" :size="14" /> إرسال إشعار
            </button>
        </div>

        <div class="space-y-2">
            @foreach ($notifications as $item)
                @php
                    $editNotifPayload = [
                        'action' => route('admin.notifications.update', $item['id']),
                        'modal_title' => 'تعديل الإشعار',
                        'title' => $item['title'],
                        'audience' => $item['audience_raw'] ?? 'all',
                        'body' => $item['body'],
                    ];
                @endphp
                <article class="card flex items-start gap-3 p-4">
                    <div class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/8 text-primary">
                        <x-icon name="bell" :size="20" />
                        @if (! $item['read'])
                            <span class="absolute -left-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-white"></span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-extrabold text-primary">{{ $item['title'] }}</h2>
                            <span class="chip bg-primary/5 text-primary/60">{{ $item['audience'] }}</span>
                            @if (! $item['read'])
                                <span class="chip bg-rose-50 text-rose-600">غير مقروء</span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs leading-relaxed text-primary/70">{{ $item['body'] }}</p>
                        <div class="mt-1.5 text-[10px] font-bold text-primary/40">{{ $item['date'] }}</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                        <button type="button" class="btn btn-outline btn-sm" data-modal="notificationModal" data-edit="{{ json_encode($editNotifPayload, JSON_UNESCAPED_UNICODE) }}">
                            <x-icon name="pencil" :size="12" /> تعديل
                        </button>
                        <form method="POST" action="{{ route('admin.notifications.destroy', $item['id']) }}" class="inline" onsubmit="return confirm('هل تريد حذف هذا الإشعار؟')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger btn-sm" type="submit">
                                <x-icon name="trash-2" :size="12" />
                            </button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>
    </div>

    <div class="modal-overlay" id="notificationModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-extrabold" data-modal-title>إرسال إشعار</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="20" class="text-primary" />
                </button>
            </div>
            <form class="space-y-3" method="POST" action="{{ route('admin.notifications.store') }}" data-store-action="{{ route('admin.notifications.store') }}">
                @csrf
                <div>
                    <label class="label">عنوان الإشعار *</label>
                    <input name="title" class="input" placeholder="مثال: تذكير بموعد الامتحان" required>
                </div>
                <div>
                    <label class="label">الجمهور المستهدف</label>
                    <select name="audience" class="input">
                        <option value="all">جميع المستخدمين</option>
                        <option value="students">جميع الطلاب</option>
                        <option value="parents">جميع أولياء الأمور</option>
                        @foreach ($students as $student)
                            <option value="{{ $student['student_no'] }}">{{ $student['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label">نص الإشعار *</label>
                    <textarea name="body" class="input min-h-[100px]" placeholder="اكتب نص الإشعار..." required></textarea>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="send" :size="16" /> إرسال
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>
@endsection

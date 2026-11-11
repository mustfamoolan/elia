@extends($layout)

@section('title', 'المحادثات والدعم')

@section('content')
    @php
        $activeConv = $activeConv ?? ($activeConversationId ? $conversations->firstWhere('id', $activeConversationId) : null);
        $mobileMode = (!empty($showListOnMobile) || !$activeConversationId) ? 'wa-mobile-mode-list' : 'wa-mobile-mode-chat';
        $totalUnread = $conversations->sum('unread_count');
    @endphp

    @if (!$chatEnabled && !$user->isAdmin())
        <div class="mx-2 my-2 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-amber-800 lg:mx-0 lg:mb-3">
            <div class="flex items-center gap-2 text-xs font-extrabold">
                <x-icon name="info" :size="16" class="text-amber-600" /> نظام المحادثات متوقف مؤقتاً من قبل الإدارة (للقراءة فقط)
            </div>
        </div>
    @endif

    {{-- حاوية تطبيق المحادثات بأسلوب واتساب --}}
    <div id="waShell" class="wa-shell {{ $mobileMode }}">
        {{-- القائمة الجانبية للمحادثات (شاشة قائمة الدردشات في الموبايل / العمود الأيمن في الحاسوب) --}}
        <aside class="wa-sidebar">
            {{-- شريط عنوان واتساب العلوي للقائمة --}}
            <div class="wa-sidebar-header">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/15 text-sm font-black text-white">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h1 class="truncate text-sm font-extrabold text-white">المحادثات والدعم</h1>
                            @if ($totalUnread > 0)
                                <span class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-[#25d366] px-1.5 text-[10px] font-black text-white">
                                    {{ $totalUnread }}
                                </span>
                            @endif
                        </div>
                        <p class="truncate text-[10px] text-white/80">{{ $conversations->count() }} محادثة نشطة</p>
                    </div>
                </div>

                <button type="button"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1.5 text-xs font-extrabold text-white transition hover:bg-white/25"
                        data-modal-open="newChatModal">
                    <x-icon name="plus" :size="14" />
                    <span>محادثة جديدة</span>
                </button>
            </div>

            {{-- شريط البحث السريع في المحادثات --}}
            <div class="wa-search-bar">
                <div class="flex items-center gap-2 rounded-xl bg-white px-3 py-1.5 shadow-sm">
                    <x-icon name="search" :size="15" class="text-slate-400 shrink-0" />
                    <input type="text" id="conversationSearchInput"
                           class="w-full border-0 bg-transparent text-xs font-semibold text-slate-800 outline-none"
                           placeholder="ابحث أو ابدأ دردشة جديدة...">
                </div>
            </div>

            {{-- قائمة الدردشات --}}
            <div class="wa-conv-list" id="conversationsList">
                @forelse ($conversations as $conv)
                    @php
                        $isActive = (int) $activeConversationId === (int) $conv['id'];
                        $roleBadgeTone = match ($conv['other_user_role']) {
                            'مدير' => 'bg-rose-50 text-rose-700',
                            'مدرس' => 'bg-indigo-50 text-indigo-700',
                            default => 'bg-emerald-50 text-emerald-700',
                        };
                        $avatarTone = match ($conv['other_user_role']) {
                            'مدير' => 'bg-rose-600 text-white',
                            'مدرس' => 'bg-indigo-600 text-white',
                            default => 'bg-[#00a884] text-white',
                        };
                    @endphp
                    <a href="{{ route('chat.index', ['conversation_id' => $conv['id']]) }}"
                       data-conv-item
                       data-conv-id="{{ $conv['id'] }}"
                       data-conv-name="{{ $conv['other_user_name'].' '.($conv['student_name'] ?? '').' '.$conv['other_user_role'] }}"
                       class="wa-conv-item {{ $isActive ? 'is-active' : '' }}">
                        <div class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $avatarTone }} text-base font-black shadow-sm">
                            {{ mb_substr($conv['other_user_name'], 0, 1) }}
                            <span class="absolute bottom-0 left-0 h-3 w-3 rounded-full border-2 border-white bg-[#25d366]"></span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span class="truncate text-[13px] font-extrabold text-slate-900">{{ $conv['other_user_name'] }}</span>
                                    <span class="chip shrink-0 px-1.5 py-0.5 text-[9px] {{ $roleBadgeTone }}">{{ $conv['other_user_role'] }}</span>
                                </div>
                                <span class="shrink-0 text-[10px] font-bold {{ $conv['unread_count'] > 0 ? 'text-[#00a884]' : 'text-slate-400' }}">
                                    {{ $conv['last_message_time'] }}
                                </span>
                            </div>

                            @if (!empty($conv['student_name']))
                                <div class="mt-0.5 flex items-center gap-1 truncate text-[10px] font-extrabold text-[#008069]">
                                    <x-icon name="graduation-cap" :size="11" />
                                    <span>الطالب: {{ $conv['student_name'] }}</span>
                                </div>
                            @endif

                            <div class="mt-1 flex items-center justify-between gap-2">
                                <p class="truncate text-xs text-slate-500">
                                    <span class="wa-ticks ml-0.5">✓✓</span>
                                    {{ $conv['last_message'] ?: 'اضغط لبدء المراسلة...' }}
                                </p>
                                @if ($conv['unread_count'] > 0)
                                    <span class="inline-flex h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-[#25d366] px-1.5 text-[10px] font-black text-white">
                                        {{ $conv['unread_count'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                        <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-[#00a884]/10 text-[#008069]">
                            <x-icon name="message-square" :size="30" />
                        </div>
                        <p class="text-sm font-extrabold text-slate-800">لا توجد محادثات بعد</p>
                        <p class="mt-1 max-w-xs text-xs text-slate-500">ابدأ محادثة فورية مع إدارة المعهد أو الكادر التدريسي أو أولياء الأمور.</p>
                        <button type="button"
                                class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-[#00a884] px-4 py-2 text-xs font-extrabold text-white shadow-md hover:bg-[#008f6f]"
                                data-modal-open="newChatModal">
                            <x-icon name="plus" :size="15" /> ابدأ محادثة جديدة
                        </button>
                    </div>
                @endforelse
            </div>
        </aside>

        {{-- نافذة المحادثة النشطة (شاشة الدردشة الكاملة على الموبايل / الجزء الأيسر على الحاسوب) --}}
        <section class="wa-main">
            @if ($activeConversationId && $activeConv)
                {{-- 1. الشريط العلوي الثابت للمحادثة (WhatsApp Top Bar) --}}
                <div class="wa-chat-header">
                    <div class="flex min-w-0 items-center gap-2">
                        {{-- زر الرجوع لقائمة المحادثات على الموبايل --}}
                        <button type="button" id="waBackToListBtn"
                                class="lg:hidden -mr-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-white hover:bg-white/15"
                                aria-label="رجوع للمحادثات">
                            <x-icon name="arrow-right" :size="20" />
                        </button>

                        <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white/20 text-sm font-black text-white">
                            {{ mb_substr($activeConv['other_user_name'] ?? 'م', 0, 1) }}
                            <span class="absolute bottom-0 left-0 h-2.5 w-2.5 rounded-full border border-[#008069] bg-[#25d366]"></span>
                        </div>

                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h2 class="truncate text-sm font-extrabold text-white">
                                    {{ $activeConv['other_user_name'] ?? 'محادثة' }}
                                </h2>
                                <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-bold text-white">
                                    {{ $activeConv['other_user_role'] ?? '' }}
                                </span>
                            </div>
                            <div class="mt-0.5 truncate text-[11px] text-white/85">
                                @if (!empty($activeConv['student_name']))
                                    <span>حول الطالب: <strong>{{ $activeConv['student_name'] }}</strong></span>
                                @else
                                    <span>متصل • قناة تواصل رسمية</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        @if ($myChildren && $myChildren->isNotEmpty())
                            <button type="button"
                                    class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1.5 text-[11px] font-extrabold text-white transition hover:bg-white/25"
                                    data-modal-open="shareCardModal"
                                    title="مشاركة بطاقة درجات أو حضور أو واجب">
                                <x-icon name="clipboard-list" :size="14" />
                                <span class="hidden sm:inline">بطاقة طالب</span>
                            </button>
                        @endif
                        <button type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-full text-white hover:bg-white/15"
                                data-modal-open="newChatModal"
                                title="محادثة جديدة">
                            <x-icon name="plus" :size="18" />
                        </button>
                    </div>
                </div>

                {{-- 2. منطقة الرسائل القابلة للتمرير بنمط واتساب --}}
                <div id="messagesArea" class="wa-messages-viewport">
                    <div class="wa-encryption-pill">
                        🔒 الرسائل والملفات في هذه المحادثة مخصصة للتواصل التربوي المباشر مع المعهد.
                    </div>

                    <div id="messagesLoading" class="py-10 text-center text-xs font-bold text-slate-500">
                        <span class="mr-1 inline-block animate-spin">⏳</span> جاري تحميل الرسائل...
                    </div>

                    <div id="messagesContainer" class="hidden"></div>
                </div>

                {{-- 3. شريط معاينة المرفقات أو بطاقة الطالب قبل الإرسال --}}
                <div id="attachmentPreviewBar"
                     class="hidden shrink-0 border-t border-slate-200 bg-[#e9edef] px-3.5 py-2 text-xs">
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 shadow-sm border-r-4 border-[#00a884]">
                        <div class="flex min-w-0 items-center gap-2">
                            <span id="previewIcon" class="text-base">📎</span>
                            <span id="previewText" class="truncate font-extrabold text-slate-800"></span>
                        </div>
                        <button type="button" id="cancelAttachmentBtn"
                                class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-extrabold text-rose-600 hover:bg-rose-100">
                            إلغاء ✕
                        </button>
                    </div>
                </div>

                {{-- 4. شريط الإرسال السفلي الثابت دائماً (WhatsApp Pinned Composer) --}}
                <div class="wa-composer">
                    <form id="chatForm" class="flex items-center gap-2" enctype="multipart/form-data">
                        @csrf
                        <input type="file" id="chatFileInput" name="file" class="hidden"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" id="cardTypeInput" name="card_type" value="">
                        <input type="hidden" id="studentIdInput" name="student_id"
                               value="{{ $activeConv['student_id'] ?? '' }}">
                        <input type="hidden" id="itemIdInput" name="item_id" value="">

                        {{-- حقل الإدخال الدائري مع أزرار الإرفاق السريعة --}}
                        <div class="wa-input-pill">
                            <button type="button" id="triggerFileBtn" class="wa-icon-btn" title="إرفاق ملف أو صورة">
                                <x-icon name="paperclip" :size="19" />
                            </button>

                            @if ($myChildren && $myChildren->isNotEmpty())
                                <button type="button" class="wa-icon-btn text-[#008069]" data-modal-open="shareCardModal" title="إرفاق بطاقة طالب (درجات / حضور / واجبات)">
                                    <x-icon name="clipboard-list" :size="19" />
                                </button>
                            @endif

                            <input type="text"
                                   id="chatMessageInput"
                                   name="body"
                                   placeholder="اكتب رسالة..."
                                   enterkeyhint="send"
                                   autocomplete="off">
                        </div>

                        {{-- زر الإرسال الدائري الثابت على طريقة واتساب --}}
                        <button type="submit" id="chatSubmitBtn" class="wa-send-btn" aria-label="إرسال">
                            <x-icon name="send" :size="18" />
                        </button>
                    </form>
                </div>
            @else
                {{-- حالة عدم وجود محادثة مختارة --}}
                <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                    <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white text-[#008069] shadow-md">
                        <x-icon name="message-square" :size="36" />
                    </div>
                    <h3 class="text-lg font-extrabold text-slate-800">محادثات معهد إيليا الفورية</h3>
                    <p class="mt-1 max-w-sm text-xs leading-relaxed text-slate-600">
                        اختر محادثة من القائمة أو ابدأ محادثة جديدة للتواصل المباشر ومشاركة بطاقات الدرجات والحضور والواجبات.
                    </p>
                    <button type="button"
                            class="mt-5 inline-flex items-center gap-2 rounded-full bg-[#00a884] px-5 py-2.5 text-xs font-extrabold text-white shadow-md hover:bg-[#008f6f]"
                            data-modal-open="newChatModal">
                        <x-icon name="plus" :size="15" /> بدء محادثة جديدة
                    </button>
                </div>
            @endif
        </section>
    </div>

    {{-- نافذة منبثقة: بدء محادثة جديدة --}}
    <div class="modal-overlay" id="newChatModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-extrabold text-primary">بدء محادثة جديدة</h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="18" class="text-primary" />
                </button>
            </div>

            <form method="POST" action="{{ route('chat.start') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label">اختر جهة الاتصال *</label>
                    <select name="recipient_id" class="input" required>
                        <option value="">— اختر جهة الاتصال —</option>
                        @foreach ($contacts as $contact)
                            <option value="{{ $contact['id'] }}">
                                {{ $contact['name'] }} ({{ $contact['role_label'] }})
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($myChildren && $myChildren->count() > 0)
                    <div>
                        <label class="label">تخصيص المحادثة حول طالب محدد (اختياري)</label>
                        <select name="student_id" class="input">
                            <option value="">— عام / بدون تحديد —</option>
                            @foreach ($myChildren as $ch)
                                <option value="{{ $ch['id'] }}">{{ $ch['name'] }} ({{ $ch['stage_name'] }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-primary/50">يساعد الطرف الآخر على معرفة الطالب المعني فوراً.</p>
                    </div>
                @endif

                <div>
                    <label class="label">الرسالة الأولى (اختياري)</label>
                    <textarea name="initial_message" class="input min-h-[80px]"
                              placeholder="اكتب استفسارك أو رسالتك هنا..."></textarea>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="send" :size="15" /> بدء المحادثة
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    {{-- نافذة منبثقة: مشاركة بطاقة بيانات الطالب --}}
    <div class="modal-overlay" id="shareCardModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-extrabold text-primary">
                    <x-icon name="clipboard-list" :size="18" class="text-[#008069]" /> مشاركة بطاقة طالب داخل المحادثة
                </h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="18" class="text-primary" />
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="label">اختر الطالب *</label>
                    <select id="cardModalStudentSelect" class="input">
                        @if (!empty($activeConv['student_id']))
                            <option value="{{ $activeConv['student_id'] }}">{{ $activeConv['student_name'] }}</option>
                        @endif
                        @if ($myChildren && $myChildren->isNotEmpty())
                            @foreach ($myChildren as $ch)
                                @if ((int) ($activeConv['student_id'] ?? 0) !== (int) $ch['id'])
                                    <option value="{{ $ch['id'] }}">{{ $ch['name'] }} ({{ $ch['stage_name'] }} • #{{ $ch['student_no'] }})</option>
                                @endif
                            @endforeach
                        @endif
                    </select>
                </div>

                <div>
                    <label class="label">نوع البطاقة المطلوب مشاركتها *</label>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-primary/10 p-3 hover:bg-primary/5">
                            <input type="radio" name="card_choice" value="attendance" checked class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">سجل الحضور والغياب</div>
                                <div class="text-[10px] text-primary/50">نسبة الحضور وأيام الغياب</div>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-primary/10 p-3 hover:bg-primary/5">
                            <input type="radio" name="card_choice" value="grade" class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">الدرجات والامتحانات</div>
                                <div class="text-[10px] text-primary/50">نتائج الاختبارات والتقييم</div>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-primary/10 p-3 hover:bg-primary/5">
                            <input type="radio" name="card_choice" value="homework" class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">استعلام عن واجب</div>
                                <div class="text-[10px] text-primary/50">حالة الواجب وملاحظات المدرس</div>
                            </div>
                        </label>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-primary/10 p-3 hover:bg-primary/5">
                            <input type="radio" name="card_choice" value="overview" class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">نظرة عامة على الطالب</div>
                                <div class="text-[10px] text-primary/50">المرحلة ورقم الطالب والمستوى</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div id="cardLivePreviewBox" class="rounded-xl border border-primary/10 bg-primary/4 p-3 text-xs">
                    <div class="mb-1 text-[11px] font-extrabold text-primary/60">معاينة البطاقة:</div>
                    <div id="cardPreviewContent" class="font-bold text-primary/80">جاري تجهيز المعاينة...</div>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="button" id="confirmAttachCardBtn" class="btn btn-dark flex-1">
                        <x-icon name="check" :size="15" /> إرفاق البطاقة بالرسالة
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            'use strict';

            document.body.classList.add('wa-chat-page');

            const waShell = document.getElementById('waShell');
            const backBtn = document.getElementById('waBackToListBtn');
            const activeConversationId = {{ $activeConversationId ? (int) $activeConversationId : 'null' }};
            const isAdminUser = {{ $user->isAdmin() ? 'true' : 'false' }};
            const pollUrl = activeConversationId ? "{{ url('/chat/conversations') }}/" + activeConversationId + "/poll" : null;
            const sendUrl = activeConversationId ? "{{ url('/chat/conversations') }}/" + activeConversationId + "/messages" : null;
            const deleteBaseUrl = "{{ url('/chat/messages') }}";
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            /* ضبط أبعاد شاشة الواتساب بدقة على الموبايل حتى مع ظهور لوحة المفاتيح */
            function updateWaViewportHeight() {
                if (!waShell) return;
                if (window.innerWidth >= 1024) {
                    waShell.style.removeProperty('--wa-mobile-h');
                    return;
                }
                const header = document.querySelector('body > div > header');
                const headerH = header ? header.getBoundingClientRect().height : 0;
                const vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                const available = Math.max(320, Math.floor(vh - headerH));
                waShell.style.setProperty('--wa-mobile-h', available + 'px');
            }

            updateWaViewportHeight();
            window.addEventListener('resize', updateWaViewportHeight);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', () => {
                    updateWaViewportHeight();
                    scrollToBottom();
                });
            }

            /* التنقل بين قائمة المحادثات وشاشة الدردشة على الموبايل مثل واتساب */
            if (backBtn && waShell) {
                backBtn.addEventListener('click', function () {
                    waShell.classList.remove('wa-mobile-mode-chat');
                    waShell.classList.add('wa-mobile-mode-list');
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', "{{ route('chat.index', ['list' => 1]) }}");
                    }
                });
            }

            document.querySelectorAll('[data-conv-item]').forEach((item) => {
                item.addEventListener('click', function (e) {
                    const convId = parseInt(this.getAttribute('data-conv-id') || '0', 10);
                    if (window.innerWidth < 1024 && convId === activeConversationId && waShell) {
                        e.preventDefault();
                        waShell.classList.remove('wa-mobile-mode-list');
                        waShell.classList.add('wa-mobile-mode-chat');
                        scrollToBottom();
                        if (window.history && window.history.replaceState) {
                            window.history.replaceState({}, '', "{{ route('chat.index') }}?conversation_id=" + convId);
                        }
                    }
                });
            });

            /* بحث المحادثات في القائمة الجانبية */
            const searchInput = document.getElementById('conversationSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function (e) {
                    const query = e.target.value.toLowerCase().trim();
                    document.querySelectorAll('[data-conv-item]').forEach(el => {
                        const name = (el.getAttribute('data-conv-name') || '').toLowerCase();
                        el.style.display = name.includes(query) ? 'flex' : 'none';
                    });
                });
            }

            if (!activeConversationId) return;

            const state = {
                messages: [],
                lastMessageId: 0,
                isPolling: false,
                pollTimer: null,
                attachedCard: null,
            };

            const messagesArea = document.getElementById('messagesArea');
            const messagesContainer = document.getElementById('messagesContainer');
            const messagesLoading = document.getElementById('messagesLoading');
            const chatForm = document.getElementById('chatForm');
            const chatMessageInput = document.getElementById('chatMessageInput');
            const chatFileInput = document.getElementById('chatFileInput');
            const triggerFileBtn = document.getElementById('triggerFileBtn');
            const attachmentPreviewBar = document.getElementById('attachmentPreviewBar');
            const previewText = document.getElementById('previewText');
            const cancelAttachmentBtn = document.getElementById('cancelAttachmentBtn');

            if (triggerFileBtn && chatFileInput) {
                triggerFileBtn.addEventListener('click', () => chatFileInput.click());
                chatFileInput.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        showAttachmentPreview('ملف مرفق: ' + file.name + ' (' + Math.max(1, Math.round(file.size / 1024)) + ' KB)');
                    }
                });
            }

            if (cancelAttachmentBtn) {
                cancelAttachmentBtn.addEventListener('click', function () {
                    if (chatFileInput) chatFileInput.value = '';
                    document.getElementById('cardTypeInput').value = '';
                    document.getElementById('itemIdInput').value = '';
                    state.attachedCard = null;
                    attachmentPreviewBar.classList.add('hidden');
                });
            }

            function showAttachmentPreview(text) {
                if (!attachmentPreviewBar) return;
                previewText.textContent = text;
                attachmentPreviewBar.classList.remove('hidden');
            }

            function scrollToBottom() {
                if (messagesArea) {
                    messagesArea.scrollTop = messagesArea.scrollHeight;
                }
            }

            function escapeHtml(str) {
                if (!str) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            /* بناء فقاعة الرسالة بأسلوب واتساب */
            function renderMessage(msg) {
                const isMe = Boolean(msg.is_me);
                const row = document.createElement('div');
                row.className = 'wa-bubble-row ' + (isMe ? 'is-me' : 'is-other');
                row.setAttribute('data-msg-id', msg.id);

                let contentHtml = '';

                if (!isMe) {
                    contentHtml += `<div class="mb-1 text-[11px] font-extrabold text-[#008069]">${escapeHtml(msg.sender_name)} <span class="text-[9px] font-bold text-slate-400">(${escapeHtml(msg.sender_role)})</span></div>`;
                }

                // بطاقة الطالب التفاعلية
                if (msg.has_card && msg.card) {
                    const card = msg.card;
                    const badgeToneClass = card.badge_tone === 'danger'
                        ? 'bg-rose-100 text-rose-800'
                        : (card.badge_tone === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800');

                    let itemsHtml = '';
                    if (card.items) {
                        for (const [k, v] of Object.entries(card.items)) {
                            itemsHtml += `<div class="flex justify-between gap-3 py-1 border-b border-slate-100 last:border-0"><span class="text-slate-500">${escapeHtml(k)}:</span><strong class="text-slate-800">${escapeHtml(String(v))}</strong></div>`;
                        }
                    }

                    contentHtml += `
                        <div class="mb-1.5 rounded-xl border border-black/5 bg-white/95 p-2.5 text-right shadow-sm border-r-4 border-r-[#00a884]">
                            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-1.5 mb-1.5">
                                <span class="text-xs font-black text-slate-900">${escapeHtml(card.title || 'بطاقة طالب')}</span>
                                <span class="chip text-[10px] font-black ${badgeToneClass}">${escapeHtml(card.badge || '')}</span>
                            </div>
                            <div class="text-[11px] font-extrabold text-[#008069] mb-1">
                                الطالب: ${escapeHtml(card.student_name)} (${escapeHtml(card.stage_name)} • #${escapeHtml(String(card.student_no))})
                            </div>
                            <div class="text-[11px] space-y-0.5">
                                ${itemsHtml}
                            </div>
                        </div>
                    `;
                }

                if (msg.body) {
                    contentHtml += `<div class="text-[13px] font-semibold leading-relaxed whitespace-pre-wrap text-[#111b21]">${escapeHtml(msg.body)}</div>`;
                }

                if (msg.attachment) {
                    const att = msg.attachment;
                    if (att.is_image) {
                        contentHtml += `
                            <div class="mt-1.5">
                                <a href="${att.url}" target="_blank" rel="noopener">
                                    <img src="${att.url}" alt="${escapeHtml(att.name)}" class="max-h-56 rounded-lg object-cover border border-black/10">
                                </a>
                            </div>
                        `;
                    } else {
                        contentHtml += `
                            <div class="mt-1.5 rounded-lg bg-black/5 px-2.5 py-1.5">
                                <a href="${att.url}" download="${escapeHtml(att.name)}" class="inline-flex items-center gap-1.5 text-xs font-extrabold text-[#008069] hover:underline">
                                    <span>📄</span> <span>${escapeHtml(att.name)}</span>
                                </a>
                            </div>
                        `;
                    }
                }

                const canDelete = !msg.pending && (Boolean(msg.can_delete) || isMe || isAdminUser);
                const deleteBtnHtml = canDelete
                    ? `<button type="button" data-delete-msg="${msg.id}" class="mr-auto inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-bold text-rose-600/80 hover:bg-rose-50 hover:text-rose-700 transition" title="حذف الرسالة لدى الطرفين">🗑️ حذف</button>`
                    : '';

                row.innerHTML = `
                    <div class="wa-bubble ${isMe ? 'is-me' : 'is-other'}">
                        ${contentHtml}
                        <div class="wa-bubble-meta">
                            ${deleteBtnHtml}
                            <span>${escapeHtml(msg.time || '')}</span>
                            ${isMe ? (msg.pending ? '<span>⏳</span>' : '<span class="wa-ticks">✓✓</span>') : ''}
                        </div>
                    </div>
                `;

                const delBtn = row.querySelector('[data-delete-msg]');
                if (delBtn) {
                    delBtn.addEventListener('click', () => deleteMessageForBoth(msg.id, row));
                }

                return row;
            }

            async function deleteMessageForBoth(msgId, rowElement) {
                if (!msgId || String(msgId).startsWith('temp_')) return;
                if (!window.confirm('هل تريد حذف هذه الرسالة نهائياً من الطرفين؟')) return;

                if (rowElement) {
                    rowElement.style.opacity = '0.4';
                    rowElement.style.pointerEvents = 'none';
                }

                try {
                    const res = await fetch(`${deleteBaseUrl}/${msgId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    });

                    if (!res.ok) throw new Error('Delete failed');

                    if (rowElement) {
                        rowElement.remove();
                    }
                    state.messages = state.messages.filter(m => Number(m.id) !== Number(msgId));
                    if (window.eyalToast) {
                        window.eyalToast('تم حذف الرسالة من الطرفين', 'success');
                    }
                } catch (err) {
                    if (rowElement) {
                        rowElement.style.opacity = '1';
                        rowElement.style.pointerEvents = 'auto';
                    }
                    if (window.eyalToast) {
                        window.eyalToast('تعذر حذف الرسالة، حاول مرة أخرى', 'danger');
                    }
                }
            }

            async function fetchMessages() {
                if (state.isPolling || document.visibilityState === 'hidden') return;
                state.isPolling = true;

                try {
                    const res = await fetch(`${pollUrl}?after=${state.lastMessageId}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) throw new Error('Poll failed');
                    const data = await res.json();

                    if (Array.isArray(data.existing_ids) && messagesContainer) {
                        const existingSet = new Set(data.existing_ids.map(id => Number(id)));
                        messagesContainer.querySelectorAll('[data-msg-id]').forEach(node => {
                            const rawId = node.getAttribute('data-msg-id') || '';
                            if (!rawId.startsWith('temp_') && !existingSet.has(Number(rawId))) {
                                node.remove();
                            }
                        });
                    }

                    if (data.messages && data.messages.length > 0) {
                        let shouldScroll = false;
                        const isNearBottom = messagesArea
                            ? (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight < 140)
                            : false;

                        data.messages.forEach(msg => {
                            const existing = document.querySelector(`[data-msg-id="${msg.id}"]`);
                            if (!existing) {
                                messagesContainer.appendChild(renderMessage(msg));
                                state.lastMessageId = Math.max(state.lastMessageId, msg.id);
                                state.messages.push(msg);
                                shouldScroll = true;
                            }
                        });

                        if (shouldScroll && (isNearBottom || state.messages.length <= data.messages.length)) {
                            scrollToBottom();
                        }
                    }

                    if (messagesLoading) messagesLoading.classList.add('hidden');
                    if (messagesContainer) messagesContainer.classList.remove('hidden');
                } catch (e) {
                    console.warn('Poll error:', e);
                } finally {
                    state.isPolling = false;
                }
            }

            fetchMessages().then(scrollToBottom);
            state.pollTimer = setInterval(fetchMessages, 2500);

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    fetchMessages();
                }
            });

            if (chatForm) {
                chatForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const body = chatMessageInput ? chatMessageInput.value.trim() : '';
                    const hasFile = chatFileInput && chatFileInput.files && chatFileInput.files.length > 0;
                    const cardType = document.getElementById('cardTypeInput').value;

                    if (!body && !hasFile && !cardType) return;

                    const formData = new FormData(this);

                    const tempId = 'temp_' + Date.now();
                    const optimisticMsg = {
                        id: tempId,
                        is_me: true,
                        body: body,
                        time: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
                        pending: true,
                        has_card: !!cardType,
                        card: state.attachedCard,
                        attachment: hasFile ? { name: chatFileInput.files[0].name, is_image: false, url: '#' } : null
                    };

                    const tempNode = renderMessage(optimisticMsg);
                    messagesContainer.appendChild(tempNode);
                    scrollToBottom();

                    if (chatMessageInput) {
                        chatMessageInput.value = '';
                        chatMessageInput.focus();
                    }
                    if (chatFileInput) chatFileInput.value = '';
                    document.getElementById('cardTypeInput').value = '';
                    document.getElementById('itemIdInput').value = '';
                    state.attachedCard = null;
                    if (attachmentPreviewBar) attachmentPreviewBar.classList.add('hidden');

                    try {
                        const res = await fetch(sendUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        if (!res.ok) throw new Error('Send failed');
                        const data = await res.json();

                        if (data.message) {
                            tempNode.remove();
                            messagesContainer.appendChild(renderMessage(data.message));
                            state.lastMessageId = Math.max(state.lastMessageId, data.message.id);
                            scrollToBottom();
                        }
                    } catch (err) {
                        tempNode.classList.add('opacity-50');
                        if (window.eyalToast) {
                            window.eyalToast('فشل إرسال الرسالة، يرجى المحاولة ثانية', 'danger');
                        }
                    }
                });
            }

            /* تفاعل نافذة مشاركة بطاقة الطالب */
            const cardModalStudentSelect = document.getElementById('cardModalStudentSelect');
            const cardPreviewContent = document.getElementById('cardPreviewContent');
            const confirmAttachCardBtn = document.getElementById('confirmAttachCardBtn');

            async function updateCardPreview() {
                if (!cardModalStudentSelect || !cardPreviewContent) return;
                const studentId = cardModalStudentSelect.value;
                const selectedRadio = document.querySelector('input[name="card_choice"]:checked');
                const cardType = selectedRadio ? selectedRadio.value : 'attendance';

                if (!studentId) {
                    cardPreviewContent.textContent = 'يرجى اختيار طالب أولاً.';
                    return;
                }

                cardPreviewContent.innerHTML = 'جاري توليد المعاينة...';

                try {
                    const res = await fetch("{{ route('chat.preview_card') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ student_id: studentId, card_type: cardType })
                    });

                    if (!res.ok) throw new Error();
                    const data = await res.json();

                    if (data.card) {
                        state.tempSelectedCard = data.card;
                        let itemsStr = '';
                        if (data.card.items) {
                            for (const [k, v] of Object.entries(data.card.items)) {
                                itemsStr += `<div class="flex justify-between py-0.5"><span>${escapeHtml(k)}:</span><strong>${escapeHtml(String(v))}</strong></div>`;
                            }
                        }
                        cardPreviewContent.innerHTML = `
                            <div class="rounded-xl bg-white p-3 border border-primary/10 border-r-4 border-r-[#00a884]">
                                <div class="flex justify-between font-extrabold text-primary mb-1">
                                    <span>${escapeHtml(data.card.title)}</span>
                                    <span class="text-[#008069]">${escapeHtml(data.card.badge || '')}</span>
                                </div>
                                <div class="text-[11px] text-primary/70 space-y-0.5">${itemsStr}</div>
                            </div>
                        `;
                    }
                } catch {
                    cardPreviewContent.textContent = 'تعذر تجهيز المعاينة.';
                }
            }

            if (cardModalStudentSelect) {
                cardModalStudentSelect.addEventListener('change', updateCardPreview);
            }
            document.querySelectorAll('input[name="card_choice"]').forEach(r => r.addEventListener('change', updateCardPreview));
            document.querySelectorAll('[data-modal-open="shareCardModal"]').forEach(btn => {
                btn.addEventListener('click', updateCardPreview);
            });

            if (confirmAttachCardBtn) {
                confirmAttachCardBtn.addEventListener('click', function () {
                    const studentId = cardModalStudentSelect ? cardModalStudentSelect.value : '';
                    const selectedRadio = document.querySelector('input[name="card_choice"]:checked');
                    const cardType = selectedRadio ? selectedRadio.value : 'attendance';

                    document.getElementById('cardTypeInput').value = cardType;
                    document.getElementById('studentIdInput').value = studentId;
                    state.attachedCard = state.tempSelectedCard;

                    showAttachmentPreview('بطاقة طالب جاهزة للإرسال: ' + (state.attachedCard?.title || cardType));

                    const modal = document.getElementById('shareCardModal');
                    if (window.eyalCloseModal && modal) {
                        window.eyalCloseModal(modal);
                    } else if (modal) {
                        modal.classList.remove('open');
                        document.body.classList.remove('modal-open');
                    }
                });
            }
        })();
    </script>
@endpush

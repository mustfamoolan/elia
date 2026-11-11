@extends($layout)

@section('title', 'المحادثات والدعم')

@section('content')
    @php
        $activeConv = $activeConv ?? ($activeConversationId ? $conversations->firstWhere('id', $activeConversationId) : null);
    @endphp

    <div class="fade-in space-y-4">
        {{-- تنبيه إذا كان الشات معطلاً من الإدارة --}}
        @if (!$chatEnabled && !$user->isAdmin())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
                <div class="flex items-center gap-2 font-extrabold text-sm">
                    <x-icon name="info" :size="18" class="text-amber-600" /> نظام المحادثات متوقف مؤقتاً
                </div>
                <p class="mt-1 text-xs text-amber-700/80">
                    تم إيقاف استقبال الرسائل الجديدة حالياً من قبل إدارة المعهد. يمكنك فقط تصفح المحادثات السابقة.
                </p>
            </div>
        @endif

        {{-- حاوية المحادثات الرئيسية بتصميم سريع وخفيف --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:h-[calc(100vh-170px)] min-h-[550px]">
            {{-- الشريط الجانبي لقائمة المحادثات --}}
            <aside class="card flex flex-col p-0 lg:col-span-4 overflow-hidden">
                <div class="border-b border-primary/5 p-4 bg-primary/2">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                <x-icon name="message-square" :size="16" />
                            </div>
                            <h2 class="text-sm font-extrabold text-primary">المحادثات ({{ $conversations->count() }})</h2>
                        </div>
                        <button type="button" class="btn btn-dark btn-sm" data-modal-open="newChatModal">
                            <x-icon name="plus" :size="13" /> محادثة جديدة
                        </button>
                    </div>

                    <div class="relative">
                        <input type="text" id="conversationSearchInput" class="input text-xs py-1.5 pl-8"
                            placeholder="بحث في المحادثات...">
                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-primary/40 text-xs">🔍</span>
                    </div>
                </div>

                {{-- قائمة المحادثات النشطة --}}
                <div class="flex-1 overflow-y-auto divide-y divide-primary/5" id="conversationsList">
                    @forelse ($conversations as $conv)
                        @php
                            $isActive = $activeConversationId === $conv['id'];
                            $roleTone = match ($conv['other_user_role']) {
                                'مدير' => 'bg-rose-50 text-rose-700',
                                'مدرس' => 'bg-indigo-50 text-indigo-700',
                                default => 'bg-emerald-50 text-emerald-700',
                            };
                        @endphp
                        <a href="{{ route('chat.index', ['conversation_id' => $conv['id']]) }}" data-conv-item
                            data-conv-name="{{ $conv['other_user_name'] }}"
                            class="flex items-start gap-3 p-3.5 transition-all {{ $isActive ? 'bg-primary/8 border-r-4 border-primary' : 'hover:bg-primary/3' }}">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary font-black text-sm">
                                {{ mb_substr($conv['other_user_name'], 0, 1) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-1 mb-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span
                                            class="text-xs font-extrabold text-primary truncate">{{ $conv['other_user_name'] }}</span>
                                        <span
                                            class="chip text-[9px] px-1.5 py-0.5 {{ $roleTone }}">{{ $conv['other_user_role'] }}</span>
                                    </div>
                                    <span class="text-[10px] text-primary/50 shrink-0">{{ $conv['last_message_time'] }}</span>
                                </div>

                                @if ($conv['student_name'])
                                    <div class="text-[10px] font-bold text-secondary mb-1 flex items-center gap-1 truncate">
                                        <span>حول الطالب:</span>
                                        <span class="underline">{{ $conv['student_name'] }}</span>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-[11px] text-primary/65 truncate">
                                        {{ $conv['last_message'] ?: 'بدء المحادثة...' }}</p>
                                    @if ($conv['unread_count'] > 0)
                                        <span
                                            class="chip bg-secondary text-white font-black text-[10px] px-1.5 py-0.5 shrink-0 rounded-full">
                                            {{ $conv['unread_count'] }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="p-8 text-center text-primary/50">
                            <x-icon name="message-square" :size="32" class="mx-auto mb-2 opacity-40" />
                            <p class="text-xs font-bold">لا توجد محادثات سابقة</p>
                            <p class="text-[10px] text-primary/40 mt-1">ابدأ محادثة جديدة للتواصل والدعم الفني</p>
                            <button type="button" class="btn btn-outline btn-sm mt-3" data-modal-open="newChatModal">
                                <x-icon name="plus" :size="12" /> محادثة جديدة
                            </button>
                        </div>
                    @endforelse
                </div>
            </aside>

            {{-- نافذة المحادثة النشطة --}}
            <main class="card flex flex-col p-0 lg:col-span-8 overflow-hidden h-full">
                @if ($activeConversationId)
                    @php
                        $activeConv = $conversations->firstWhere('id', $activeConversationId);
                    @endphp

                    {{-- شريط عنوان المحادثة النشطة --}}
                    <div class="border-b border-primary/5 p-3.5 bg-white flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white font-extrabold text-sm">
                                {{ mb_substr($activeConv['other_user_name'] ?? 'م', 0, 1) }}
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-extrabold text-primary truncate">
                                        {{ $activeConv['other_user_name'] ?? 'محادثة' }}</h3>
                                    <span
                                        class="chip text-[10px] bg-primary/5 text-primary">{{ $activeConv['other_user_role'] ?? '' }}</span>
                                </div>
                                @if (!empty($activeConv['student_name']))
                                    <div class="text-[11px] font-bold text-secondary flex items-center gap-1 mt-0.5">
                                        <span>الطالب المعني:</span>
                                        <strong>{{ $activeConv['student_name'] }}</strong>
                                    </div>
                                @else
                                    <div class="text-[10px] text-emerald-600 font-bold flex items-center gap-1 mt-0.5">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        <span>قناة دعم رسمية مباشرة</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            {{-- زر مشاركة بطاقة الابن السريعة --}}
                            @if ($user->isParent() || $user->isStaff())
                                <button type="button" class="btn btn-outline btn-sm" data-modal-open="shareCardModal"
                                    title="مشاركة بطاقة درجات أو حضور أو واجب">
                                    <x-icon name="clipboard-list" :size="14" />
                                    <span class="hidden sm:inline">مشاركة بطاقة طالب</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- منطقة تدفق الرسائل مع سكرول تلقائي --}}
                    <div id="messagesArea" class="flex-1 overflow-y-auto p-4 space-y-3 bg-[#f8fafc]/70">
                        <div id="messagesLoading" class="py-10 text-center text-xs font-bold text-primary/50">
                            <span class="inline-block animate-spin mr-1">⏳</span> جاري تحميل الرسائل...
                        </div>
                        <div id="messagesContainer" class="space-y-3 hidden"></div>
                    </div>

                    {{-- شريط معاينة المرفق أو البطاقة قبل الإرسال --}}
                    <div id="attachmentPreviewBar"
                        class="hidden border-t border-primary/10 bg-primary/4 px-4 py-2 flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span id="previewIcon">📎</span>
                            <span id="previewText" class="font-extrabold text-primary truncate"></span>
                        </div>
                        <button type="button" id="cancelAttachmentBtn"
                            class="text-rose-600 hover:text-rose-800 text-xs font-bold px-2">إلغاء</button>
                    </div>

                    {{-- صندوق كتابة الرسالة والإرسال --}}
                    <div class="border-t border-primary/5 p-3 bg-white">
                        <form id="chatForm" class="flex items-center gap-2" enctype="multipart/form-data">
                            @csrf
                            <input type="file" id="chatFileInput" name="file" class="hidden"
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                            <input type="hidden" id="cardTypeInput" name="card_type" value="">
                            <input type="hidden" id="studentIdInput" name="student_id"
                                value="{{ $activeConv['student_id'] ?? '' }}">
                            <input type="hidden" id="itemIdInput" name="item_id" value="">

                            {{-- زر إرفاق ملف --}}
                            <button type="button" id="triggerFileBtn"
                                class="btn btn-ghost btn-sm text-primary/60 hover:text-primary p-2" title="إرفاق ملف أو صورة">
                                <x-icon name="paperclip" :size="18" />
                            </button>

                            {{-- حقل نص الرسالة --}}
                            <div class="relative flex-1">
                                <input type="text" id="chatMessageInput" name="body" class="input py-2 text-xs"
                                    placeholder="اكتب رسالتك هنا... (اضغط Enter للإرسال)" autocomplete="off">
                            </div>

                            {{-- زر الإرسال السريع الفوري --}}
                            <button type="submit" id="chatSubmitBtn"
                                class="btn btn-dark btn-sm px-4 flex items-center gap-1.5 shrink-0">
                                <x-icon name="send" :size="14" />
                                <span>إرسال</span>
                            </button>
                        </form>
                    </div>

                @else
                    {{-- في حال عدم اختيار محادثة --}}
                    <div class="flex h-full flex-col items-center justify-center p-8 text-center text-primary/50">
                        <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-primary/5 text-primary mb-3">
                            <x-icon name="message-square" :size="30" />
                        </div>
                        <h3 class="text-base font-extrabold text-primary">نافذة المحادثات والدعم</h3>
                        <p class="text-xs text-primary/60 max-w-sm mt-1">اختر محادثة من القائمة الجانبية أو ابدأ محادثة جديدة
                            للتواصل مع إدارة المعهد أو الكادر التدريسي.</p>
                        <button type="button" class="btn btn-dark btn-sm mt-4" data-modal-open="newChatModal">
                            <x-icon name="plus" :size="13" /> محادثة جديدة
                        </button>
                    </div>
                @endif
            </main>
        </div>
    </div>

    {{-- نافذة منبثقة: بدء محادثة جديدة --}}
    <div class="modal-overlay" id="newChatModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between border-b border-primary/5 pb-3">
                <h3 class="text-base font-extrabold">بدء محادثة جديدة</h3>
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

                @if ($user->isParent() && !empty($myChildren) && $myChildren->count() > 0)
                    <div>
                        <label class="label">تخصيص المحادثة حول أحد الأبناء (اختياري)</label>
                        <select name="student_id" class="input">
                            <option value="">— عام / بدون تحديد —</option>
                            @foreach ($myChildren as $ch)
                                <option value="{{ $ch['id'] }}">{{ $ch['name'] }} ({{ $ch['stage_name'] }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-primary/50">يساعد المدرس أو الإدارة على معرفة الطالب المعني فوراً.</p>
                    </div>
                @endif

                <div>
                    <label class="label">الرسالة الأولى (اختياري)</label>
                    <textarea name="initial_message" class="input min-h-[80px]"
                        placeholder="اكتب استفسارك أو رسالتك الترحيبية..."></textarea>
                </div>

                <div class="flex gap-2 pt-2 border-t border-primary/5">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="send" :size="15" /> بدء المحادثة
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    {{-- نافذة منبثقة: مشاركة بطاقة بيانات الابن (حضور، درجات، واجبات) --}}
    <div class="modal-overlay" id="shareCardModal">
        <div class="modal-panel modal-md">
            <div class="mb-4 flex items-center justify-between border-b border-primary/5 pb-3">
                <h3 class="text-base font-extrabold flex items-center gap-2">
                    <x-icon name="clipboard-list" :size="18" class="text-primary" /> مشاركة بطاقة طالب داخل الشات
                </h3>
                <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                    <x-icon name="x" :size="18" class="text-primary" />
                </button>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="label">اختر الطالب *</label>
                    <select id="cardModalStudentSelect" class="input">
                        @if ($user->isParent() && !empty($myChildren))
                            @foreach ($myChildren as $ch)
                                <option value="{{ $ch['id'] }}">{{ $ch['name'] }} ({{ $ch['stage_name'] }} •
                                    #{{ $ch['student_no'] }})</option>
                            @endforeach
                        @elseif (!empty($activeConv['student_id']))
                            <option value="{{ $activeConv['student_id'] }}">{{ $activeConv['student_name'] }}</option>
                        @endif
                    </select>
                </div>

                <div>
                    <label class="label">نوع البطاقة المطلوب مشاركتها *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            class="flex items-center gap-2 rounded-xl border border-primary/10 p-3 cursor-pointer hover:bg-primary/3">
                            <input type="radio" name="card_choice" value="attendance" checked class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">سجل الحضور والغياب</div>
                                <div class="text-[10px] text-primary/50">نسبة الحضور وأيام الغياب</div>
                            </div>
                        </label>
                        <label
                            class="flex items-center gap-2 rounded-xl border border-primary/10 p-3 cursor-pointer hover:bg-primary/3">
                            <input type="radio" name="card_choice" value="grade" class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">الدرجات والامتحانات</div>
                                <div class="text-[10px] text-primary/50">نتائج الاختبارات والتقييم</div>
                            </div>
                        </label>
                        <label
                            class="flex items-center gap-2 rounded-xl border border-primary/10 p-3 cursor-pointer hover:bg-primary/3">
                            <input type="radio" name="card_choice" value="homework" class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">استعلام عن واجب</div>
                                <div class="text-[10px] text-primary/50">حالة الواجب وملاحظات المدرس</div>
                            </div>
                        </label>
                        <label
                            class="flex items-center gap-2 rounded-xl border border-primary/10 p-3 cursor-pointer hover:bg-primary/3">
                            <input type="radio" name="card_choice" value="overview" class="text-primary">
                            <div>
                                <div class="text-xs font-extrabold text-primary">نظرة عامة على الطالب</div>
                                <div class="text-[10px] text-primary/50">المرحلة ورقم الطالب والمستوى</div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- معاينة البطاقة --}}
                <div id="cardLivePreviewBox" class="rounded-xl border border-primary/10 bg-primary/2 p-3 text-xs">
                    <div class="text-[11px] font-extrabold text-primary/60 mb-1">معاينة شكل البطاقة التي ستظهر في الشات:
                    </div>
                    <div id="cardPreviewContent" class="text-primary/75 font-bold">جاري تجهيز المعاينة...</div>
                </div>

                <div class="flex gap-2 pt-2 border-t border-primary/5">
                    <button type="button" id="confirmAttachCardBtn" class="btn btn-dark flex-1">
                        <x-icon name="check" :size="15" /> تأكيد وإرفاق بالرسالة
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

            const activeConversationId = {{ $activeConversationId ? $activeConversationId : 'null' }};
            const currentUserId = {{ $user->id }};
            const pollUrl = activeConversationId ? "{{ url('/chat/conversations') }}/" + activeConversationId + "/poll" : null;
            const sendUrl = activeConversationId ? "{{ url('/chat/conversations') }}/" + activeConversationId + "/messages" : null;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            // بحث المحادثات في القائمة الجانبية
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

            /* -------------------------------------------------------------------------
             | محرك المحادثات التفاعلي الفوري (State Management & Adaptive Polling)
             | ------------------------------------------------------------------------- */
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

            // تفعيل زر اختيار الملفات
            if (triggerFileBtn && chatFileInput) {
                triggerFileBtn.addEventListener('click', () => chatFileInput.click());
                chatFileInput.addEventListener('change', function () {
                    if (this.files && this.files[0]) {
                        const file = this.files[0];
                        showAttachmentPreview('📎 ملف مرفق: ' + file.name + ' (' + Math.round(file.size / 1024) + ' KB)');
                    }
                });
            }

            // إلغاء المرفق أو البطاقة
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

            /* بناء فقاعة الرسالة في الـ DOM */
            function renderMessage(msg) {
                const isMe = msg.is_me;
                const div = document.createElement('div');
                div.className = 'flex flex-col ' + (isMe ? 'items-end' : 'items-start') + ' animate-fadeIn';
                div.setAttribute('data-msg-id', msg.id);

                let contentHtml = '';

                // بطاقة الطالب التفاعلية (Rich Card)
                if (msg.has_card && msg.card) {
                    const card = msg.card;
                    const badgeToneClass = card.badge_tone === 'danger' ? 'bg-rose-100 text-rose-800' : (card.badge_tone === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800');

                    let itemsHtml = '';
                    if (card.items) {
                        for (const [k, v] of Object.entries(card.items)) {
                            itemsHtml += `<div class="flex justify-between py-0.5 border-b border-primary/5 last:border-0"><span class="opacity-70">${k}:</span><strong>${v}</strong></div>`;
                        }
                    }

                    contentHtml += `
                    <div class="mb-2 rounded-2xl border border-primary/10 bg-white p-3 shadow-sm text-right min-w-[260px] max-w-sm text-primary">
                        <div class="flex items-center justify-between gap-2 border-b border-primary/5 pb-2 mb-2">
                            <span class="text-xs font-black">${card.title || 'بطاقة طالب'}</span>
                            <span class="chip text-[10px] font-black ${badgeToneClass}">${card.badge || ''}</span>
                        </div>
                        <div class="text-[11px] font-bold text-secondary mb-1">
                            الطالب: ${card.student_name} (${card.stage_name} • #${card.student_no})
                        </div>
                        <div class="text-[10px] space-y-0.5">
                            ${itemsHtml}
                        </div>
                    </div>
                `;
                }

                // نص الرسالة
                if (msg.body) {
                    contentHtml += `<div class="text-xs font-semibold leading-relaxed whitespace-pre-wrap">${escapeHtml(msg.body)}</div>`;
                }

                // الملف المرفق
                if (msg.attachment) {
                    const att = msg.attachment;
                    if (att.is_image) {
                        contentHtml += `
                        <div class="mt-2">
                            <a href="${att.url}" target="_blank">
                                <img src="${att.url}" alt="${att.name}" class="max-h-48 rounded-xl object-cover border border-white/20">
                            </a>
                        </div>
                    `;
                    } else {
                        contentHtml += `
                        <div class="mt-2">
                            <a href="${att.url}" download="${att.name}" class="inline-flex items-center gap-1.5 text-xs font-bold underline ${isMe ? 'text-white' : 'text-primary'}">
                                <span>📄 تنزيل المرفق:</span> <span>${att.name}</span>
                            </a>
                        </div>
                    `;
                    }
                }

                const bubbleClasses = isMe
                    ? 'rounded-2xl rounded-bl-sm bg-primary text-white p-3.5 max-w-[85%] sm:max-w-[75%] shadow-sm'
                    : 'rounded-2xl rounded-br-sm bg-white border border-primary/10 text-primary p-3.5 max-w-[85%] sm:max-w-[75%] shadow-sm';

                const senderLabel = !isMe ? `<div class="text-[10px] font-extrabold text-primary/60 mb-1">${escapeHtml(msg.sender_name)} • ${escapeHtml(msg.sender_role)}</div>` : '';

                div.innerHTML = `
                ${senderLabel}
                <div class="${bubbleClasses}">
                    ${contentHtml}
                    <div class="mt-1 flex items-center justify-end gap-1 text-[9px] opacity-75">
                        <span>${msg.time || ''}</span>
                        ${isMe ? (msg.pending ? '<span>⏳</span>' : '<span>✓✓</span>') : ''}
                    </div>
                </div>
            `;

                return div;
            }

            function escapeHtml(str) {
                if (!str) return '';
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }

            /* جلب الرسائل الجديدة بنظام Polling خفيف */
            async function fetchMessages() {
                if (state.isPolling || document.visibilityState === 'hidden') return;
                state.isPolling = true;

                try {
                    const res = await fetch(`${pollUrl}?after=${state.lastMessageId}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (!res.ok) throw new Error('Poll failed');
                    const data = await res.json();

                    if (data.messages && data.messages.length > 0) {
                        let shouldScroll = false;
                        const isNearBottom = messagesArea ? (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight < 120) : false;

                        data.messages.forEach(msg => {
                            // تجنب تكرار الرسائل المحقونة فورياً
                            const existing = document.querySelector(`[data-msg-id="${msg.id}"]`);
                            if (!existing) {
                                messagesContainer.appendChild(renderMessage(msg));
                                state.lastMessageId = Math.max(state.lastMessageId, msg.id);
                                shouldScroll = true;
                            }
                        });

                        if (shouldScroll && (isNearBottom || state.messages.length === 0)) {
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

            // بدء الـ Polling التكيفي (كل 2.5 ثانية)
            fetchMessages().then(scrollToBottom);
            state.pollTimer = setInterval(fetchMessages, 2500);

            // توفير موارد السيرفر المشترك: توقف عند تصغير المتصفح واستئناف فوري عند العودة
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    fetchMessages();
                }
            });

            /* إرسال رسالة جديدة (Optimistic UI) */
            if (chatForm) {
                chatForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const body = chatMessageInput ? chatMessageInput.value.trim() : '';
                    const hasFile = chatFileInput && chatFileInput.files && chatFileInput.files.length > 0;
                    const cardType = document.getElementById('cardTypeInput').value;

                    if (!body && !hasFile && !cardType) return;

                    const formData = new FormData(this);

                    // واجهة تفاؤلية سريعة (Optimistic UI)
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

                    // تفريغ المدخلات فوراً لتجربة سريعة
                    if (chatMessageInput) chatMessageInput.value = '';
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

            /* -------------------------------------------------------------------------
             | تفاعل مودال مشاركة بطاقة الابن
             | ------------------------------------------------------------------------- */
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
                                itemsStr += `<div>${k}: <strong>${v}</strong></div>`;
                            }
                        }
                        cardPreviewContent.innerHTML = `
                        <div class="rounded-lg bg-white p-2.5 border border-primary/10">
                            <div class="flex justify-between font-extrabold text-primary mb-1">
                                <span>${data.card.title}</span>
                                <span class="text-secondary">${data.card.badge || ''}</span>
                            </div>
                            <div class="text-[10px] text-primary/60">${itemsStr}</div>
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

            // تحديث المعاينة عند فتح المودال
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

                    showAttachmentPreview('📊 بطاقة جاهزة للمشاركة: ' + (state.attachedCard?.title || cardType));

                    // إغلاق المودال
                    const modal = document.getElementById('shareCardModal');
                    if (modal) modal.classList.remove('open');
                    document.body.classList.remove('modal-open');
                });
            }

        })();
    </script>
@endpush
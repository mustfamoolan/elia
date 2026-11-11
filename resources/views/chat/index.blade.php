@extends($layout)

@section('title', 'المحادثات والدعم')

@push('styles')
    <style>
        @media (max-width: 1023px) {
            body.wa-chat-page {
                overflow: hidden !important;
                height: 100vh;
                height: 100dvh;
            }
            body.wa-chat-page .bottom-nav,
            body.wa-chat-page footer {
                display: none !important;
            }
            body.wa-chat-page > div.min-h-screen {
                min-height: 0 !important;
                padding-bottom: 0 !important;
            }
            body.wa-chat-page main {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }

        .wa-shell {
            display: flex;
            flex-direction: column;
            width: 100%;
            height: var(--wa-mobile-h, calc(100dvh - 112px));
            background: #ffffff;
            overflow: hidden;
            position: relative;
        }

        @media (max-width: 1023px) {
            .wa-shell.wa-mobile-mode-list .wa-sidebar {
                display: flex !important;
                width: 100%;
                height: 100%;
            }
            .wa-shell.wa-mobile-mode-list .wa-main {
                display: none !important;
            }
            .wa-shell.wa-mobile-mode-chat .wa-sidebar {
                display: none !important;
            }
            .wa-shell.wa-mobile-mode-chat .wa-main {
                display: flex !important;
                width: 100%;
                height: 100%;
            }
        }

        @media (min-width: 1024px) {
            .wa-shell {
                display: grid !important;
                grid-template-columns: 350px minmax(0, 1fr);
                height: calc(100dvh - 10rem);
                min-height: 560px;
                max-height: 820px;
                border-radius: 1.25rem;
                border: 1px solid rgba(11, 58, 99, 0.12);
                box-shadow: 0 20px 50px -20px rgba(8, 24, 40, 0.22);
            }
            .wa-sidebar {
                display: flex !important;
                border-left: 1px solid #e2e8f0;
                height: 100%;
                overflow: hidden;
            }
            .wa-main {
                display: flex !important;
                height: 100%;
                overflow: hidden;
            }
            .wa-back-btn {
                display: none !important;
            }
        }

        .wa-sidebar {
            display: flex;
            flex-direction: column;
            background: #ffffff;
            min-height: 0;
        }

        .wa-sidebar-header {
            flex-shrink: 0;
            background: #008069;
            color: #ffffff;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .wa-header-avatar {
            position: relative;
            display: flex;
            height: 2.5rem;
            width: 2.5rem;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-size: 0.9rem;
            font-weight: 900;
        }

        .wa-header-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.24);
            padding: 0.38rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 800;
            color: #ffffff;
            cursor: pointer;
            transition: background 0.15s ease;
            white-space: nowrap;
        }

        .wa-header-btn:hover {
            background: rgba(255, 255, 255, 0.28);
        }

        .wa-back-btn {
            display: inline-flex;
            height: 2.25rem;
            width: 2.25rem;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            border: 0;
            cursor: pointer;
        }

        .wa-back-btn:hover {
            background: rgba(255, 255, 255, 0.24);
        }

        .wa-unread-pill {
            display: inline-flex;
            height: 1.25rem;
            min-width: 1.25rem;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #25d366;
            color: #ffffff;
            padding: 0 0.4rem;
            font-size: 0.68rem;
            font-weight: 900;
        }

        .wa-search-bar {
            flex-shrink: 0;
            background: #f0f2f5;
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #e9edef;
        }

        .wa-search-box {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 0.75rem;
            background: #ffffff;
            padding: 0.4rem 0.75rem;
            border: 1px solid #e2e8f0;
        }

        .wa-search-box input {
            width: 100%;
            border: 0;
            background: transparent;
            font-size: 0.78rem;
            font-weight: 600;
            color: #1e293b;
            outline: none;
        }

        .wa-conv-list {
            flex: 1 1 0%;
            min-height: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            background: #ffffff;
        }

        .wa-conv-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0.9rem;
            border-bottom: 1px solid #f0f2f5;
            transition: background 0.15s ease;
            text-decoration: none;
            color: #111b21;
        }

        .wa-conv-item:hover {
            background: #f5f6f6;
        }

        .wa-conv-item.is-active {
            background: #e9edef;
        }

        .wa-conv-avatar {
            position: relative;
            display: flex;
            height: 3rem;
            width: 3rem;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 900;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
        }

        .wa-avatar-admin { background: #e11d48; }
        .wa-avatar-staff { background: #4f46e5; }
        .wa-avatar-parent { background: #00a884; }

        .wa-online-dot {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 0.75rem;
            width: 0.75rem;
            border-radius: 999px;
            border: 2px solid #ffffff;
            background: #25d366;
        }

        .wa-student-tag {
            margin-top: 0.15rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.7rem;
            font-weight: 800;
            color: #008069;
        }

        .wa-main {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 0;
            background-color: #efeae2;
        }

        .wa-chat-header {
            flex-shrink: 0;
            background: #008069;
            color: #ffffff;
            padding: 0.6rem 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            box-shadow: 0 1px 3px rgba(11, 20, 26, 0.14);
            z-index: 10;
        }

        .wa-messages-viewport {
            flex: 1 1 0%;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            padding: 0.9rem 0.85rem;
            background-color: #efeae2;
            background-image:
                radial-gradient(rgba(17, 27, 33, 0.045) 1.2px, transparent 1.2px),
                radial-gradient(rgba(17, 27, 33, 0.035) 1.2px, #efeae2 1.2px);
            background-size: 24px 24px;
            background-position: 0 0, 12px 12px;
        }

        .wa-encryption-pill {
            margin: 0 auto 0.85rem;
            max-width: 28rem;
            border-radius: 0.65rem;
            background: #ffeecd;
            color: #54656f;
            padding: 0.4rem 0.75rem;
            font-size: 0.72rem;
            font-weight: 700;
            text-align: center;
            box-shadow: 0 1px 1px rgba(11, 20, 26, 0.08);
        }

        .wa-bubble-row {
            display: flex;
            width: 100%;
            margin-bottom: 0.5rem;
        }

        .wa-bubble-row.is-me {
            justify-content: flex-start;
        }

        .wa-bubble-row.is-other {
            justify-content: flex-end;
        }

        .wa-bubble {
            position: relative;
            max-width: min(85%, 30rem);
            min-width: 7rem;
            padding: 0.45rem 0.7rem 0.35rem;
            border-radius: 0.75rem;
            font-size: 0.84rem;
            line-height: 1.5;
            color: #111b21;
            box-shadow: 0 1px 1.5px rgba(11, 20, 26, 0.14);
            word-break: break-word;
        }

        .wa-bubble.is-me {
            background: #d9fdd3;
            border-top-right-radius: 0.15rem;
        }

        .wa-bubble.is-other {
            background: #ffffff;
            border-top-left-radius: 0.15rem;
        }

        .wa-msg-card {
            margin-bottom: 0.4rem;
            border-radius: 0.65rem;
            background: rgba(255, 255, 255, 0.96);
            padding: 0.6rem;
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-right: 4px solid #00a884;
        }

        .wa-bubble-meta {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.35rem;
            margin-top: 0.2rem;
            font-size: 0.68rem;
            color: #667781;
            line-height: 1;
        }

        .wa-delete-btn {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            border-radius: 0.35rem;
            padding: 0.15rem 0.35rem;
            font-size: 0.68rem;
            font-weight: 700;
            color: #e11d48;
            background: transparent;
            border: 0;
            cursor: pointer;
        }

        .wa-delete-btn:hover {
            background: rgba(225, 29, 72, 0.08);
        }

        .wa-ticks {
            color: #53bdeb;
            font-weight: 900;
            font-size: 0.72rem;
            letter-spacing: -1px;
        }

        .wa-preview-bar {
            flex-shrink: 0;
            border-top: 1px solid #e2e8f0;
            background: #e9edef;
            padding: 0.5rem 0.85rem;
            font-size: 0.75rem;
        }

        .wa-preview-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            border-radius: 0.75rem;
            background: #ffffff;
            padding: 0.5rem 0.75rem;
            border-right: 4px solid #00a884;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
        }

        .wa-composer {
            flex-shrink: 0;
            background: #f0f2f5;
            padding: 0.5rem 0.65rem calc(0.5rem + env(safe-area-inset-bottom, 0px));
            border-top: 1px solid rgba(17, 27, 33, 0.08);
            z-index: 15;
        }

        .wa-composer-form {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .wa-input-pill {
            flex: 1 1 0%;
            min-width: 0;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: #ffffff;
            border-radius: 999px;
            padding: 0.2rem 0.85rem;
            box-shadow: 0 1px 2px rgba(11, 20, 26, 0.08);
        }

        .wa-input-pill input {
            width: 100%;
            border: 0;
            outline: none;
            background: transparent;
            padding: 0.45rem 0;
            font-size: 0.92rem;
            font-weight: 600;
            color: #111b21;
        }

        .wa-icon-btn {
            height: 2.25rem;
            width: 2.25rem;
            flex-shrink: 0;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #54656f;
            background: transparent;
            border: 0;
            transition: background 0.15s ease, color 0.15s ease;
            cursor: pointer;
        }

        .wa-icon-btn:hover {
            background: rgba(17, 27, 33, 0.06);
            color: #111b21;
        }

        .wa-send-btn {
            height: 2.65rem;
            width: 2.65rem;
            flex-shrink: 0;
            border-radius: 999px;
            background: #00a884;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            box-shadow: 0 2px 6px rgba(0, 168, 132, 0.35);
            transition: transform 0.15s ease, background 0.15s ease;
            cursor: pointer;
        }

        .wa-send-btn:hover {
            background: #008f6f;
        }

        .wa-send-btn:active {
            transform: scale(0.94);
        }
    </style>
@endpush

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
                    <div class="wa-header-avatar">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h1 class="truncate text-sm font-extrabold" style="color: #fff;">المحادثات والدعم</h1>
                            @if ($totalUnread > 0)
                                <span class="wa-unread-pill">{{ $totalUnread }}</span>
                            @endif
                        </div>
                        <p class="truncate text-xs" style="color: rgba(255,255,255,0.82); font-size: 11px;">
                            {{ $conversations->count() }} محادثة نشطة
                        </p>
                    </div>
                </div>

                <button type="button" class="wa-header-btn" data-modal-open="newChatModal">
                    <x-icon name="plus" :size="14" />
                    <span>محادثة جديدة</span>
                </button>
            </div>

            {{-- شريط البحث السريع في المحادثات --}}
            <div class="wa-search-bar">
                <div class="wa-search-box">
                    <x-icon name="search" :size="15" class="text-primary/40 shrink-0" />
                    <input type="text" id="conversationSearchInput" placeholder="ابحث أو ابدأ دردشة جديدة...">
                </div>
            </div>

            {{-- قائمة الدردشات --}}
            <div class="wa-conv-list" id="conversationsList">
                @forelse ($conversations as $conv)
                    @php
                        $isActive = (int) $activeConversationId === (int) $conv['id'];
                        $roleBadgeTone = match ($conv['other_user_role']) {
                            'مدير' => 'chip-danger',
                            'مدرس' => 'chip-info',
                            default => 'chip-success',
                        };
                        $avatarClass = match ($conv['other_user_role']) {
                            'مدير' => 'wa-avatar-admin',
                            'مدرس' => 'wa-avatar-staff',
                            default => 'wa-avatar-parent',
                        };
                    @endphp
                    <a href="{{ route('chat.index', ['conversation_id' => $conv['id']]) }}"
                       data-conv-item
                       data-conv-id="{{ $conv['id'] }}"
                       data-conv-name="{{ $conv['other_user_name'].' '.($conv['student_name'] ?? '').' '.$conv['other_user_role'] }}"
                       class="wa-conv-item {{ $isActive ? 'is-active' : '' }}">
                        <div class="wa-conv-avatar {{ $avatarClass }}">
                            {{ mb_substr($conv['other_user_name'], 0, 1) }}
                            <span class="wa-online-dot"></span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <span class="truncate text-sm font-extrabold text-primary">{{ $conv['other_user_name'] }}</span>
                                    <span class="chip {{ $roleBadgeTone }} shrink-0">{{ $conv['other_user_role'] }}</span>
                                </div>
                                <span class="shrink-0 text-[11px] font-bold" style="color: {{ $conv['unread_count'] > 0 ? '#00a884' : '#64748b' }};">
                                    {{ $conv['last_message_time'] }}
                                </span>
                            </div>

                            @if (!empty($conv['student_name']))
                                <div class="wa-student-tag truncate">
                                    <x-icon name="graduation-cap" :size="12" />
                                    <span>الطالب: {{ $conv['student_name'] }}</span>
                                </div>
                            @endif

                            <div class="mt-1 flex items-center justify-between gap-2">
                                <p class="truncate text-xs text-primary/60">
                                    <span class="wa-ticks">✓✓</span>
                                    {{ $conv['last_message'] ?: 'اضغط لبدء المراسلة...' }}
                                </p>
                                @if ($conv['unread_count'] > 0)
                                    <span class="wa-unread-pill">{{ $conv['unread_count'] }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                        <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-primary/5 text-primary">
                            <x-icon name="message-square" :size="30" />
                        </div>
                        <p class="text-sm font-extrabold text-primary">لا توجد محادثات بعد</p>
                        <p class="mt-1 max-w-xs text-xs text-primary/60">ابدأ محادثة فورية مع إدارة المعهد أو الكادر التدريسي أو أولياء الأمور.</p>
                        <button type="button" class="btn btn-dark btn-sm mt-4" data-modal-open="newChatModal">
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
                        <button type="button" id="waBackToListBtn" class="wa-back-btn" aria-label="رجوع للمحادثات" title="رجوع لقائمة المحادثات">
                            <x-icon name="arrow-right" :size="20" />
                        </button>

                        <div class="wa-header-avatar">
                            {{ mb_substr($activeConv['other_user_name'] ?? 'م', 0, 1) }}
                            <span class="wa-online-dot"></span>
                        </div>

                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <h2 class="truncate text-sm font-extrabold" style="color: #fff;">
                                    {{ $activeConv['other_user_name'] ?? 'محادثة' }}
                                </h2>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" style="background: rgba(255,255,255,0.2); color: #fff;">
                                    {{ $activeConv['other_user_role'] ?? '' }}
                                </span>
                            </div>
                            <div class="mt-0.5 truncate text-[11px]" style="color: rgba(255,255,255,0.85);">
                                @if (!empty($activeConv['student_name']))
                                    <span>حول الطالب: <strong>{{ $activeConv['student_name'] }}</strong></span>
                                @else
                                    <span>متصل • قناة تواصل رسمية</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5">
                        @if ($myChildren && $myChildren->isNotEmpty())
                            <button type="button"
                                    class="wa-header-btn"
                                    data-modal-open="shareCardModal"
                                    title="مشاركة بطاقة درجات أو حضور أو واجب">
                                <x-icon name="clipboard-list" :size="14" />
                                <span class="hidden sm:inline">بطاقة طالب</span>
                            </button>
                        @endif
                        <button type="button"
                                class="wa-back-btn"
                                style="display: inline-flex;"
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

                    <div id="messagesLoading" class="py-10 text-center text-xs font-bold text-primary/60">
                        <span>⏳ جاري تحميل الرسائل...</span>
                    </div>

                    <div id="messagesContainer" class="hidden"></div>
                </div>

                {{-- 3. شريط معاينة المرفقات أو بطاقة الطالب قبل الإرسال --}}
                <div id="attachmentPreviewBar" class="wa-preview-bar hidden">
                    <div class="wa-preview-inner">
                        <div class="flex min-w-0 items-center gap-2">
                            <span id="previewIcon" class="text-base">📎</span>
                            <span id="previewText" class="truncate font-extrabold text-primary"></span>
                        </div>
                        <button type="button" id="cancelAttachmentBtn" class="chip chip-danger" style="cursor: pointer; border: 0;">
                            إلغاء ✕
                        </button>
                    </div>
                </div>

                {{-- 4. شريط الإرسال السفلي الثابت دائماً (WhatsApp Pinned Composer) --}}
                <div class="wa-composer">
                    <form id="chatForm" class="wa-composer-form" enctype="multipart/form-data">
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
                                <button type="button" class="wa-icon-btn" style="color: #008069;" data-modal-open="shareCardModal" title="إرفاق بطاقة طالب (درجات / حضور / واجبات)">
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
                    <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-white text-primary shadow-soft">
                        <x-icon name="message-square" :size="36" />
                    </div>
                    <h3 class="text-lg font-extrabold text-primary">محادثات معهد إيليا الفورية</h3>
                    <p class="mt-1 max-w-sm text-xs leading-relaxed text-primary/60">
                        اختر محادثة من القائمة أو ابدأ محادثة جديدة للتواصل المباشر ومشاركة بطاقات الدرجات والحضور والواجبات.
                    </p>
                    <button type="button" class="btn btn-dark mt-5" data-modal-open="newChatModal">
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
                    <x-icon name="clipboard-list" :size="18" class="text-secondary" /> مشاركة بطاقة طالب داخل المحادثة
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
                    contentHtml += `<div style="margin-bottom:4px;font-size:11px;font-weight:800;color:#008069;">${escapeHtml(msg.sender_name)} <span style="font-size:10px;font-weight:700;color:#64748b;">(${escapeHtml(msg.sender_role)})</span></div>`;
                }

                // بطاقة الطالب التفاعلية
                if (msg.has_card && msg.card) {
                    const card = msg.card;
                    const badgeToneClass = card.badge_tone === 'danger'
                        ? 'chip-danger'
                        : (card.badge_tone === 'warning' ? 'chip-warning' : 'chip-success');

                    let itemsHtml = '';
                    if (card.items) {
                        for (const [k, v] of Object.entries(card.items)) {
                            itemsHtml += `<div class="flex justify-between gap-3 py-1" style="border-bottom:1px solid #f1f5f9;"><span style="color:#64748b;">${escapeHtml(k)}:</span><strong style="color:#0f172a;">${escapeHtml(String(v))}</strong></div>`;
                        }
                    }

                    contentHtml += `
                        <div class="wa-msg-card">
                            <div class="flex items-center justify-between gap-2 pb-1.5 mb-1.5" style="border-bottom:1px solid #f1f5f9;">
                                <span class="text-xs font-extrabold text-primary">${escapeHtml(card.title || 'بطاقة طالب')}</span>
                                <span class="chip ${badgeToneClass}">${escapeHtml(card.badge || '')}</span>
                            </div>
                            <div style="font-size:11px;font-weight:800;color:#008069;margin-bottom:4px;">
                                الطالب: ${escapeHtml(card.student_name)} (${escapeHtml(card.stage_name)} • #${escapeHtml(String(card.student_no))})
                            </div>
                            <div style="font-size:11px;">
                                ${itemsHtml}
                            </div>
                        </div>
                    `;
                }

                if (msg.body) {
                    contentHtml += `<div style="font-size:13.5px;font-weight:600;line-height:1.55;white-space:pre-wrap;color:#111b21;">${escapeHtml(msg.body)}</div>`;
                }

                if (msg.attachment) {
                    const att = msg.attachment;
                    if (att.is_image) {
                        contentHtml += `
                            <div class="mt-1.5">
                                <a href="${att.url}" target="_blank" rel="noopener">
                                    <img src="${att.url}" alt="${escapeHtml(att.name)}" class="rounded-xl object-cover" style="max-height:220px;border:1px solid rgba(0,0,0,0.08);">
                                </a>
                            </div>
                        `;
                    } else {
                        contentHtml += `
                            <div class="mt-1.5 rounded-lg px-2.5 py-1.5" style="background:rgba(0,0,0,0.05);">
                                <a href="${att.url}" download="${escapeHtml(att.name)}" class="inline-flex items-center gap-1.5 text-xs font-extrabold" style="color:#008069;">
                                    <span>📄</span> <span>${escapeHtml(att.name)}</span>
                                </a>
                            </div>
                        `;
                    }
                }

                const canDelete = !msg.pending && (Boolean(msg.can_delete) || isMe || isAdminUser);
                const deleteBtnHtml = canDelete
                    ? `<button type="button" data-delete-msg="${msg.id}" class="wa-delete-btn" title="حذف الرسالة لدى الطرفين">🗑️ حذف</button>`
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
                        tempNode.style.opacity = '0.5';
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
                            <div class="wa-msg-card">
                                <div class="flex justify-between font-extrabold text-primary mb-1">
                                    <span>${escapeHtml(data.card.title)}</span>
                                    <span style="color:#008069;">${escapeHtml(data.card.badge || '')}</span>
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

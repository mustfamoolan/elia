@extends($layout)

@section('title', 'المحادثات والدعم')

@push('styles')
<style>
    @media (max-width: 1023px) {
        body.eyal-chat-page {
            overflow: hidden !important;
            height: 100vh;
            height: 100dvh;
        }
        body.eyal-chat-page .bottom-nav,
        body.eyal-chat-page footer {
            display: none !important;
        }
        body.eyal-chat-page > div.min-h-screen {
            min-height: 0 !important;
            padding-bottom: 0 !important;
        }
        body.eyal-chat-page main {
            max-width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }
    }

    .chat-app {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: var(--chat-mobile-h, calc(100dvh - 110px));
        background: #ffffff;
        overflow: hidden;
        position: relative;
    }

    @media (max-width: 1023px) {
        .chat-app.mode-list .chat-sidebar {
            display: flex !important;
            width: 100%;
            height: 100%;
        }
        .chat-app.mode-list .chat-stage {
            display: none !important;
        }
        .chat-app.mode-chat .chat-sidebar {
            display: none !important;
        }
        .chat-app.mode-chat .chat-stage {
            display: flex !important;
            width: 100%;
            height: 100%;
        }
    }

    @media (min-width: 1024px) {
        .chat-app {
            display: grid !important;
            grid-template-columns: 360px minmax(0, 1fr);
            height: calc(100dvh - 9.5rem);
            min-height: 580px;
            max-height: 840px;
            border-radius: 1.5rem;
            border: 1px solid rgba(11, 58, 99, 0.1);
            box-shadow: 0 24px 60px -24px rgba(11, 58, 99, 0.16);
        }
        .chat-sidebar {
            display: flex !important;
            border-left: 1px solid #eef2f6;
            height: 100%;
            overflow: hidden;
        }
        .chat-stage {
            display: flex !important;
            height: 100%;
            overflow: hidden;
        }
        .chat-mobile-back {
            display: none !important;
        }
    }

    .chat-sidebar {
        display: flex;
        flex-direction: column;
        background: #ffffff;
        min-height: 0;
    }

    .chat-sidebar-top {
        flex-shrink: 0;
        padding: 0.9rem 1rem;
        background: linear-gradient(135deg, #0b3a63 0%, #134e82 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .chat-top-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 0.75rem;
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.22);
        padding: 0.42rem 0.75rem;
        font-size: 0.74rem;
        font-weight: 800;
        color: #ffffff;
        cursor: pointer;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    .chat-top-btn:hover {
        background: rgba(255, 255, 255, 0.26);
    }

    .chat-search-wrap {
        flex-shrink: 0;
        padding: 0.65rem 0.85rem;
        background: #f8fafc;
        border-bottom: 1px solid #eef2f6;
    }

    .chat-search-input {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        padding: 0.45rem 0.75rem;
        transition: border-color 0.15s ease;
    }
    .chat-search-input:focus-within {
        border-color: #0b3a63;
    }
    .chat-search-input input {
        width: 100%;
        border: 0;
        outline: none;
        background: transparent;
        font-size: 0.78rem;
        font-weight: 700;
        color: #0f172a;
    }

    .chat-conv-list {
        flex: 1 1 0%;
        min-height: 0;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        background: #ffffff;
    }

    .chat-conv-card {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        cursor: pointer;
        transition: background 0.15s ease;
        text-decoration: none;
        color: #0f172a;
        position: relative;
    }
    .chat-conv-card:hover {
        background: #f8fafc;
    }
    .chat-conv-card.is-active {
        background: #f0f7ff;
        border-right: 4px solid #0b3a63;
    }

    .chat-avatar {
        position: relative;
        display: flex;
        height: 2.85rem;
        width: 2.85rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 0.95rem;
        font-size: 0.95rem;
        font-weight: 900;
        color: #ffffff;
    }
    .chat-avatar.role-admin { background: linear-gradient(135deg, #be123c, #e11d48); }
    .chat-avatar.role-staff { background: linear-gradient(135deg, #1d4ed8, #3b82f6); }
    .chat-avatar.role-parent { background: linear-gradient(135deg, #047857, #10b981); }

    .chat-status-dot {
        position: absolute;
        bottom: -2px;
        right: -2px;
        height: 0.78rem;
        width: 0.78rem;
        border-radius: 999px;
        border: 2px solid #ffffff;
        background: #94a3b8;
        transition: background 0.2s ease, box-shadow 0.2s ease;
    }
    .chat-status-dot.is-online {
        background: #10b981;
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.22);
    }
    .chat-status-dot.is-offline {
        background: #94a3b8;
    }

    .chat-header-main {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
        flex: 1 1 0%;
    }
    .chat-header-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
        flex: 1 1 0%;
        gap: 0.18rem;
    }
    .chat-header-title-row {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        min-width: 0;
    }
    .chat-header-sub-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.35rem;
        font-size: 0.7rem;
        font-weight: 700;
        line-height: 1.3;
    }
    .chat-presence-text {
        display: inline-flex;
        align-items: center;
        gap: 0.28rem;
        font-weight: 800;
        color: #64748b;
    }
    .chat-presence-text.is-online {
        color: #059669;
    }
    .chat-header-actions {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-shrink: 0;
    }

    .chat-unread-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.3rem;
        height: 1.3rem;
        padding: 0 0.38rem;
        border-radius: 999px;
        background: #0b3a63;
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 900;
    }

    .chat-stage {
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 0;
        background: #f4f7fb;
    }

    .chat-stage-header {
        flex-shrink: 0;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        padding: 0.7rem 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        z-index: 10;
    }

    .chat-mobile-back {
        display: inline-flex;
        height: 2.25rem;
        width: 2.25rem;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        background: #f1f5f9;
        color: #0b3a63;
        border: 0;
        cursor: pointer;
    }

    .chat-viewport {
        flex: 1 1 0%;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        padding: 1rem 1rem 0.75rem;
        background-color: #f4f7fb;
        background-image: radial-gradient(rgba(11, 58, 99, 0.05) 1px, transparent 1px);
        background-size: 22px 22px;
    }

    .chat-security-banner {
        margin: 0 auto 1rem;
        max-width: 30rem;
        border-radius: 0.85rem;
        background: #ffffff;
        border: 1px solid rgba(11, 58, 99, 0.08);
        color: #475569;
        padding: 0.45rem 0.85rem;
        font-size: 0.72rem;
        font-weight: 700;
        text-align: center;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    }

    .chat-date-divider {
        display: flex;
         justify-content: center;
        margin: 0.75rem 0;
    }
    .chat-date-divider span {
        background: rgba(15, 23, 42, 0.06);
        color: #475569;
        font-size: 0.68rem;
        font-weight: 800;
        padding: 0.2rem 0.75rem;
        border-radius: 999px;
    }

    .chat-msg-row {
        display: flex;
        width: 100%;
        margin-bottom: 0.55rem;
    }
    .chat-msg-row.is-me {
        justify-content: flex-start;
    }
    .chat-msg-row.is-other {
        justify-content: flex-end;
    }

    .chat-bubble {
        position: relative;
        max-width: min(85%, 31rem);
        min-width: 6.5rem;
        padding: 0.55rem 0.85rem 0.42rem;
        border-radius: 1.1rem;
        font-size: 0.84rem;
        line-height: 1.55;
        word-break: break-word;
        transition: opacity 0.15s ease;
    }
    .chat-bubble.is-me {
        background: linear-gradient(135deg, #0b3a63 0%, #155e95 100%);
        color: #ffffff;
        border-bottom-right-radius: 0.25rem;
        box-shadow: 0 4px 14px -4px rgba(11, 58, 99, 0.28);
    }
    .chat-bubble.is-other {
        background: #ffffff;
        color: #0f172a;
        border: 1px solid #e2e8f0;
        border-bottom-left-radius: 0.25rem;
        box-shadow: 0 2px 8px -2px rgba(15, 23, 42, 0.06);
    }

    .chat-student-card {
        margin-bottom: 0.45rem;
        border-radius: 0.75rem;
        background: #ffffff;
        color: #0f172a;
        padding: 0.65rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-right: 4px solid #1565c0;
    }

    .chat-meta {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.4rem;
        margin-top: 0.25rem;
        font-size: 0.66rem;
        line-height: 1;
        opacity: 0.82;
    }

    .chat-del-btn {
        margin-left: auto;
        display: inline-flex;
        align-items: center;
        gap: 0.2rem;
        border-radius: 0.35rem;
        padding: 0.12rem 0.35rem;
        font-size: 0.65rem;
        font-weight: 700;
        color: inherit;
        opacity: 0.75;
        background: rgba(0, 0, 0, 0.12);
        border: 0;
        cursor: pointer;
    }
    .chat-bubble.is-other .chat-del-btn {
        color: #e11d48;
        background: rgba(225, 29, 72, 0.08);
    }
    .chat-del-btn:hover {
        opacity: 1;
    }

    .chat-composer-bar {
        flex-shrink: 0;
        background: #ffffff;
        padding: 0.6rem 0.85rem calc(0.6rem + env(safe-area-inset-bottom, 0px));
        border-top: 1px solid #e2e8f0;
        z-index: 15;
    }

    .chat-composer-pill {
        flex: 1 1 0%;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 999px;
        padding: 0.22rem 0.85rem;
        transition: border-color 0.15s ease, background 0.15s ease;
    }
    .chat-composer-pill:focus-within {
        background: #ffffff;
        border-color: #0b3a63;
    }
    .chat-composer-pill input {
        width: 100%;
        border: 0;
        outline: none;
        background: transparent;
        padding: 0.45rem 0;
        font-size: 0.88rem;
        font-weight: 600;
        color: #0f172a;
    }

    .chat-tool-btn {
        height: 2.2rem;
        width: 2.2rem;
        flex-shrink: 0;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #475569;
        background: transparent;
        border: 0;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .chat-tool-btn:hover {
        background: #e2e8f0;
        color: #0b3a63;
    }

    .chat-send-btn {
        height: 2.65rem;
        width: 2.65rem;
        flex-shrink: 0;
        border-radius: 999px;
        background: linear-gradient(135deg, #0b3a63 0%, #1565c0 100%);
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        box-shadow: 0 4px 12px rgba(11, 58, 99, 0.28);
        cursor: pointer;
        transition: transform 0.15s ease;
    }
    .chat-send-btn:active {
        transform: scale(0.94);
    }
</style>
@endpush

@section('content')
    @php
        $activeConv = $activeConv ?? ($activeConversationId ? $conversations->firstWhere('id', $activeConversationId) : null);
        $mobileMode = (!empty($showListOnMobile) || !$activeConversationId) ? 'mode-list' : 'mode-chat';
        $totalUnread = $conversations->sum('unread_count');
    @endphp

    @if (!$chatEnabled && !$user->isAdmin())
        <div class="mx-2 my-2 rounded-2xl border border-amber-200 bg-amber-50 p-3 text-amber-800 lg:mx-0 lg:mb-3">
            <div class="flex items-center gap-2 text-xs font-extrabold">
                <x-icon name="info" :size="16" class="text-amber-600" /> نظام المحادثات متوقف مؤقتاً من قبل الإدارة (للقراءة فقط)
            </div>
        </div>
    @endif

    <div id="chatAppShell" class="chat-app {{ $mobileMode }}">
        {{-- القائمة الجانبية للمحادثات --}}
        <aside class="chat-sidebar">
            <div class="chat-sidebar-top">
                <div class="flex min-w-0 items-center gap-2.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 text-sm font-black text-white">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <h1 class="truncate text-sm font-extrabold text-white">المحادثات والدعم</h1>
                            <span id="sidebarTotalUnreadBadge" class="chat-unread-badge {{ $totalUnread > 0 ? '' : 'hidden' }}" style="background:#10b981;">
                                {{ $totalUnread }}
                            </span>
                        </div>
                        <p class="truncate text-[11px] text-white/75">
                            <span id="sidebarConvCount">{{ $conversations->count() }}</span> محادثة نشطة
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    @if ($user->isAdmin())
                        <button type="button" class="chat-top-btn" data-modal-open="chatAdminSettingsModal" title="التحكم بظهور المدرسين وقنوات الجات">
                            <x-icon name="settings" :size="14" />
                            <span class="hidden sm:inline">التحكم</span>
                        </button>
                    @endif
                    <button type="button" class="chat-top-btn" data-modal-open="newChatModal">
                        <x-icon name="plus" :size="14" />
                        <span>جديد</span>
                    </button>
                </div>
            </div>

            {{-- شريط البحث والفلترة السريعة --}}
            <div class="chat-search-wrap">
                <div class="chat-search-input">
                    <x-icon name="search" :size="15" class="shrink-0 text-primary/40" />
                    <input type="text" id="conversationSearchInput" placeholder="ابحث بالاسم أو اسم الطالب...">
                </div>
            </div>

            {{-- قائمة المحادثات (SPA State Managed) --}}
            <div class="chat-conv-list" id="conversationsList">
                @forelse ($conversations as $conv)
                    @php
                        $isActive = (int) $activeConversationId === (int) $conv['id'];
                        $roleBadgeTone = match ($conv['other_user_role']) {
                            'مدير' => 'bg-rose-50 text-rose-700',
                            'مدرس' => 'bg-blue-50 text-blue-700',
                            default => 'bg-emerald-50 text-emerald-700',
                        };
                        $avatarRoleClass = match ($conv['other_user_role']) {
                            'مدير' => 'role-admin',
                            'مدرس' => 'role-staff',
                            default => 'role-parent',
                        };
                    @endphp
                    <a href="{{ route('chat.index', ['conversation_id' => $conv['id']]) }}"
                       data-conv-item
                       data-conv-id="{{ $conv['id'] }}"
                       data-conv-meta="{{ json_encode($conv, JSON_UNESCAPED_UNICODE) }}"
                       data-conv-name="{{ $conv['other_user_name'].' '.($conv['student_name'] ?? '').' '.$conv['other_user_role'] }}"
                       class="chat-conv-card {{ $isActive ? 'is-active' : '' }}">
                        <div class="chat-avatar {{ $avatarRoleClass }}">
                            {{ mb_substr($conv['other_user_name'], 0, 1) }}
                            <span data-conv-dot class="chat-status-dot {{ !empty($conv['is_online']) ? 'is-online' : 'is-offline' }}"
                                  title="{{ $conv['presence_label'] ?? '' }}"></span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center" style="gap: 0.4rem;">
                                    <span class="truncate text-xs font-extrabold text-primary">{{ $conv['other_user_name'] }}</span>
                                    <span class="chip {{ $roleBadgeTone }} shrink-0 text-[10px]">{{ $conv['other_user_role'] }}</span>
                                </div>
                                <span data-conv-time class="shrink-0 text-[10px] font-bold text-primary/50">
                                    {{ $conv['last_message_time'] }}
                                </span>
                            </div>

                            <div class="mt-0.5 flex flex-wrap items-center" style="gap: 0.35rem;">
                                <span data-conv-presence class="text-[10px] font-bold {{ !empty($conv['is_online']) ? 'text-emerald-600' : 'text-primary/45' }}">
                                    {{ $conv['presence_label'] ?? 'غير متصل' }}
                                </span>
                                @if (!empty($conv['student_name']))
                                    <span class="text-[10px] text-primary/30">•</span>
                                    <span class="truncate text-[10px] font-bold text-blue-700">الطالب: {{ $conv['student_name'] }}</span>
                                @endif
                            </div>

                            <div class="mt-1 flex items-center justify-between gap-2">
                                <p data-conv-preview class="truncate text-[11px] text-primary/60">
                                    {{ $conv['last_message'] ?: 'اضغط لبدء المراسلة...' }}
                                </p>
                                <span data-conv-unread class="chat-unread-badge {{ $conv['unread_count'] > 0 ? '' : 'hidden' }}">
                                    {{ $conv['unread_count'] }}
                                </span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="flex h-full flex-col items-center justify-center p-8 text-center">
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/5 text-primary">
                            <x-icon name="message-square" :size="28" />
                        </div>
                        <p class="text-sm font-extrabold text-primary">لا توجد محادثات بعد</p>
                        <p class="mt-1 max-w-xs text-xs text-primary/60">ابدأ محادثة فورية مع إدارة المعهد أو الكادر التدريسي.</p>
                        <button type="button" class="btn btn-dark btn-sm mt-4" data-modal-open="newChatModal">
                            <x-icon name="plus" :size="15" /> بدء محادثة جديدة
                        </button>
                    </div>
                @endforelse
            </div>
        </aside>

        {{-- مساحة المحادثة النشطة (SPA Chat Stage) --}}
        <section class="chat-stage">
            <div id="chatStageActiveWrap" class="flex h-full flex-col {{ ($activeConversationId && $activeConv) ? '' : 'hidden' }}">
                {{-- الشريط العلوي للمحادثة --}}
                @php
                    $activeRoleClass = match ($activeConv['other_user_role'] ?? '') {
                        'مدير' => 'role-admin',
                        'مدرس' => 'role-staff',
                        default => 'role-parent',
                    };
                    $activeIsOnline = !empty($activeConv['is_online']);
                    $activePresenceLabel = $activeConv['presence_label'] ?? ($activeIsOnline ? 'متصل الآن' : 'غير متصل');
                @endphp
                <div class="chat-stage-header">
                    <div class="chat-header-main">
                        <button type="button" id="chatBackToListBtn" class="chat-mobile-back" aria-label="رجوع للمحادثات">
                            <x-icon name="arrow-right" :size="19" />
                        </button>

                        <div id="activeHeaderAvatar" class="chat-avatar {{ $activeRoleClass }}" style="height:2.65rem;width:2.65rem;font-size:0.9rem;">
                            <span id="activeHeaderAvatarChar">{{ mb_substr($activeConv['other_user_name'] ?? 'م', 0, 1) }}</span>
                            <span id="activeHeaderDot" class="chat-status-dot {{ $activeIsOnline ? 'is-online' : 'is-offline' }}"></span>
                        </div>

                        <div class="chat-header-text">
                            <div class="chat-header-title-row">
                                <h2 id="activeHeaderName" class="truncate text-sm font-extrabold text-primary">
                                    {{ $activeConv['other_user_name'] ?? 'محادثة' }}
                                </h2>
                                <span id="activeHeaderRole" class="chip shrink-0 bg-primary/5 text-[10px] text-primary">
                                    {{ $activeConv['other_user_role'] ?? '' }}
                                </span>
                            </div>
                            <div class="chat-header-sub-row">
                                <span id="activeHeaderPresence" class="chat-presence-text {{ $activeIsOnline ? 'is-online' : '' }}">
                                    {{ $activePresenceLabel }}
                                </span>
                                <span id="activeHeaderStudentSep" class="text-primary/30 {{ !empty($activeConv['student_name']) ? '' : 'hidden' }}">•</span>
                                <span id="activeHeaderStudent" class="truncate text-blue-700 {{ !empty($activeConv['student_name']) ? '' : 'hidden' }}">
                                    {{ !empty($activeConv['student_name']) ? ('الطالب: '.$activeConv['student_name']) : '' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="chat-header-actions">
                        @if ($myChildren && $myChildren->isNotEmpty())
                            <button type="button" class="btn btn-outline btn-sm" data-modal-open="shareCardModal" title="مشاركة بطاقة طالب">
                                <x-icon name="clipboard-list" :size="14" />
                                <span class="hidden sm:inline">بطاقة طالب</span>
                            </button>
                        @endif
                        <button type="button" class="btn btn-outline btn-sm" data-modal-open="newChatModal" title="محادثة جديدة">
                            <x-icon name="plus" :size="14" />
                        </button>
                    </div>
                </div>

                {{-- منطقة الرسائل --}}
                <div id="messagesArea" class="chat-viewport">
                    <div class="chat-security-banner">
                        🔒 قناة تواصل رسمية ومباشرة — يمكنك إرفاق الصور والملفات وبطاقات متابعة الطالب.
                    </div>

                    <div id="messagesLoading" class="hidden py-8 text-center text-xs font-bold text-primary/50">
                        جاري مزامنة الرسائل...
                    </div>

                    <div id="messagesContainer"></div>
                </div>

                {{-- شريط معاينة المرفقات أو بطاقة الطالب قبل الإرسال --}}
                <div id="attachmentPreviewBar" class="hidden border-t border-primary/10 bg-slate-100 px-3.5 py-2 text-xs">
                    <div class="flex items-center justify-between gap-2 rounded-xl border-r-4 border-r-primary bg-white px-3 py-2 shadow-sm">
                        <div class="flex min-w-0 items-center gap-2">
                            <span>📎</span>
                            <span id="previewText" class="truncate font-extrabold text-primary"></span>
                        </div>
                        <button type="button" id="cancelAttachmentBtn" class="chip bg-rose-50 text-rose-700" style="cursor:pointer;border:0;">
                            إلغاء ✕
                        </button>
                    </div>
                </div>

                {{-- شريط الإرسال السفلي الثابت --}}
                <div class="chat-composer-bar">
                    <form id="chatForm" class="flex items-center gap-2" enctype="multipart/form-data">
                        @csrf
                        <input type="file" id="chatFileInput" name="file" class="hidden"
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                        <input type="hidden" id="cardTypeInput" name="card_type" value="">
                        <input type="hidden" id="studentIdInput" name="student_id"
                               value="{{ $activeConv['student_id'] ?? '' }}">
                        <input type="hidden" id="itemIdInput" name="item_id" value="">

                        <div class="chat-composer-pill">
                            <button type="button" id="triggerFileBtn" class="chat-tool-btn" title="إرفاق صورة أو ملف">
                                <x-icon name="paperclip" :size="18" />
                            </button>

                            @if ($myChildren && $myChildren->isNotEmpty())
                                <button type="button" class="chat-tool-btn text-primary" data-modal-open="shareCardModal" title="إرفاق بطاقة طالب">
                                    <x-icon name="clipboard-list" :size="18" />
                                </button>
                            @endif

                            <input type="text"
                                   id="chatMessageInput"
                                   name="body"
                                   placeholder="اكتب رسالتك هنا..."
                                   enterkeyhint="send"
                                   autocomplete="off">
                        </div>

                        <button type="submit" id="chatSubmitBtn" class="chat-send-btn" aria-label="إرسال">
                            <x-icon name="send" :size="17" />
                        </button>
                    </form>
                </div>
            </div>

            {{-- حالة عدم وجود محادثة مختارة --}}
            <div id="chatStageEmptyWrap" class="flex h-full flex-col items-center justify-center p-8 text-center {{ ($activeConversationId && $activeConv) ? 'hidden' : '' }}">
                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-3xl bg-white text-primary shadow-soft">
                    <x-icon name="message-square" :size="36" />
                </div>
                <h3 class="text-lg font-extrabold text-primary">نظام المحادثات والدعم الفوري</h3>
                <p class="mt-1 max-w-sm text-xs leading-relaxed text-primary/60">
                    اختر محادثة من القائمة الجانبية أو ابدأ محادثة جديدة للتواصل الفوري ومشاركة بطاقات الدرجات والحضور والواجبات.
                </p>
                <button type="button" class="btn btn-dark mt-5" data-modal-open="newChatModal">
                    <x-icon name="plus" :size="15" /> بدء محادثة جديدة
                </button>
            </div>
        </section>
    </div>

    {{-- نافذة التحكم بظهور المدرسين وقنوات الجات (للإدارة فقط) --}}
    @if ($user->isAdmin())
        <div class="modal-overlay" id="chatAdminSettingsModal">
            <div class="modal-panel modal-md">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-base font-extrabold text-primary">
                        <x-icon name="settings" :size="18" class="text-primary" /> التحكم بقنوات المحادثات وظهور المدرسين
                    </h3>
                    <button type="button" class="rounded-full p-2 hover:bg-primary/5" data-modal-close aria-label="إغلاق">
                        <x-icon name="x" :size="18" class="text-primary" />
                    </button>
                </div>

                <div class="space-y-2.5">
                    <div class="flex items-center gap-3 rounded-xl border border-primary/10 bg-primary/[0.02] p-3.5">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">إظهار المدرسين لأولياء الأمور في الجات</div>
                            <div class="mt-0.5 text-[11px] leading-relaxed text-primary/60">
                                عند الإيقاف: يظهر لولي الأمر <strong>شات الإدارة فقط</strong> ويختفي المدرسون تماماً من الجات لديهم. وعند التفعيل: يظهر لهم مدرسو مواد أبنائهم أيضاً.
                            </div>
                        </div>
                        <button class="toggle shrink-0" aria-checked="{{ ($institute['chat_parent_teacher_enabled'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_parent_teacher_enabled']) }}"
                                data-toggle-toast="تم تحديث ظهور المدرسين لأولياء الأمور في الجات"></button>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-primary/10 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">شات أولياء الأمور مع الإدارة</div>
                            <div class="text-[10px] text-primary/55">السماح لأولياء الأمور بمراسلة إدارة المعهد (الدعم الفني والتربوي)</div>
                        </div>
                        <button class="toggle shrink-0" aria-checked="{{ ($institute['chat_parent_admin_enabled'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_parent_admin_enabled']) }}"
                                data-toggle-toast="تم تحديث شات أولياء الأمور مع الإدارة"></button>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-primary/10 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">شات الكادر التدريسي مع الإدارة</div>
                            <div class="text-[10px] text-primary/55">التواصل المباشر بين المدرسين وإدارة المعهد</div>
                        </div>
                        <button class="toggle shrink-0" aria-checked="{{ ($institute['chat_staff_admin_enabled'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_staff_admin_enabled']) }}"
                                data-toggle-toast="تم تحديث شات الكادر مع الإدارة"></button>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-primary/10 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">كروب أولياء الأمور والإدارة</div>
                            <div class="text-[10px] text-primary/55">إظهار أو إخفاء مجموعة الدردشة العامة الخاصة بأولياء الأمور فقط (معزولة تماماً عن المدرسين)</div>
                        </div>
                        <button class="toggle shrink-0" aria-checked="{{ ($institute['chat_parents_group_enabled'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_parents_group_enabled']) }}"
                                data-toggle-toast="تم تحديث ظهور كروب أولياء الأمور"></button>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-primary/10 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">كروب الكادر التدريسي والإدارة</div>
                            <div class="text-[10px] text-primary/55">إظهار أو إخفاء مجموعة الدردشة الخاصة بالمدرسين فقط (معزولة تماماً عن أولياء الأمور)</div>
                        </div>
                        <button class="toggle shrink-0" aria-checked="{{ ($institute['chat_staff_group_enabled'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_staff_group_enabled']) }}"
                                data-toggle-toast="تم تحديث ظهور كروب الكادر التدريسي"></button>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl border border-primary/10 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="text-xs font-extrabold text-primary">تشغيل نظام المحادثات العام</div>
                            <div class="text-[10px] text-primary/55">فتح أو إيقاف إرسال الرسائل في الموقع بالكامل</div>
                        </div>
                        <button class="toggle shrink-0" aria-checked="{{ ($institute['chat_enabled'] ?? true) ? 'true' : 'false' }}"
                                data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.chat_enabled']) }}"
                                data-toggle-toast="تم تحديث حالة نظام المحادثات"></button>
                    </div>

                    <div class="mt-3 rounded-xl border border-blue-500/20 bg-blue-50/40 p-3.5">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-extrabold text-primary">✨ المساعد الذكي (Gemini AI) في الجات</span>
                            <a href="{{ route('admin.settings') }}" class="text-[10px] font-bold text-blue-700 underline">إعداد مفتاح API</a>
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-primary/80">تقسيم وتوزيع الواجبات من رسائل الأهالي تلقائياً</span>
                                <button class="toggle shrink-0" aria-checked="{{ ($institute['ai_auto_homework'] ?? true) ? 'true' : 'false' }}"
                                        data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.ai_auto_homework']) }}"
                                        data-toggle-toast="تم تحديث تقسيم الواجبات الذكي"></button>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-primary/80">الرد الذكي التلقائي على الأهالي بدلاً من الكادر</span>
                                <button class="toggle shrink-0" aria-checked="{{ ($institute['ai_auto_reply'] ?? true) ? 'true' : 'false' }}"
                                        data-toggle-url="{{ route('admin.settings.toggle', ['key' => 'features.ai_auto_reply']) }}"
                                        data-toggle-toast="تم تحديث الرد التلقائي الذكي"></button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="button" class="btn btn-dark btn-sm" data-modal-close>تم</button>
                </div>
            </div>
        </div>
    @endif

    {{-- نافذة بدء محادثة جديدة --}}
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
                    </div>
                @endif

                <div>
                    <label class="label">الرسالة الأولى (اختياري)</label>
                    <textarea name="initial_message" class="input min-h-[80px]"
                              placeholder="اكتب استفسارك أو رسالتك هنا..."></textarea>
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="btn btn-dark flex-1">
                        <x-icon name="send" :size="15" /> فتح المحادثة
                    </button>
                    <button type="button" class="btn btn-ghost" data-modal-close>إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    {{-- نافذة مشاركة بطاقة بيانات الطالب --}}
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

    {{-- نافذة تكبير الصور المرفقة في الشات --}}
    <div class="modal-overlay" id="chatLightboxModal" onclick="if(event.target===this) closeChatLightbox()">
        <div class="modal-panel modal-lg" style="max-width: 54rem;">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h3 id="chatLightboxTitle" class="truncate text-sm font-extrabold text-primary">معاينة الصورة</h3>
                <div class="flex items-center gap-2">
                    <a id="chatLightboxDownload" href="#" class="btn btn-outline btn-sm">
                        <x-icon name="download" :size="14" /> تحميل
                    </a>
                    <button type="button" class="rounded-full p-2 hover:bg-primary/5" onclick="closeChatLightbox()" aria-label="إغلاق">
                        <x-icon name="x" :size="20" class="text-primary" />
                    </button>
                </div>
            </div>
            <div class="flex max-h-[75vh] items-center justify-center overflow-auto rounded-2xl bg-slate-950/5 p-2">
                <img id="chatLightboxImg" src="" alt="صورة" class="max-h-[72vh] w-auto max-w-full rounded-xl object-contain">
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    window.__EYAL_CHAT_INIT__ = {
        activeConvId: {{ $activeConversationId ? (int) $activeConversationId : 'null' }},
        activeConv: {!! json_encode($activeConv, JSON_UNESCAPED_UNICODE) !!},
        initialMessages: {!! json_encode($initialMessages ?? [], JSON_UNESCAPED_UNICODE) !!},
        initialFp: {!! json_encode($initialFingerprint ?? ['last_id' => 0, 'del_v' => 1], JSON_UNESCAPED_UNICODE) !!},
        isAdmin: {{ $user->isAdmin() ? 'true' : 'false' }},
        baseUrl: "{{ url('/chat/conversations') }}",
        deleteBaseUrl: "{{ url('/chat/messages') }}",
        chatIndexUrl: "{{ route('chat.index') }}",
        previewCardUrl: "{{ route('chat.preview_card') }}",
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}'
    };

    function openChatLightbox(src, title, dl) {
        var modal = document.getElementById('chatLightboxModal');
        var img = document.getElementById('chatLightboxImg');
        var titleEl = document.getElementById('chatLightboxTitle');
        var dlEl = document.getElementById('chatLightboxDownload');
        if (!modal || !img) return;
        img.src = src;
        if (titleEl) titleEl.textContent = title || 'معاينة الصورة';
        if (dlEl) dlEl.href = dl || src;
        modal.classList.add('open');
    }

    function closeChatLightbox() {
        var modal = document.getElementById('chatLightboxModal');
        var img = document.getElementById('chatLightboxImg');
        if (modal) modal.classList.remove('open');
        if (img) img.src = '';
    }

    (function () {
        'use strict';

        document.body.classList.add('eyal-chat-page');

        const cfg = window.__EYAL_CHAT_INIT__;
        const shell = document.getElementById('chatAppShell');
        const backBtn = document.getElementById('chatBackToListBtn');
        const activeWrap = document.getElementById('chatStageActiveWrap');
        const emptyWrap = document.getElementById('chatStageEmptyWrap');
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

        /* --------------------------- SPA State Store --------------------------- */
        const store = {
            activeConvId: cfg.activeConvId,
            conversations: {}, // convId -> { messages: [], lastId: 0, delV: 1 }
            isPolling: false,
            pollTimer: null,
            idleTicks: 0,
            attachedCard: null,
            tempSelectedCard: null,
        };

        // تهيئة المحادثة النشطة فوراً (Zero-Latency Hydration)
        if (cfg.activeConvId && Array.isArray(cfg.initialMessages)) {
            const maxId = cfg.initialMessages.reduce((m, x) => Math.max(m, Number(x.id) || 0), cfg.initialFp.last_id || 0);
            store.conversations[cfg.activeConvId] = {
                messages: cfg.initialMessages,
                lastId: maxId,
                delV: cfg.initialFp.del_v || 1,
            };
        }

        /* ضبط ارتفاع الشاشة للموبايل ولوحة المفاتيح */
        function updateMobileViewport() {
            if (!shell) return;
            if (window.innerWidth >= 1024) {
                shell.style.removeProperty('--chat-mobile-h');
                return;
            }
            const header = document.querySelector('body > div > header');
            const headerH = header ? header.getBoundingClientRect().height : 0;
            const vh = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            shell.style.setProperty('--chat-mobile-h', Math.max(320, Math.floor(vh - headerH)) + 'px');
        }

        updateMobileViewport();
        window.addEventListener('resize', updateMobileViewport);
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', () => {
                updateMobileViewport();
                scrollToBottom();
            });
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

        /* بناء فقاعة الرسالة */
        function createMessageElement(msg) {
            const isMe = Boolean(msg.is_me);
            const row = document.createElement('div');
            row.className = 'chat-msg-row ' + (isMe ? 'is-me' : 'is-other');
            row.setAttribute('data-msg-id', msg.id);

            let contentHtml = '';

            if (!isMe) {
                contentHtml += `<div style="margin-bottom:3px;font-size:11px;font-weight:800;color:#1565c0;">${escapeHtml(msg.sender_name)} <span style="font-size:10px;font-weight:700;color:#64748b;">(${escapeHtml(msg.sender_role)})</span></div>`;
            }

            if (msg.has_card && msg.card) {
                const card = msg.card;
                let itemsHtml = '';
                if (card.items) {
                    for (const [k, v] of Object.entries(card.items)) {
                        itemsHtml += `<div class="flex justify-between gap-3 py-1" style="border-bottom:1px solid #f1f5f9;"><span style="color:#64748b;">${escapeHtml(k)}:</span><strong style="color:#0f172a;">${escapeHtml(String(v))}</strong></div>`;
                    }
                }

                contentHtml += `
                    <div class="chat-student-card">
                        <div class="flex items-center justify-between gap-2 pb-1.5 mb-1.5" style="border-bottom:1px solid #f1f5f9;">
                            <span class="text-xs font-extrabold text-primary">${escapeHtml(card.title || 'بطاقة طالب')}</span>
                            <span class="chip bg-blue-50 text-blue-700 text-[10px]">${escapeHtml(card.badge || '')}</span>
                        </div>
                        <div style="font-size:11px;font-weight:800;color:#0b3a63;margin-bottom:4px;">
                            الطالب: ${escapeHtml(card.student_name)} (${escapeHtml(card.stage_name)} • #${escapeHtml(String(card.student_no))})
                        </div>
                        <div style="font-size:11px;">${itemsHtml}</div>
                    </div>
                `;
            }

            if (msg.body) {
                contentHtml += `<div style="font-size:13.5px;font-weight:600;line-height:1.55;white-space:pre-wrap;">${escapeHtml(msg.body)}</div>`;
            }

            if (msg.attachment) {
                const att = msg.attachment;
                if (att.is_image) {
                    const dlUrl = att.download_url || att.url;
                    contentHtml += `
                        <div class="mt-2">
                            <button type="button" class="block overflow-hidden rounded-xl border border-white/20 bg-black/5 cursor-pointer"
                                    data-src="${escapeHtml(att.url)}" data-title="${escapeHtml(att.name)}" data-dl="${escapeHtml(dlUrl)}"
                                    onclick="openChatLightbox(this.dataset.src, this.dataset.title, this.dataset.dl)">
                                <img src="${escapeHtml(att.url)}" alt="${escapeHtml(att.name)}" class="max-h-60 w-auto object-contain" loading="lazy">
                            </button>
                        </div>
                    `;
                } else {
                    const dlUrl = att.download_url || att.url;
                    contentHtml += `
                        <div class="mt-2 rounded-lg px-3 py-2" style="background:rgba(255,255,255,0.16);border:1px solid rgba(255,255,255,0.2);">
                            <a href="${escapeHtml(dlUrl)}" class="inline-flex items-center gap-1.5 text-xs font-extrabold" style="color:inherit;text-decoration:underline;">
                                <span>📄</span> <span>${escapeHtml(att.name)}</span>
                            </a>
                        </div>
                    `;
                }
            }

            const canDelete = !msg.pending && (Boolean(msg.can_delete) || isMe || cfg.isAdmin);
            const deleteBtnHtml = canDelete
                ? `<button type="button" data-delete-msg="${msg.id}" class="chat-del-btn" title="حذف الرسالة لدى الطرفين">حذف</button>`
                : '';

            const canAiProcess = !msg.pending && !msg.has_card && (cfg.isAdmin || !isMe);
            const aiBtnHtml = canAiProcess
                ? `<button type="button" data-ai-msg="${msg.id}" class="chat-del-btn" style="background:rgba(37,99,235,0.12);color:${isMe ? '#fff' : '#1d4ed8'};" title="تحليل الرسالة بالذكاء الاصطناعي (تقسيم الواجب أو الرد التلقائي)">✨ AI</button>`
                : '';

            row.innerHTML = `
                <div class="chat-bubble ${isMe ? 'is-me' : 'is-other'}">
                    ${contentHtml}
                    <div class="chat-meta">
                        ${deleteBtnHtml}
                        ${aiBtnHtml}
                        <span>${escapeHtml(msg.time || '')}</span>
                        ${isMe ? (msg.pending ? '<span>⏳</span>' : '<span>✓✓</span>') : ''}
                    </div>
                </div>
            `;

            const delBtn = row.querySelector('[data-delete-msg]');
            if (delBtn) {
                delBtn.addEventListener('click', () => deleteMessageForBoth(msg.id, row));
            }

            const aiBtn = row.querySelector('[data-ai-msg]');
            if (aiBtn) {
                aiBtn.addEventListener('click', () => triggerAiOnMessage(msg.id, aiBtn));
            }

            return row;
        }

        function renderConversationMessages(convId) {
            if (!messagesContainer) return;
            messagesContainer.innerHTML = '';
            const entry = store.conversations[convId];
            if (!entry || !Array.isArray(entry.messages)) return;

            const frag = document.createDocumentFragment();
            let lastDate = null;

            entry.messages.forEach((msg) => {
                if (msg.date && msg.date !== lastDate) {
                    lastDate = msg.date;
                    const div = document.createElement('div');
                    div.className = 'chat-date-divider';
                    div.innerHTML = `<span>${escapeHtml(msg.date)}</span>`;
                    frag.appendChild(div);
                }
                frag.appendChild(createMessageElement(msg));
            });

            messagesContainer.appendChild(frag);
            scrollToBottom();
        }

        function applyHeaderPresence(isOnline, label) {
            const dotEl = document.getElementById('activeHeaderDot');
            const presEl = document.getElementById('activeHeaderPresence');
            if (dotEl) {
                dotEl.classList.toggle('is-online', Boolean(isOnline));
                dotEl.classList.toggle('is-offline', !isOnline);
            }
            if (presEl) {
                presEl.textContent = label || (isOnline ? 'متصل الآن' : 'غير متصل');
                presEl.classList.toggle('is-online', Boolean(isOnline));
            }
        }

        function updateActiveHeader(convMeta) {
            if (!convMeta) return;
            const nameEl = document.getElementById('activeHeaderName');
            const roleEl = document.getElementById('activeHeaderRole');
            const charEl = document.getElementById('activeHeaderAvatarChar');
            const avatarEl = document.getElementById('activeHeaderAvatar');
            const studentSep = document.getElementById('activeHeaderStudentSep');
            const studentEl = document.getElementById('activeHeaderStudent');
            const studentInput = document.getElementById('studentIdInput');

            if (nameEl) nameEl.textContent = convMeta.other_user_name || 'محادثة';
            if (roleEl) roleEl.textContent = convMeta.other_user_role || '';
            if (charEl) charEl.textContent = (convMeta.other_user_name || 'م').substring(0, 1);

            applyHeaderPresence(Boolean(convMeta.is_online), convMeta.presence_label || '');

            if (studentEl && studentSep) {
                const hasStudent = Boolean(convMeta.student_name);
                studentEl.textContent = hasStudent ? ('الطالب: ' + convMeta.student_name) : '';
                studentEl.classList.toggle('hidden', !hasStudent);
                studentSep.classList.toggle('hidden', !hasStudent);
            }

            if (avatarEl) {
                avatarEl.className = 'chat-avatar ' + (
                    convMeta.other_user_role === 'مدير' ? 'role-admin'
                        : (convMeta.other_user_role === 'مدرس' ? 'role-staff' : 'role-parent')
                );
            }
            if (studentInput) {
                studentInput.value = convMeta.student_id || '';
            }
        }

        /* التبديل الفوري بين المحادثات بدون إعادة تحميل الصفحة (SPA Instant Switch) */
        async function switchConversation(convId, convMeta) {
            convId = Number(convId);
            if (!convId) return;

            store.activeConvId = convId;
            store.idleTicks = 0;

            if (activeWrap) activeWrap.classList.remove('hidden');
            if (emptyWrap) emptyWrap.classList.add('hidden');

            document.querySelectorAll('[data-conv-item]').forEach((item) => {
                const id = Number(item.getAttribute('data-conv-id'));
                item.classList.toggle('is-active', id === convId);
                if (id === convId) {
                    const badge = item.querySelector('[data-conv-unread]');
                    if (badge) {
                        badge.textContent = '0';
                        badge.classList.add('hidden');
                    }
                }
            });

            if (convMeta) {
                updateActiveHeader(convMeta);
            }

            if (shell && window.innerWidth < 1024) {
                shell.classList.remove('mode-list');
                shell.classList.add('mode-chat');
            }

            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', cfg.chatIndexUrl + '?conversation_id=' + convId);
            }

            if (store.conversations[convId]) {
                if (messagesLoading) messagesLoading.classList.add('hidden');
                renderConversationMessages(convId);
                await pollActiveConversation(false);
            } else {
                if (messagesContainer) messagesContainer.innerHTML = '';
                if (messagesLoading) messagesLoading.classList.remove('hidden');
                store.conversations[convId] = { messages: [], lastId: 0, delV: 0 };
                await pollActiveConversation(true);
            }
        }

        document.querySelectorAll('[data-conv-item]').forEach((item) => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const convId = Number(this.getAttribute('data-conv-id'));
                let meta = null;
                try { meta = JSON.parse(this.getAttribute('data-conv-meta') || '{}'); } catch (err) { meta = null; }
                switchConversation(convId, meta);
            });
        });

        if (backBtn && shell) {
            backBtn.addEventListener('click', function () {
                shell.classList.remove('mode-chat');
                shell.classList.add('mode-list');
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', cfg.chatIndexUrl + '?list=1');
                }
            });
        }

        /* تحديث الشريط الجانبي حياً في الخلفية */
        function syncSidebarConversations(conversationsList) {
            if (!Array.isArray(conversationsList)) return;
            let totalUnread = 0;

            conversationsList.forEach((c) => {
                totalUnread += Number(c.unread_count || 0);
                const el = document.querySelector(`[data-conv-item][data-conv-id="${c.id}"]`);
                if (!el) return;

                el.setAttribute('data-conv-meta', JSON.stringify(c));
                const dotEl = el.querySelector('[data-conv-dot]');
                if (dotEl) {
                    dotEl.classList.toggle('is-online', Boolean(c.is_online));
                    dotEl.classList.toggle('is-offline', !c.is_online);
                    dotEl.setAttribute('title', c.presence_label || '');
                }
                const presEl = el.querySelector('[data-conv-presence]');
                if (presEl && c.presence_label) {
                    presEl.textContent = c.presence_label;
                    presEl.className = 'text-[10px] font-bold ' + (c.is_online ? 'text-emerald-600' : 'text-primary/45');
                }
                const previewEl = el.querySelector('[data-conv-preview]');
                if (previewEl && c.last_message) {
                    previewEl.textContent = c.last_message;
                }
                const timeEl = el.querySelector('[data-conv-time]');
                if (timeEl && c.last_message_time) {
                    timeEl.textContent = c.last_message_time;
                }
                const unreadEl = el.querySelector('[data-conv-unread]');
                if (unreadEl) {
                    const count = Number(c.id) === Number(store.activeConvId) ? 0 : Number(c.unread_count || 0);
                    unreadEl.textContent = String(count);
                    unreadEl.classList.toggle('hidden', count <= 0);
                }
            });

            const totalBadge = document.getElementById('sidebarTotalUnreadBadge');
            if (totalBadge) {
                totalBadge.textContent = String(totalUnread);
                totalBadge.classList.toggle('hidden', totalUnread <= 0);
            }
        }

        /* مزامنة الرسائل مع الكاش الذكي (Smart Fingerprint Delta Poll) */
        async function pollActiveConversation(forceFull = false) {
            const convId = store.activeConvId;
            if (!convId || store.isPolling || document.visibilityState === 'hidden') return;

            store.isPolling = true;
            const entry = store.conversations[convId] || (store.conversations[convId] = { messages: [], lastId: 0, delV: 0 });
            const afterId = forceFull ? 0 : entry.lastId;
            const includeSidebar = (store.idleTicks % 3 === 0) ? 1 : 0;

            try {
                const url = `${cfg.baseUrl}/${convId}/poll?after=${afterId}&del_v=${entry.delV}&sidebar=${includeSidebar}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Poll failed');
                const data = await res.json();

                if (data.conversations) {
                    syncSidebarConversations(data.conversations);
                }

                if (Number(store.activeConvId) !== Number(convId)) return;

                if (data.other_presence) {
                    applyHeaderPresence(Boolean(data.other_presence.is_online), data.other_presence.label);
                    const activeSidebarItem = document.querySelector(`[data-conv-item][data-conv-id="${convId}"]`);
                    if (activeSidebarItem) {
                        const dotEl = activeSidebarItem.querySelector('[data-conv-dot]');
                        if (dotEl) {
                            dotEl.classList.toggle('is-online', Boolean(data.other_presence.is_online));
                            dotEl.classList.toggle('is-offline', !data.other_presence.is_online);
                        }
                        const presEl = activeSidebarItem.querySelector('[data-conv-presence]');
                        if (presEl) {
                            presEl.textContent = data.other_presence.label || '';
                            presEl.className = 'text-[10px] font-bold ' + (data.other_presence.is_online ? 'text-emerald-600' : 'text-primary/45');
                        }
                    }
                }

                if (data.del_v) {
                    entry.delV = Number(data.del_v);
                }

                // معالجة الرسائل المحذوفة عند تغير del_v
                if (Array.isArray(data.existing_ids) && messagesContainer) {
                    const existingSet = new Set(data.existing_ids.map(Number));
                    entry.messages = entry.messages.filter((m) => existingSet.has(Number(m.id)));
                    messagesContainer.querySelectorAll('[data-msg-id]').forEach((node) => {
                        const rawId = node.getAttribute('data-msg-id') || '';
                        if (!rawId.startsWith('temp_') && !existingSet.has(Number(rawId))) {
                            node.remove();
                        }
                    });
                }

                if (afterId === 0 && Array.isArray(data.messages)) {
                    entry.messages = data.messages;
                    entry.lastId = data.messages.reduce((m, x) => Math.max(m, Number(x.id) || 0), Number(data.last_id) || 0);
                    renderConversationMessages(convId);
                } else if (Array.isArray(data.messages) && data.messages.length > 0) {
                    store.idleTicks = 0;
                    const isNearBottom = messagesArea
                        ? (messagesArea.scrollHeight - messagesArea.scrollTop - messagesArea.clientHeight < 150)
                        : true;

                    data.messages.forEach((msg) => {
                        if (!document.querySelector(`[data-msg-id="${msg.id}"]`)) {
                            entry.messages.push(msg);
                            entry.lastId = Math.max(entry.lastId, Number(msg.id) || 0);
                            messagesContainer.appendChild(createMessageElement(msg));
                        }
                    });

                    if (isNearBottom) scrollToBottom();
                } else {
                    store.idleTicks++;
                }

                if (messagesLoading) messagesLoading.classList.add('hidden');
            } catch (e) {
                // ignore transient network errors
            } finally {
                store.isPolling = false;
            }
        }

        async function deleteMessageForBoth(msgId, rowElement) {
            if (!msgId || String(msgId).startsWith('temp_')) return;
            if (!window.confirm('هل تريد حذف هذه الرسالة نهائياً من الطرفين؟')) return;

            if (rowElement) {
                rowElement.style.opacity = '0.4';
                rowElement.style.pointerEvents = 'none';
            }

            try {
                const res = await fetch(`${cfg.deleteBaseUrl}/${msgId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': cfg.csrfToken,
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) throw new Error('Delete failed');
                const data = await res.json();

                if (rowElement) rowElement.remove();
                const entry = store.conversations[store.activeConvId];
                if (entry) {
                    entry.messages = entry.messages.filter((m) => Number(m.id) !== Number(msgId));
                    if (data.del_v) entry.delV = Number(data.del_v);
                }
                if (window.eyalToast) window.eyalToast('تم حذف الرسالة من الطرفين', 'success');
            } catch (err) {
                if (rowElement) {
                    rowElement.style.opacity = '1';
                    rowElement.style.pointerEvents = 'auto';
                }
                if (window.eyalToast) window.eyalToast('تعذر حذف الرسالة', 'danger');
            }
        }

        async function triggerAiOnMessage(msgId, btnEl) {
            if (!msgId || String(msgId).startsWith('temp_')) return;
            const origText = btnEl ? btnEl.textContent : '✨ AI';
            if (btnEl) {
                btnEl.disabled = true;
                btnEl.textContent = '⏳ جاري التحليل...';
            }

            try {
                const res = await fetch(`${cfg.deleteBaseUrl}/${msgId}/ai-process`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': cfg.csrfToken,
                        'Accept': 'application/json'
                    }
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    if (window.eyalToast) window.eyalToast(data.message || 'تعذر تشغيل المساعد الذكي', 'danger');
                    return;
                }

                if (data.ai_message) {
                    const convId = store.activeConvId;
                    const entry = store.conversations[convId];
                    if (entry && !document.querySelector(`[data-msg-id="${data.ai_message.id}"]`)) {
                        entry.messages.push(data.ai_message);
                        entry.lastId = Math.max(entry.lastId, Number(data.ai_message.id) || 0);
                        messagesContainer.appendChild(createMessageElement(data.ai_message));
                        scrollToBottom();
                    }
                    if (window.eyalToast) window.eyalToast('تم تحليل الرسالة وتنفيذ المطلوب بالذكاء الاصطناعي', 'success');
                }
            } catch (err) {
                if (window.eyalToast) window.eyalToast('تعذر الاتصال بالخادم', 'danger');
            } finally {
                if (btnEl) {
                    btnEl.disabled = false;
                    btnEl.textContent = origText;
                }
            }
        }

        /* البحث الفوري في القائمة الجانبية */
        const searchInput = document.getElementById('conversationSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                const q = e.target.value.toLowerCase().trim();
                document.querySelectorAll('[data-conv-item]').forEach((el) => {
                    const name = (el.getAttribute('data-conv-name') || '').toLowerCase();
                    el.style.display = name.includes(q) ? 'flex' : 'none';
                });
            });
        }

        /* إرفاق الملفات والصور */
        function showAttachmentPreview(text) {
            if (!attachmentPreviewBar) return;
            previewText.textContent = text;
            attachmentPreviewBar.classList.remove('hidden');
        }

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
                store.attachedCard = null;
                attachmentPreviewBar.classList.add('hidden');
            });
        }

        /* إرسال الرسالة بشكل فوري (Optimistic UI) */
        if (chatForm) {
            chatForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                const convId = store.activeConvId;
                if (!convId) return;

                const body = chatMessageInput ? chatMessageInput.value.trim() : '';
                const hasFile = chatFileInput && chatFileInput.files && chatFileInput.files.length > 0;
                const cardType = document.getElementById('cardTypeInput').value;

                if (!body && !hasFile && !cardType) return;

                const formData = new FormData(this);
                const tempId = 'temp_' + Date.now();

                const tempNode = createMessageElement({
                    id: tempId,
                    is_me: true,
                    body: body,
                    time: new Date().toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' }),
                    pending: true,
                    has_card: Boolean(cardType),
                    card: store.attachedCard,
                    attachment: hasFile ? { name: chatFileInput.files[0].name, is_image: false, url: '#' } : null
                });

                messagesContainer.appendChild(tempNode);
                scrollToBottom();

                if (chatMessageInput) {
                    chatMessageInput.value = '';
                    chatMessageInput.focus();
                }
                if (chatFileInput) chatFileInput.value = '';
                document.getElementById('cardTypeInput').value = '';
                document.getElementById('itemIdInput').value = '';
                store.attachedCard = null;
                if (attachmentPreviewBar) attachmentPreviewBar.classList.add('hidden');

                try {
                    const res = await fetch(`${cfg.baseUrl}/${convId}/messages`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': cfg.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    if (!res.ok) throw new Error('Send failed');
                    const data = await res.json();

                    if (data.message) {
                        tempNode.remove();
                        const entry = store.conversations[convId] || (store.conversations[convId] = { messages: [], lastId: 0, delV: 1 });
                        if (!document.querySelector(`[data-msg-id="${data.message.id}"]`)) {
                            entry.messages.push(data.message);
                            entry.lastId = Math.max(entry.lastId, Number(data.message.id) || 0);
                            messagesContainer.appendChild(createMessageElement(data.message));
                        }
                        if (data.ai_message && !document.querySelector(`[data-msg-id="${data.ai_message.id}"]`)) {
                            entry.messages.push(data.ai_message);
                            entry.lastId = Math.max(entry.lastId, Number(data.ai_message.id) || 0);
                            messagesContainer.appendChild(createMessageElement(data.ai_message));
                        }
                        if (data.del_v) entry.delV = Number(data.del_v);
                        scrollToBottom();
                    }
                } catch (err) {
                    tempNode.style.opacity = '0.5';
                    if (window.eyalToast) window.eyalToast('تعذر إرسال الرسالة، يرجى المحاولة مرة أخرى', 'danger');
                }
            });
        }

        /* معاينة بطاقة الطالب */
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

            cardPreviewContent.innerHTML = 'جاري تجهيز البطاقة...';

            try {
                const res = await fetch(cfg.previewCardUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': cfg.csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ student_id: studentId, card_type: cardType })
                });

                if (!res.ok) throw new Error();
                const data = await res.json();

                if (data.card) {
                    store.tempSelectedCard = data.card;
                    let itemsStr = '';
                    if (data.card.items) {
                        for (const [k, v] of Object.entries(data.card.items)) {
                            itemsStr += `<div class="flex justify-between py-0.5"><span>${escapeHtml(k)}:</span><strong>${escapeHtml(String(v))}</strong></div>`;
                        }
                    }
                    cardPreviewContent.innerHTML = `
                        <div class="chat-student-card">
                            <div class="flex justify-between font-extrabold text-primary mb-1">
                                <span>${escapeHtml(data.card.title)}</span>
                                <span class="text-blue-700">${escapeHtml(data.card.badge || '')}</span>
                            </div>
                            <div class="text-[11px] text-primary/70 space-y-0.5">${itemsStr}</div>
                        </div>
                    `;
                }
            } catch {
                cardPreviewContent.textContent = 'تعذر تجهيز المعاينة.';
            }
        }

        if (cardModalStudentSelect) cardModalStudentSelect.addEventListener('change', updateCardPreview);
        document.querySelectorAll('input[name="card_choice"]').forEach((r) => r.addEventListener('change', updateCardPreview));
        document.querySelectorAll('[data-modal-open="shareCardModal"]').forEach((btn) => btn.addEventListener('click', updateCardPreview));

        if (confirmAttachCardBtn) {
            confirmAttachCardBtn.addEventListener('click', function () {
                const studentId = cardModalStudentSelect ? cardModalStudentSelect.value : '';
                const selectedRadio = document.querySelector('input[name="card_choice"]:checked');
                const cardType = selectedRadio ? selectedRadio.value : 'attendance';

                document.getElementById('cardTypeInput').value = cardType;
                document.getElementById('studentIdInput').value = studentId;
                store.attachedCard = store.tempSelectedCard;

                showAttachmentPreview('بطاقة طالب مرفقة: ' + (store.attachedCard?.title || cardType));

                const modal = document.getElementById('shareCardModal');
                if (window.eyalCloseModal && modal) window.eyalCloseModal(modal);
                else if (modal) modal.classList.remove('open');
            });
        }

        /* العرض الفوري في اللحظة صفر + Adaptive Polling ذكي */
        if (store.activeConvId) {
            renderConversationMessages(store.activeConvId);
        }

        function scheduleNextPoll() {
            if (store.pollTimer) clearTimeout(store.pollTimer);
            const delay = store.idleTicks > 12 ? 6000 : (store.idleTicks > 4 ? 3500 : 2200);
            store.pollTimer = setTimeout(async () => {
                await pollActiveConversation(false);
                scheduleNextPoll();
            }, delay);
        }

        scheduleNextPoll();

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                store.idleTicks = 0;
                pollActiveConversation(false);
            }
        });
    })();
</script>
@endpush

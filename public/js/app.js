/* ==========================================================================
   معهد آيلا التعليمي — تفاعلات الواجهة (بدون قاعدة بيانات)
   ========================================================================== */
(function () {
    'use strict';

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    /* ----------------------------- التنبيهات ----------------------------- */
    function toast(message, type) {
        const stack = $('#toastStack');
        if (!stack || !message) return;
        const el = document.createElement('div');
        el.className = 'toast' + (type ? ' toast-' + type : '');
        el.innerHTML =
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>' +
            '<span></span>';
        el.querySelector('span').textContent = message;
        stack.appendChild(el);
        window.setTimeout(() => {
            el.style.transition = 'opacity .25s ease, transform .25s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(8px)';
            window.setTimeout(() => el.remove(), 260);
        }, 3200);
    }
    window.eyalToast = toast;

    $$('[data-flash]').forEach((node) => {
        toast(node.getAttribute('data-flash'), node.getAttribute('data-flash-type') || 'success');
    });

    /* ------------------------- القائمة الجانبية ------------------------- */
    const drawerPanel = $('[data-drawer-panel]');
    const drawerOverlay = $('[data-drawer-overlay]');

    function setDrawer(open) {
        if (!drawerPanel || !drawerOverlay) return;
        drawerPanel.classList.toggle('open', open);
        drawerOverlay.classList.toggle('open', open);
        drawerPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('modal-open', open || $$('.modal-overlay.open').length > 0);
    }

    $$('[data-drawer-open]').forEach((btn) => btn.addEventListener('click', () => setDrawer(true)));
    $$('[data-drawer-close]').forEach((btn) => btn.addEventListener('click', () => setDrawer(false)));
    if (drawerOverlay) drawerOverlay.addEventListener('click', () => setDrawer(false));

    /* --------------------------- النوافذ --------------------------- */
    function syncBodyScroll() {
        document.body.classList.toggle('modal-open', $$('.modal-overlay.open').length > 0 || (drawerPanel && drawerPanel.classList.contains('open')));
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('open');
        syncBodyScroll();
        const first = modal.querySelector('input:not([type=hidden]), select, textarea');
        if (first && window.innerWidth >= 640) window.setTimeout(() => first.focus(), 120);
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('open');
        syncBodyScroll();
    }

    window.eyalOpenModal = openModal;
    window.eyalCloseModal = closeModal;

    $$('[data-modal-open]').forEach((btn) => {
        btn.addEventListener('click', () => openModal(btn.getAttribute('data-modal-open')));
    });
    $$('[data-modal-close]').forEach((btn) => {
        btn.addEventListener('click', () => closeModal(btn.closest('.modal-overlay')));
    });
    $$('.modal-overlay').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeModal(overlay);
        });
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const open = $$('.modal-overlay.open').pop();
        if (open) closeModal(open);
        else setDrawer(false);
    });

    /* ------------------------- النماذج التجريبية ------------------------- */
    $$('form[data-demo-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const modal = form.closest('.modal-overlay');
            if (modal) closeModal(modal);
            toast(form.getAttribute('data-demo-message') || 'تم الحفظ بنجاح — وضع تجريبي بدون قاعدة بيانات', 'success');
            if (form.hasAttribute('data-demo-reset')) form.reset();
        });
    });

    /* ---------------------------- المفاتيح ---------------------------- */
    $$('.toggle').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            if (toggle.hasAttribute('data-toggle-url')) return;
            const next = toggle.getAttribute('aria-checked') !== 'true';
            toggle.setAttribute('aria-checked', next ? 'true' : 'false');
            const label = toggle.getAttribute('data-toggle-label');
            if (label) {
                const target = document.querySelector(label);
                if (target) target.textContent = next ? toggle.getAttribute('data-on') || 'مفعّل' : toggle.getAttribute('data-off') || 'متوقف';
            }
            if (toggle.hasAttribute('data-toast')) {
                toast(toggle.getAttribute('data-toast'), 'success');
            }
        });
    });

    /* ------------------- مفاتيح محفوظة في قاعدة البيانات ------------------- */
    $$('[data-toggle-url]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const url = btn.getAttribute('data-toggle-url');
            const key = btn.getAttribute('data-key') || new URL(url).searchParams.get('key') || '';
            const token = document.querySelector('meta[name="csrf-token"]');
            btn.disabled = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                        'Content-Type': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: key ? JSON.stringify({key}) : undefined,
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    toast(data.message || 'تعذر تحديث الحالة', 'danger');
                    return;
                }

                btn.setAttribute('aria-checked', data.active ? 'true' : 'false');
                toast(btn.getAttribute('data-toggle-toast') || 'تم تحديث الحالة بنجاح', 'success');
            } catch (error) {
                toast('تعذر الاتصال بالخادم', 'danger');
            } finally {
                btn.disabled = false;
            }
        });
    });

    /* ---------------------- فتح نماذج الإضافة والتعديل ---------------------- */
    function setFormMethod(form, method) {
        let field = form.querySelector('input[name="_method"]');
        if (method === 'POST') {
            if (field) field.remove();
            return;
        }
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = '_method';
            form.appendChild(field);
        }
        field.value = method;
    }

    $$('[data-edit]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.getAttribute('data-modal'));
            if (!modal) return;

            let data = {};
            try { data = JSON.parse(btn.getAttribute('data-edit')); } catch (e) { return; }

            const form = modal.querySelector('form');
            if (!form) return;

            form.reset();

            if (data.action) {
                form.action = data.action;
                setFormMethod(form, 'PUT');
            }

            const title = modal.querySelector('[data-modal-title]');
            if (title && data.modal_title) title.textContent = data.modal_title;

            Object.keys(data).forEach((key) => {
                if (key === 'action' || key === 'modal_title') return;
                const field = form.elements.namedItem(key);
                if (!field) return;
                if (field.type === 'checkbox') field.checked = Boolean(data[key]);
                else field.value = data[key] == null ? '' : data[key];
            });

            openModal(modal.id);
        });
    });

    $$('[data-add]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.getAttribute('data-add'));
            if (!modal) return;

            const form = modal.querySelector('form');
            if (form) {
                form.reset();
                const store = form.getAttribute('data-store-action');
                if (store) form.action = store;
                setFormMethod(form, 'POST');
            }

            const title = modal.querySelector('[data-modal-title]');
            if (title && btn.getAttribute('data-add-title')) {
                title.textContent = btn.getAttribute('data-add-title');
            }
        });
    });

    /* --------------------------- البحث الفوري --------------------------- */
    $$('[data-filter-input]').forEach((input) => {
        const targetSelector = input.getAttribute('data-filter-input');
        const emptySelector = input.getAttribute('data-filter-empty');
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            let visible = 0;
            $$(targetSelector).forEach((item) => {
                const haystack = (item.getAttribute('data-search') || item.textContent || '').toLowerCase();
                const match = !term || haystack.indexOf(term) !== -1;
                item.classList.toggle('is-hidden', !match);
                if (match) visible += 1;
            });
            if (emptySelector) {
                const empty = $(emptySelector);
                if (empty) empty.classList.toggle('is-hidden', visible !== 0);
            }
        });
    });

    /* --------------------------- تبويبات الحالة --------------------------- */
    $$('[data-tabs]').forEach((group) => {
        const buttons = $$('[data-tab-value]', group);
        const targetSelector = group.getAttribute('data-tabs');
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                buttons.forEach((b) => {
                    b.classList.remove('bg-primary', 'text-white', 'shadow-soft');
                    b.classList.add('bg-white', 'text-primary');
                });
                btn.classList.add('bg-primary', 'text-white', 'shadow-soft');
                btn.classList.remove('bg-white', 'text-primary');
                const value = btn.getAttribute('data-tab-value');
                $$(targetSelector).forEach((item) => {
                    item.classList.toggle('is-hidden', value !== 'all' && item.getAttribute('data-status') !== value);
                });
            });
        });
    });

    /* ------------------------- حضور الطلاب ------------------------- */
    $$('[data-attendance-group]').forEach((group) => {
        $$('.att-btn', group).forEach((btn) => {
            btn.addEventListener('click', () => {
                $$('.att-btn', group).forEach((b) => b.removeAttribute('data-active'));
                btn.setAttribute('data-active', btn.getAttribute('data-status'));
                const input = group.querySelector('[data-attendance-status]');
                if (input) input.value = btn.getAttribute('data-status');
            });
        });
    });

    $$('[data-attendance-filter]').forEach((select) => {
        select.addEventListener('change', () => {
            const stage = select.value;
            $$('[data-attendance-group]').forEach((group) => {
                group.classList.toggle('is-hidden', stage !== '' && group.getAttribute('data-stage') !== stage);
            });
        });
    });

    /* ---------------------- تعديل بيانات الطالب ---------------------- */
    const studentModal = document.getElementById('studentModal');
    if (studentModal) {
        $$('[data-edit-student]').forEach((btn) => {
            btn.addEventListener('click', () => {
                let data = {};
                try { data = JSON.parse(btn.getAttribute('data-edit-student')); } catch (e) { return; }
                const form = studentModal.querySelector('form');
                if (!form) return;
                form.reset();
                const title = studentModal.querySelector('[data-modal-title]');
                if (title) title.textContent = 'تعديل بيانات الطالب';
                Object.keys(data).forEach((key) => {
                    const field = form.elements.namedItem(key);
                    if (!field) return;
                    if (field.type === 'checkbox') field.checked = Boolean(data[key]);
                    else field.value = data[key] == null ? '' : data[key];
                });
                openModal('studentModal');
            });
        });

        const addBtn = document.querySelector('[data-add-student]');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const form = studentModal.querySelector('form');
                if (form) form.reset();
                const title = studentModal.querySelector('[data-modal-title]');
                if (title) title.textContent = 'إضافة طالب';
            });
        }
    }

    /* ---------------------- إظهار / إخفاء كلمة المرور ---------------------- */
    $$('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.getAttribute('data-toggle-password'));
            if (!input) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.classList.toggle('text-secondary', input.type === 'text');
        });
    });

    /* --------------------------- تأكيد الحذف --------------------------- */
    $$('[data-confirm]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            if (!window.confirm(btn.getAttribute('data-confirm'))) return;
            const selector = btn.getAttribute('data-remove');
            if (selector) {
                const target = selector.indexOf('closest:') === 0
                    ? btn.closest(selector.slice(8))
                    : document.querySelector(selector);
                if (target) {
                    target.style.transition = 'opacity .2s ease, transform .2s ease';
                    target.style.opacity = '0';
                    target.style.transform = 'scale(.98)';
                    window.setTimeout(() => target.remove(), 220);
                }
            }
            toast(btn.getAttribute('data-confirm-toast') || 'تم الحذف — وضع تجريبي', 'danger');
        });
    });

    /* --------------------------- ألوان التطبيق --------------------------- */
    $$('[data-color-input]').forEach((input) => {
        input.addEventListener('input', () => {
            const variable = input.getAttribute('data-color-input');
            document.documentElement.style.setProperty(variable, input.value);
        });
    });

    const resetColors = document.querySelector('[data-reset-colors]');
    if (resetColors) {
        resetColors.addEventListener('click', () => {
            const defaults = { '--app-primary': '#0B3A63', '--app-secondary': '#1565C0' };
            Object.keys(defaults).forEach((key) => document.documentElement.style.setProperty(key, defaults[key]));
            $$('[data-color-input]').forEach((input) => {
                const key = input.getAttribute('data-color-input');
                if (defaults[key]) input.value = defaults[key];
            });
            toast('تمت إعادة الألوان الافتراضية', 'success');
        });
    }

    /* ---------------------- إرسال طلب التسجيل واتساب ---------------------- */
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const value = (name) => {
                const field = registerForm.elements.namedItem(name);
                return field ? String(field.value || '').trim() : '';
            };
            const selectedLabel = (name) => {
                const field = registerForm.elements.namedItem(name);
                return field && field.selectedOptions && field.selectedOptions[0]
                    ? field.selectedOptions[0].textContent.trim()
                    : value(name);
            };
            const student = value('student_name');
            const parent = value('parent_name');
            const phone = value('phone');
            const stage = value('stage');
            const course = value('course');
            const notes = value('notes');
            if (!student || !parent || !phone || !stage) {
                toast('يرجى إكمال الحقول المطلوبة أولاً', 'danger');
                return;
            }

            const submitButton = registerForm.querySelector('button[type="submit"]');
            if (submitButton) submitButton.disabled = true;

            try {
                const response = await fetch(registerForm.action || window.location.href, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new FormData(registerForm),
                    credentials: 'same-origin',
                });

                let data = {};
                try { data = await response.json(); } catch (e) { data = {}; }

                if (!response.ok) {
                    const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
                    toast(firstError || data.message || 'تعذر إرسال طلب التسجيل. يرجى المحاولة مرة أخرى.', 'danger');
                    return;
                }

                if (data.whatsapp_url) {
                    window.open(data.whatsapp_url, '_blank', 'noopener');
                }

                toast(data.message || 'تم استلام طلب التسجيل بنجاح', 'success');
                registerForm.reset();
            } catch (error) {
                const lines = [
                    'السلام عليكم، أرغب بتسجيل طالب في معهد آيلا التعليمي:',
                    'اسم الطالب: ' + student,
                    'اسم ولي الأمر: ' + parent,
                    'رقم الهاتف: ' + phone,
                    'المرحلة: ' + selectedLabel('stage'),
                    course ? 'الدورة/المادة: ' + selectedLabel('course') : '',
                    notes ? 'ملاحظات: ' + notes : '',
                ].filter(Boolean);
                const url = 'https://wa.me/9647762668892?text=' + encodeURIComponent(lines.join('\n'));
                window.open(url, '_blank', 'noopener');
                toast('تم تجهيز طلب التسجيل وإرساله إلى واتساب المعهد', 'success');
            } finally {
                if (submitButton) submitButton.disabled = false;
            }
        });
    }

    /* ------------------------- الطباعة ------------------------- */
    $$('[data-print]').forEach((btn) => btn.addEventListener('click', () => window.print()));
})();

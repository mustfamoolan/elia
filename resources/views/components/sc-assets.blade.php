<style id="eyal-sc-critical-css">
.sc-card {
    background: #ffffff;
    border: 1px solid rgba(11, 58, 99, 0.09);
    border-radius: 1rem;
    box-shadow: 0 2px 10px -4px rgba(11, 58, 99, 0.06);
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
    overflow: hidden;
}
.sc-card:hover { border-color: rgba(11, 58, 99, 0.2); }
.sc-card.is-expanded {
    border-color: rgba(11, 58, 99, 0.25);
    box-shadow: 0 10px 26px -10px rgba(11, 58, 99, 0.14);
}
.sc-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    cursor: pointer;
    user-select: none;
    transition: background 0.15s ease;
}
.sc-summary:hover { background: rgba(11, 58, 99, 0.018); }
.sc-summary-main {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-width: 0;
    flex: 1 1 0%;
}
.sc-avatar {
    display: flex;
    height: 2.75rem;
    width: 2.75rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    background: rgba(11, 58, 99, 0.07);
    color: var(--app-primary, #0b3a63);
    font-size: 0.95rem;
    font-weight: 900;
}
.sc-title-wrap { min-width: 0; flex: 1 1 0%; }
.sc-title-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.4rem;
}
.sc-title {
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--app-primary, #0b3a63);
    line-height: 1.35;
}
.sc-subtitle {
    margin-top: 0.18rem;
    font-size: 0.73rem;
    font-weight: 600;
    color: rgba(11, 58, 99, 0.62);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.35rem;
}
.sc-summary-end {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}
.sc-chevron-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.32rem 0.6rem;
    border-radius: 0.6rem;
    border: 1px solid rgba(11, 58, 99, 0.12);
    background: #f8fafc;
    color: var(--app-primary, #0b3a63);
    font-size: 0.7rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.18s ease;
}
.sc-chevron-btn:hover { background: rgba(11, 58, 99, 0.06); }
.sc-chevron-icon {
    transition: transform 0.22s ease;
    display: inline-flex;
}
.sc-card.is-expanded .sc-chevron-icon { transform: rotate(180deg); }
.sc-card.is-expanded .sc-chevron-btn {
    background: var(--app-primary, #0b3a63);
    color: #ffffff;
    border-color: var(--app-primary, #0b3a63);
}
.sc-details {
    display: none;
    border-top: 1px solid rgba(11, 58, 99, 0.07);
    background: #fafbfd;
    padding: 0.9rem 1rem;
}
.sc-card.is-expanded .sc-details {
    display: block;
    animation: scSlideDown 0.18s ease-out;
}
@keyframes scSlideDown {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}
.sc-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.55rem;
    margin-bottom: 0.75rem;
}
.sc-kv {
    background: #ffffff;
    border: 1px solid rgba(11, 58, 99, 0.07);
    border-radius: 0.75rem;
    padding: 0.5rem 0.7rem;
}
.sc-kv-label {
    font-size: 0.66rem;
    font-weight: 700;
    color: rgba(11, 58, 99, 0.52);
    margin-bottom: 0.1rem;
}
.sc-kv-val {
    font-size: 0.78rem;
    font-weight: 800;
    color: var(--app-primary, #0b3a63);
    word-break: break-word;
}
.sc-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.45rem;
    padding-top: 0.65rem;
    border-top: 1px dashed rgba(11, 58, 99, 0.1);
}
.sc-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.65rem;
    margin-top: 0.9rem;
    padding: 0.65rem 0.95rem;
    background: #ffffff;
    border: 1px solid rgba(11, 58, 99, 0.09);
    border-radius: 0.95rem;
    box-shadow: 0 2px 8px -4px rgba(11, 58, 99, 0.05);
}
.sc-page-info {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    font-size: 0.74rem;
    font-weight: 700;
    color: rgba(11, 58, 99, 0.68);
}
.sc-page-size {
    border: 1px solid rgba(11, 58, 99, 0.15);
    border-radius: 0.55rem;
    padding: 0.22rem 0.45rem;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--app-primary, #0b3a63);
    background: #f8fafc;
    outline: none;
    cursor: pointer;
}
.sc-page-controls {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.3rem;
}
.sc-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2rem;
    height: 2rem;
    padding: 0 0.55rem;
    border-radius: 0.55rem;
    border: 1px solid rgba(11, 58, 99, 0.12);
    background: #ffffff;
    color: var(--app-primary, #0b3a63);
    font-size: 0.74rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sc-page-btn:hover:not(:disabled) {
    background: rgba(11, 58, 99, 0.06);
    border-color: rgba(11, 58, 99, 0.25);
}
.sc-page-btn.is-active {
    background: var(--app-primary, #0b3a63);
    color: #ffffff;
    border-color: var(--app-primary, #0b3a63);
}
.sc-page-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.is-page-hidden {
    display: none !important;
}
.att-btn[data-active="excused"] {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
}

/* تحويل جميع الجداول إلى بطاقات مرتبة بدون سكرول جانبي على الموبايل */
@media (max-width: 767px) {
    .table-wrap {
        overflow-x: visible !important;
        border: 0 !important;
        background: transparent !important;
    }
    table.data-table {
        min-width: 0 !important;
        width: 100% !important;
        display: block;
    }
    table.data-table thead {
        display: none;
    }
    table.data-table tbody {
        display: flex;
        flex-direction: column;
        gap: 0.65rem;
        padding: 0.75rem;
    }
    table.data-table tbody tr {
        display: block;
        background: #ffffff;
        border: 1px solid rgba(11, 58, 99, 0.1);
        border-radius: 0.95rem;
        padding: 0.65rem 0.85rem;
        box-shadow: 0 2px 8px -4px rgba(11, 58, 99, 0.06);
    }
    table.data-table tbody tr:hover {
        background: #ffffff;
    }
    table.data-table tbody td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.42rem 0 !important;
        border-bottom: 1px dashed rgba(11, 58, 99, 0.08) !important;
        text-align: left;
        white-space: normal !important;
    }
    table.data-table tbody td:last-child {
        border-bottom: 0 !important;
        padding-bottom: 0.1rem !important;
    }
    table.data-table tbody td[data-label]:not([data-label=""])::before {
        content: attr(data-label);
        font-size: 0.7rem;
        font-weight: 800;
        color: rgba(11, 58, 99, 0.56);
        text-align: right;
        flex-shrink: 0;
    }
    table.data-table tbody td[data-label=""] {
        justify-content: flex-end;
    }
}
</style>
<script>
(function () {
    'use strict';
    function bootScEngine() {
        const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

        if (!window._eyalCollapseInitialized) {
            window._eyalCollapseInitialized = true;
            document.addEventListener('click', (event) => {
                const trigger = event.target.closest ? event.target.closest('[data-collapse-trigger]') : null;
                if (!trigger) return;
                const interactive = event.target.closest('a, button:not([data-collapse-btn]), input, select, textarea, form, .toggle');
                if (interactive && !interactive.hasAttribute('data-collapse-btn')) return;
                const card = trigger.closest('.sc-card');
                if (!card) return;
                const expanded = card.classList.toggle('is-expanded');
                trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                const labelEl = card.querySelector('[data-collapse-label]');
                if (labelEl) labelEl.textContent = expanded ? 'إخفاء' : 'التفاصيل';
            });
        }

        function initSmartPagination(container) {
            if (!container || container._eyalPaginateInitialized) return;
            container._eyalPaginateInitialized = true;

            const defaultSize = parseInt(container.getAttribute('data-paginate') || '10', 10) || 10;
            const itemSelector = container.getAttribute('data-paginate-items') || ':scope > *';
            let currentPage = 1;
            let pageSize = defaultSize;

            const nav = document.createElement('div');
            nav.className = 'sc-pagination';

            const parentWrap = container.tagName === 'TBODY'
                ? (container.closest('.table-wrap') || container.closest('table'))
                : container;

            if (parentWrap && parentWrap.parentNode) {
                parentWrap.parentNode.insertBefore(nav, parentWrap.nextSibling);
            }

            function getEligibleItems() {
                const all = itemSelector === ':scope > *'
                    ? Array.from(container.children)
                    : $$(itemSelector, container);

                return all.filter((el) => {
                    if (el.classList.contains('sc-pagination')) return false;
                    if (el.hasAttribute('data-paginate-ignore')) return false;
                    if (el.classList.contains('is-hidden')) return false;
                    return true;
                });
            }

            function renderPagination(resetPage) {
                if (resetPage) currentPage = 1;
                const eligible = getEligibleItems();
                const total = eligible.length;
                const totalPages = pageSize > 0 ? Math.max(1, Math.ceil(total / pageSize)) : 1;
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;

                const startIdx = pageSize > 0 ? (currentPage - 1) * pageSize : 0;
                const endIdx = pageSize > 0 ? Math.min(total, startIdx + pageSize) : total;

                const allChildren = itemSelector === ':scope > *'
                    ? Array.from(container.children)
                    : $$(itemSelector, container);
                allChildren.forEach((el) => el.classList.add('is-page-hidden'));
                eligible.slice(startIdx, endIdx).forEach((el) => el.classList.remove('is-page-hidden'));

                if (total <= defaultSize && currentPage === 1 && pageSize === defaultSize) {
                    nav.classList.add('is-hidden');
                    return;
                }
                nav.classList.remove('is-hidden');

                const fromNum = total === 0 ? 0 : startIdx + 1;
                const toNum = endIdx;

                let pagesHtml = `<button type="button" class="sc-page-btn" data-page-go="${currentPage - 1}" ${currentPage <= 1 ? 'disabled' : ''}>السابق</button>`;
                const maxButtons = 5;
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, startPage + maxButtons - 1);
                if (endPage - startPage < maxButtons - 1) startPage = Math.max(1, endPage - maxButtons + 1);

                if (startPage > 1) {
                    pagesHtml += `<button type="button" class="sc-page-btn" data-page-go="1">1</button>`;
                    if (startPage > 2) pagesHtml += `<span class="px-1 text-xs text-primary/40">…</span>`;
                }
                for (let p = startPage; p <= endPage; p++) {
                    pagesHtml += `<button type="button" class="sc-page-btn ${p === currentPage ? 'is-active' : ''}" data-page-go="${p}">${p}</button>`;
                }
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) pagesHtml += `<span class="px-1 text-xs text-primary/40">…</span>`;
                    pagesHtml += `<button type="button" class="sc-page-btn" data-page-go="${totalPages}">${totalPages}</button>`;
                }
                pagesHtml += `<button type="button" class="sc-page-btn" data-page-go="${currentPage + 1}" ${currentPage >= totalPages ? 'disabled' : ''}>التالي</button>`;

                nav.innerHTML = `
                    <div class="sc-page-info">
                        <span>عرض <b>${fromNum}–${toNum}</b> من أصل <b>${total}</b></span>
                        <select class="sc-page-size" aria-label="عدد العناصر في الصفحة">
                            <option value="10" ${pageSize === 10 ? 'selected' : ''}>10 / صفحة</option>
                            <option value="20" ${pageSize === 20 ? 'selected' : ''}>20 / صفحة</option>
                            <option value="50" ${pageSize === 50 ? 'selected' : ''}>50 / صفحة</option>
                            <option value="0" ${pageSize === 0 ? 'selected' : ''}>عرض الكل</option>
                        </select>
                    </div>
                    <div class="sc-page-controls">${pagesHtml}</div>
                `;

                const sizeSelect = nav.querySelector('.sc-page-size');
                if (sizeSelect) {
                    sizeSelect.addEventListener('change', () => {
                        pageSize = parseInt(sizeSelect.value, 10) || 0;
                        renderPagination(true);
                    });
                }
                $$('[data-page-go]', nav).forEach((btn) => {
                    btn.addEventListener('click', () => {
                        const targetPage = parseInt(btn.getAttribute('data-page-go'), 10);
                        if (!targetPage || targetPage === currentPage || targetPage < 1 || targetPage > totalPages) return;
                        currentPage = targetPage;
                        renderPagination(false);
                    });
                });
            }

            container._eyalPaginateRefresh = renderPagination;
            renderPagination(true);
        }

        $$('[data-paginate]').forEach(initSmartPagination);
        if (!window.eyalInitPagination) window.eyalInitPagination = initSmartPagination;

        // إضافة تسميات الأعمدة تلقائياً للجداول لتظهر كبطاقات مرتبة بدون سكرول جانبي على الموبايل
        $$('table.data-table').forEach((table) => {
            const headers = Array.from(table.querySelectorAll('thead th')).map((th) => th.textContent.trim());
            if (!headers.length) return;
            table.querySelectorAll('tbody tr').forEach((tr) => {
                Array.from(tr.children).forEach((td, idx) => {
                    if (td.tagName === 'TD' && !td.hasAttribute('data-label')) {
                        td.setAttribute('data-label', headers[idx] || '');
                    }
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootScEngine);
    } else {
        bootScEngine();
    }
})();
</script>

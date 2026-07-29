/* Cerebro — entities.js — Filtros, busca, view toggle */
/* Assets locais, sem inline, sem CDN — AGENTS.md */

'use strict';

// ─── Filtros e busca de entidades ──────────────────────────────────────
const EntityFilter = (() => {
    let searchInput, typeChips, statusChips, cards, tableRows;
    let activeType = 'all';
    let activeStatus = 'all';
    let searchTerm = '';

    function matches(card) {
        const name = (card.dataset.name || '').toLowerCase();
        const type = card.dataset.type || '';
        const status = card.dataset.status || '';

        if (searchTerm && !name.includes(searchTerm)) return false;
        if (activeType !== 'all' && type !== activeType) return false;
        if (activeStatus !== 'all' && status !== activeStatus) return false;
        return true;
    }

    function applyFilters() {
        let visible = 0;
        cards.forEach(card => {
            const show = matches(card);
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        tableRows && tableRows.forEach(row => {
            const show = matches(row);
            row.style.display = show ? '' : 'none';
        });

        const empty = document.getElementById('entities-empty');
        if (empty) empty.style.display = visible === 0 ? 'block' : 'none';

        const counter = document.getElementById('entities-count');
        if (counter) counter.textContent = visible;
    }

    function initChips(chips, setter, key) {
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                chips.forEach(c => c.classList.remove('active'));
                chip.classList.add('active');
                setter(chip.dataset[key] || 'all');
                applyFilters();
            });
        });
    }

    function init() {
        searchInput = document.getElementById('entity-search');
        typeChips   = document.querySelectorAll('[data-filter-type]');
        statusChips = document.querySelectorAll('[data-filter-status]');
        cards       = document.querySelectorAll('.cbr-entity-card, [data-name]');
        tableRows   = document.querySelectorAll('tbody tr[data-name]');

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                searchTerm = searchInput.value.toLowerCase().trim();
                applyFilters();
            });
        }

        initChips(typeChips, v => { activeType = v; }, 'filterType');
        initChips(statusChips, v => { activeStatus = v; }, 'filterStatus');
    }

    return { init };
})();

// ─── View toggle (cards / tabela) ─────────────────────────────────────
const ViewToggle = (() => {
    function init() {
        const cardView  = document.getElementById('view-cards');
        const tableView = document.getElementById('view-table');
        const btnCards  = document.getElementById('btn-view-cards');
        const btnTable  = document.getElementById('btn-view-table');

        if (!btnCards || !btnTable) return;

        btnCards.addEventListener('click', () => {
            cardView  && (cardView.style.display  = '');
            tableView && (tableView.style.display = 'none');
            btnCards.classList.add('active');
            btnTable.classList.remove('active');
            localStorage.setItem('cbr-entity-view', 'cards');
        });

        btnTable.addEventListener('click', () => {
            cardView  && (cardView.style.display  = 'none');
            tableView && (tableView.style.display = '');
            btnTable.classList.add('active');
            btnCards.classList.remove('active');
            localStorage.setItem('cbr-entity-view', 'table');
        });

        // Restaurar preferência
        if (localStorage.getItem('cbr-entity-view') === 'table') {
            btnTable.click();
        }
    }
    return { init };
})();

// ─── Confirmar hipótese (POST via fetch) ──────────────────────────────
function initConfirmButtons() {
    document.querySelectorAll('[data-confirm-entity]').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id = this.dataset.confirmEntity;
            const name = this.dataset.entityName || 'esta entidade';
            if (!confirm(`Confirmar "${name}" como fato documentado?`)) return;

            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i>';

            try {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = this.dataset.confirmUrl;

                const csrf = document.querySelector('meta[name="csrf-token"]');
                if (csrf) {
                    const hidden = document.createElement('input');
                    hidden.type  = 'hidden';
                    hidden.name  = csrf.dataset.name || 'csrf_token';
                    hidden.value = csrf.content;
                    form.appendChild(hidden);
                }
                document.body.appendChild(form);
                form.submit();
            } catch (e) {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-patch-check"></i> Confirmar';
            }
        });
    });
}

// ─── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    EntityFilter.init();
    ViewToggle.init();
    initConfirmButtons();
});

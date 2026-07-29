/* Cerebro — forms.js — Stepper, tipo dinâmico, autocomplete, slider */
/* Assets locais, sem inline, sem CDN — AGENTS.md */

'use strict';

// ─── Stepper ───────────────────────────────────────────────────────────
const Stepper = (() => {
    let steps, panels, currentStep = 0;

    function goTo(index) {
        steps.forEach((s, i) => {
            s.classList.remove('active', 'completed');
            if (i < index) s.classList.add('completed');
            if (i === index) s.classList.add('active');
        });
        panels.forEach((p, i) => {
            p.classList.toggle('active', i === index);
        });
        currentStep = index;
        updateButtons();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateButtons() {
        const btnPrev = document.getElementById('step-prev');
        const btnNext = document.getElementById('step-next');
        const btnSubmit = document.getElementById('step-submit');

        if (btnPrev) btnPrev.style.display = currentStep === 0 ? 'none' : '';
        if (btnNext) btnNext.style.display = currentStep >= panels.length - 1 ? 'none' : '';
        if (btnSubmit) btnSubmit.style.display = currentStep >= panels.length - 1 ? '' : 'none';
    }

    function validateStep(index) {
        const panel = panels[index];
        if (!panel) return true;
        const required = panel.querySelectorAll('[required]');
        let ok = true;
        required.forEach(field => {
            field.classList.remove('is-invalid');
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                ok = false;
            }
        });
        return ok;
    }

    function init() {
        steps  = document.querySelectorAll('.cbr-step');
        panels = document.querySelectorAll('.cbr-step-panel');
        if (!steps.length) return;

        goTo(0);

        document.getElementById('step-next') && document.getElementById('step-next').addEventListener('click', () => {
            if (validateStep(currentStep)) goTo(currentStep + 1);
        });
        document.getElementById('step-prev') && document.getElementById('step-prev').addEventListener('click', () => {
            goTo(Math.max(0, currentStep - 1));
        });

        steps.forEach((step, i) => {
            step.addEventListener('click', () => {
                if (i <= currentStep || validateStep(currentStep)) goTo(i);
            });
        });
    }

    return { init, goTo };
})();

// ─── Seletor de tipo de entidade ───────────────────────────────────────
const TypeSelector = (() => {
    function init() {
        const options = document.querySelectorAll('.cbr-type-option');
        const dynamicSections = document.querySelectorAll('.cbr-dynamic-fields');
        const typeInput = document.getElementById('entity-type-input');

        options.forEach(opt => {
            opt.addEventListener('click', () => {
                options.forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');

                const type = opt.dataset.type;
                if (typeInput) typeInput.value = type;

                dynamicSections.forEach(sec => {
                    sec.classList.toggle('active', sec.dataset.forType === type);
                });

                // Preencher revisão
                updateReview();
            });
        });
    }
    return { init };
})();

// ─── Autocomplete de entidade ──────────────────────────────────────────
const Autocomplete = (() => {
    function bind(inputId, hiddenId, resultsId, dataUrl) {
        const input   = document.getElementById(inputId);
        const hidden  = document.getElementById(hiddenId);
        const results = document.getElementById(resultsId);

        if (!input || !results) return;

        let debounce;

        input.addEventListener('input', () => {
            clearTimeout(debounce);
            const q = input.value.trim();
            if (q.length < 2) {
                results.classList.remove('open');
                results.innerHTML = '';
                if (hidden) hidden.value = '';
                return;
            }
            debounce = setTimeout(async () => {
                try {
                    const url = dataUrl + '?q=' + encodeURIComponent(q);
                    const res = await fetch(url, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    renderResults(data, results, input, hidden);
                } catch (e) {
                    console.warn('Autocomplete error:', e);
                }
            }, 280);
        });

        document.addEventListener('click', e => {
            if (!input.contains(e.target) && !results.contains(e.target)) {
                results.classList.remove('open');
            }
        });
    }

    const typeIconMap = {
        person:   { icon: 'bi-person-fill',     cls: 'badge-person' },
        location: { icon: 'bi-geo-alt-fill',     cls: 'badge-location' },
        event:    { icon: 'bi-calendar-event',   cls: 'badge-event' },
        document: { icon: 'bi-file-earmark-text',cls: 'badge-document' },
    };

    function renderResults(items, container, input, hidden) {
        container.innerHTML = '';
        if (!items || !items.length) {
            container.innerHTML = '<div class="cbr-autocomplete-item text-subtle"><i class="bi bi-search me-2"></i>Nenhum resultado</div>';
            container.classList.add('open');
            return;
        }
        items.forEach(item => {
            const ti = typeIconMap[item.type] || { icon: 'bi-circle', cls: '' };
            const div = document.createElement('div');
            div.className = 'cbr-autocomplete-item';
            div.innerHTML = `
                <div class="item-icon badge-type ${ti.cls}"><i class="bi ${ti.icon}"></i></div>
                <div>
                    <div class="item-name">${escHtml(item.name)}</div>
                    <div class="item-type">${escHtml(item.type)}</div>
                </div>
            `;
            div.addEventListener('click', () => {
                input.value = item.name;
                if (hidden) hidden.value = item.id;
                container.classList.remove('open');
                container.innerHTML = '';
            });
            container.appendChild(div);
        });
        container.classList.add('open');
    }

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function init() {
        // Binds declarados via data attributes no HTML
        document.querySelectorAll('[data-autocomplete]').forEach(wrap => {
            bind(
                wrap.dataset.inputId,
                wrap.dataset.hiddenId,
                wrap.dataset.resultsId,
                wrap.dataset.autocomplete
            );
        });
    }

    return { init, bind };
})();

// ─── Slider de confiança ───────────────────────────────────────────────
function initConfidenceSliders() {
    document.querySelectorAll('.cbr-confidence-slider').forEach(slider => {
        const display = document.getElementById(slider.dataset.display);

        function update() {
            const pct = slider.value;
            slider.style.setProperty('--slider-pct', pct + '%');
            if (display) display.querySelector('[data-pct]').textContent = pct;
        }

        slider.addEventListener('input', update);
        update();
    });
}

// ─── Revisão do formulário (step 3) ───────────────────────────────────
function updateReview() {
    const map = {
        'review-type': document.getElementById('entity-type-input'),
        'review-name': document.getElementById('entity-name'),
    };
    Object.entries(map).forEach(([id, field]) => {
        const el = document.getElementById(id);
        if (el && field) el.textContent = field.value || '—';
    });
}

// ─── JSONB attributes preview ──────────────────────────────────────────
function initAttrPreview() {
    document.querySelectorAll('[data-attr-key]').forEach(field => {
        field.addEventListener('input', () => {
            const key = field.dataset.attrKey;
            const preview = document.querySelector(`[data-preview="${key}"]`);
            if (preview) preview.textContent = field.value || '—';
        });
    });
}

// ─── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    Stepper.init();
    TypeSelector.init();
    Autocomplete.init();
    initConfidenceSliders();
    initAttrPreview();
});

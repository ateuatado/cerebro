/* Cerebro — batch-ingest.js — Drag & Drop + Processamento em Lote assíncrono via IA */
/* Assets locais, sem inline, sem CDN — AGENTS.md */

'use strict';

window.clearFullDatabase = function () {
    if (!confirm('⚠️ ATENÇÃO: Deseja ZERAR COMPLETAMENTE toda a base de dados (0 entidades, 0 relações, apagar todos os arquivos salvos)?\n\nEsta ação é irreversível!')) {
        return;
    }

    const baseUrl = (window.BASE_URL || '/').replace(/\/+$/, '') + '/';
    fetch(baseUrl + 'api/limpar-banco-total', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Erro ao zerar base: ' + (data.error || 'Acesso negado.'));
        }
    })
    .catch(err => {
        alert('Erro de rede ao comunicar com o servidor.');
    });
};

document.addEventListener('DOMContentLoaded', function () {
    const dropzoneCard    = document.getElementById('dropzone-card');
    const fileInput       = document.getElementById('batch-file-input');
    const btnBrowse       = document.getElementById('btn-browse-files');
    const progressCard    = document.getElementById('batch-progress-card');
    const queueCard       = document.getElementById('queue-card');
    const queueList       = document.getElementById('batch-queue-list');
    const progressBar     = document.getElementById('batch-progress-bar');
    const statusTitle     = document.getElementById('batch-status-title');
    const statusCounts    = document.getElementById('batch-status-counts');
    const countEntitiesEl = document.getElementById('count-entities');
    const countRelsEl     = document.getElementById('count-rels');
    const countHypoEl     = document.getElementById('count-hypotheses');
    const btnGraphSummary = document.getElementById('btn-view-graph-summary');

    if (!dropzoneCard || !fileInput) return;

    let queue = [];
    let isProcessing = false;
    let totalEntities = 0;
    let totalRels = 0;
    let totalHypotheses = 0;
    let completedFiles = 0;

    // Trigger seletor de arquivos
    btnBrowse.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', function () {
        if (this.files.length > 0) {
            handleFiles(Array.from(this.files));
        }
    });

    // Drag and Drop
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzoneCard.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzoneCard.style.borderColor = 'var(--cbr-accent)';
            dropzoneCard.style.background = 'var(--cbr-primary-dim)';
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzoneCard.addEventListener(eventName, e => {
            e.preventDefault();
            e.stopPropagation();
            dropzoneCard.style.borderColor = 'var(--cbr-primary)';
            dropzoneCard.style.background = 'var(--cbr-surface-2)';
        });
    });

    dropzoneCard.addEventListener('drop', e => {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            handleFiles(Array.from(files));
        }
    });

    function handleFiles(files) {
        // Filtrar formatos válidos (TXT, PDF, JPG, PNG, etc.)
        const validFiles = files.filter(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            return ['txt', 'md', 'json', 'csv', 'pdf', 'jpg', 'jpeg', 'png', 'webp', 'bmp'].includes(ext);
        });

        if (validFiles.length === 0) {
            alert('Nenhum arquivo válido (.pdf, .jpg, .png, .txt, .md, .json, .csv) foi selecionado.');
            return;
        }

        queue = validFiles.map((file, idx) => ({
            id: 'file-' + idx + '-' + Date.now(),
            file: file,
            status: 'pending', // pending, uploading, extracting, done, error
            message: 'Aguardando fila...',
            entities: 0,
            rels: 0,
            hypotheses: 0,
        }));

        renderQueue();
        startBatchProcess();
    }

    function renderQueue() {
        progressCard.style.display = 'block';
        queueCard.style.display = 'block';
        queueList.innerHTML = '';

        queue.forEach(item => {
            const li = document.createElement('li');
            li.id = item.id;
            li.className = 'cbr-recent-item';
            li.innerHTML = `
                <div class="cbr-recent-icon" style="background:var(--cbr-surface-2);color:var(--cbr-text-muted)">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="cbr-recent-name">${escHtml(item.file.name)}</div>
                    <div class="cbr-recent-meta item-status-msg" style="font-size:.75rem">${escHtml(item.message)}</div>
                </div>
                <div class="item-badge">
                    <span class="badge-hypothesis" style="background:var(--cbr-surface-2);color:var(--cbr-text-muted)">Pendente</span>
                </div>
            `;
            queueList.appendChild(li);
        });

        updateProgressUI();
    }

    async function startBatchProcess() {
        if (isProcessing) return;
        isProcessing = true;
        totalEntities = 0;
        totalRels = 0;
        totalHypotheses = 0;
        completedFiles = 0;

        for (let i = 0; i < queue.length; i++) {
            const item = queue[i];
            updateItemStatus(item.id, 'extracting', 'Analisando documento com IA DeepSeek...', '<span class="badge-hypothesis" style="background:var(--cbr-primary-dim);color:var(--cbr-primary)"><i class="bi bi-hourglass-split me-1"></i>Analisando IA</span>');

            try {
                const formData = new FormData();
                formData.append('file', item.file);

                const csrf = document.querySelector('meta[name="csrf-token"]');
                if (csrf) {
                    formData.append(csrf.dataset.name || 'csrf_token', csrf.content);
                }

                const uploadUrl = dropzoneCard.dataset.uploadUrl 
                    || ((window.BASE_URL || '/').replace(/\/+$/, '') + '/api/documentos/upload-item');

                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    item.status = 'done';
                    item.entities = data.entitiesExtracted || 0;
                    item.rels = data.relsExtracted || 0;
                    item.hypotheses = data.hypothesesCount || 0;

                    totalEntities += item.entities;
                    totalRels += item.rels;
                    totalHypotheses += item.hypotheses;

                    const msg = `✓ Extraídas ${item.entities} entidades e ${item.rels} relações (${item.hypotheses} hipóteses)`;
                    updateItemStatus(item.id, 'done', msg, '<span class="badge-confirmed"><i class="bi bi-check-lg me-1"></i>Concluído</span>');
                } else {
                    item.status = 'error';
                    const errMsg = data.error || data.message || 'Erro no processamento.';
                    updateItemStatus(item.id, 'error', '❌ ' + errMsg, '<span class="badge-hypothesis" style="background:rgba(239,68,68,.15);color:var(--cbr-danger)"><i class="bi bi-x-circle me-1"></i>Erro</span>');
                }
            } catch (err) {
                item.status = 'error';
                updateItemStatus(item.id, 'error', '❌ Falha de rede/servidor: ' + err.message, '<span class="badge-hypothesis" style="background:rgba(239,68,68,.15);color:var(--cbr-danger)">Erro</span>');
            }

            completedFiles++;
            updateProgressUI();

            await new Promise(r => setTimeout(r, 200));
        }

        isProcessing = false;
        statusTitle.textContent = '🎉 Ingestão em Lote Concluída!';
        btnGraphSummary.classList.remove('d-none');
    }

    function updateItemStatus(itemId, status, message, badgeHtml) {
        const itemEl = document.getElementById(itemId);
        if (!itemEl) return;
        const msgEl = itemEl.querySelector('.item-status-msg');
        const badgeEl = itemEl.querySelector('.item-badge');
        if (msgEl) msgEl.innerHTML = escHtml(message);
        if (badgeEl) badgeEl.innerHTML = badgeHtml;

        if (status === 'extracting') {
            itemEl.style.background = 'var(--cbr-primary-dim)';
        } else {
            itemEl.style.background = '';
        }
    }

    function updateProgressUI() {
        const pct = queue.length > 0 ? Math.round((completedFiles / queue.length) * 100) : 0;
        progressBar.style.width = pct + '%';
        statusCounts.textContent = `${completedFiles} / ${queue.length} concluídos (${pct}%)`;

        countEntitiesEl.textContent = totalEntities;
        countRelsEl.textContent = totalRels;
        countHypoEl.textContent = totalHypotheses;
    }

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
});

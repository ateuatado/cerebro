/* Cerebro — review-workspace.js — Workspace Interativo de Transcrição Histórica (Spec 7 & Spec 8) */
/* Assets locais, sem inline, sem CDN — AGENTS.md */

'use strict';

window.deleteDocumentFromWorkspace = function (docId, docName) {
    if (!confirm(`Deseja APAGAR DEFINITIVAMENTE o documento:\n"${docName}"\n\nIsso apagará o arquivo físico no servidor e todas as conexões geradas por ele no grafo!`)) {
        return;
    }

    const baseUrl = (window.BASE_URL || '/').replace(/\/+$/, '') + '/';
    fetch(baseUrl + `documentos/${docId}/deletar`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = baseUrl + 'documentos/pendentes';
        } else {
            alert('Erro ao excluir documento: ' + (data.error || 'Falha.'));
        }
    })
    .catch(() => alert('Erro de comunicação com o servidor.'));
};

document.addEventListener('DOMContentLoaded', function () {
    const container      = document.getElementById('workspace-container');
    if (!container) return;

    const docId          = parseInt(container.dataset.docId, 10);
    const totalPages     = parseInt(container.dataset.totalPages, 10) || 1;

    const imgViewer      = document.getElementById('pageImageViewer');
    const wrapper        = document.getElementById('imageCanvasWrapper');
    const spinner        = document.getElementById('imageLoadingSpinner');
    const cropOverlay    = document.getElementById('cropOverlay');
    const currentPageEl  = document.getElementById('currentPageNum');
    const textarea       = document.getElementById('transcriptionTextarea');
    const charBadge      = document.getElementById('charCountBadge');

    const btnPrevPage          = document.getElementById('btnPrevPage');
    const btnNextPage          = document.getElementById('btnNextPage');
    const btnRotateCcw         = document.getElementById('btnRotateCcw');
    const btnRotateCw          = document.getElementById('btnRotateCw');
    const btnRotate180         = document.getElementById('btnRotate180');
    const btnToggleCrop        = document.getElementById('btnToggleCrop');
    const btnExtractCrop       = document.getElementById('btnExtractCrop');
    const btnExtractRegionEnts = document.getElementById('btnExtractRegionEntities');
    const btnSaveText          = document.getElementById('btnSaveWorkspaceText');
    const btnExtractFull       = document.getElementById('btnExtractPageFullIa');

    // Elementos da Modal (Spec 8)
    const modalEl              = document.getElementById('modalRegionEntities');
    let regionModal            = null;
    if (modalEl && typeof bootstrap !== 'undefined') {
        regionModal = new bootstrap.Modal(modalEl);
    }
    const modalTranscript      = document.getElementById('modalRegionTranscript');
    const modalEntitiesList    = document.getElementById('modalRegionEntitiesList');
    const modalRelsList        = document.getElementById('modalRegionRelsList');
    const btnConfirmEntities   = document.getElementById('btnConfirmRegionEntities');

    let currentPage = 1;
    let isCropMode = false;
    let isMouseDown = false;
    let startX = 0, startY = 0;
    let cropCoords = null; // {x, y, w, h, canvasW, canvasH}
    let lastRegionResult = null;

    function getBaseUrl() {
        return (window.BASE_URL || '/').replace(/\/+$/, '') + '/';
    }

    // 1. Contador de Caracteres
    textarea.addEventListener('input', function () {
        const len = this.value.length;
        charBadge.textContent = len.toLocaleString('pt-BR') + ' caracteres';
    });

    // 2. Navegação Paginada
    function loadPage(pageNum) {
        if (pageNum < 1 || pageNum > totalPages) return;
        currentPage = pageNum;
        currentPageEl.textContent = currentPage;

        spinner.classList.remove('d-none');
        resetCrop();

        const imgUrl = getBaseUrl() + `api/documentos/${docId}/pagina/${currentPage}/imagem?t=` + Date.now();
        imgViewer.src = imgUrl;
    }

    imgViewer.addEventListener('load', () => spinner.classList.add('d-none'));
    imgViewer.addEventListener('error', () => spinner.classList.add('d-none'));

    btnPrevPage.addEventListener('click', () => loadPage(currentPage - 1));
    btnNextPage.addEventListener('click', () => loadPage(currentPage + 1));

    // 3. Rotação Física da Imagem no Servidor + Re-OCR
    function handleRotation(degrees) {
        spinner.classList.remove('d-none');

        const formData = new FormData();
        formData.append('degrees', degrees);

        fetch(getBaseUrl() + `api/documentos/${docId}/pagina/${currentPage}/girar`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            spinner.classList.add('d-none');
            if (data.success) {
                loadPage(currentPage);
                if (data.ocrText && data.ocrText.trim().length > 0) {
                    if (confirm('A rotação gerou uma nova leitura OCR. Deseja anexar esse texto ao editor?')) {
                        textarea.value += "\n\n--- OCR PÁGINA " + currentPage + " (Orientação Corrigida) ---\n" + data.ocrText;
                        textarea.dispatchEvent(new Event('input'));
                    }
                }
            } else {
                alert('Erro na rotação: ' + (data.error || 'Falha.'));
            }
        })
        .catch(() => {
            spinner.classList.add('d-none');
            alert('Erro de comunicação ao rotacionar imagem.');
        });
    }

    btnRotateCw.addEventListener('click', () => handleRotation(90));
    btnRotateCcw.addEventListener('click', () => handleRotation(270));
    btnRotate180.addEventListener('click', () => handleRotation(180));

    // 4. Desenho de Seleção por Região (Crop Tool)
    btnToggleCrop.addEventListener('click', function () {
        isCropMode = !isCropMode;
        if (isCropMode) {
            btnToggleCrop.classList.replace('btn-outline-primary', 'btn-primary');
            wrapper.classList.add('crop-mode');
        } else {
            btnToggleCrop.classList.replace('btn-primary', 'btn-outline-primary');
            wrapper.classList.remove('crop-mode');
            resetCrop();
        }
    });

    wrapper.addEventListener('mousedown', function (e) {
        if (!isCropMode || e.target !== imgViewer) return;
        isMouseDown = true;

        const rect = imgViewer.getBoundingClientRect();
        startX = e.clientX - rect.left;
        startY = e.clientY - rect.top;

        cropOverlay.style.left   = (imgViewer.offsetLeft + startX) + 'px';
        cropOverlay.style.top    = (imgViewer.offsetTop + startY) + 'px';
        cropOverlay.style.width  = '0px';
        cropOverlay.style.height = '0px';
        cropOverlay.classList.remove('d-none');
        btnExtractCrop.classList.add('d-none');
        if (btnExtractRegionEnts) btnExtractRegionEnts.classList.add('d-none');
    });

    wrapper.addEventListener('mousemove', function (e) {
        if (!isCropMode || !isMouseDown) return;
        const rect = imgViewer.getBoundingClientRect();
        const currentX = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
        const currentY = Math.max(0, Math.min(e.clientY - rect.top, rect.height));

        const width  = Math.abs(currentX - startX);
        const height = Math.abs(currentY - startY);
        const left   = Math.min(startX, currentX);
        const top    = Math.min(startY, currentY);

        cropOverlay.style.left   = (imgViewer.offsetLeft + left) + 'px';
        cropOverlay.style.top    = (imgViewer.offsetTop + top) + 'px';
        cropOverlay.style.width  = width + 'px';
        cropOverlay.style.height = height + 'px';

        cropCoords = {
            x: left,
            y: top,
            width: width,
            height: height,
            canvas_w: rect.width,
            canvas_h: rect.height,
        };
    });

    document.addEventListener('mouseup', function () {
        if (isMouseDown && cropCoords && cropCoords.width > 20 && cropCoords.height > 20) {
            btnExtractCrop.classList.remove('d-none');
            if (btnExtractRegionEnts) btnExtractRegionEnts.classList.remove('d-none');
        }
        isMouseDown = false;
    });

    function resetCrop() {
        cropOverlay.classList.add('d-none');
        btnExtractCrop.classList.add('d-none');
        if (btnExtractRegionEnts) btnExtractRegionEnts.classList.add('d-none');
        cropCoords = null;
    }

    // 5. Extração por IA do Texto Recortado
    btnExtractCrop.addEventListener('click', function () {
        if (!cropCoords) return;

        btnExtractCrop.disabled = true;
        btnExtractCrop.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Extraindo Texto...';

        const formData = new FormData();
        formData.append('x', Math.round(cropCoords.x));
        formData.append('y', Math.round(cropCoords.y));
        formData.append('width', Math.round(cropCoords.width));
        formData.append('height', Math.round(cropCoords.height));
        formData.append('canvas_w', Math.round(cropCoords.canvas_w));
        formData.append('canvas_h', Math.round(cropCoords.canvas_h));

        fetch(getBaseUrl() + `api/documentos/${docId}/pagina/${currentPage}/extrair-regiao`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            btnExtractCrop.disabled = false;
            btnExtractCrop.innerHTML = '<i class="bi bi-cpu me-1"></i> Extrair Texto (IA)';

            if (data.success && data.text) {
                const extractedText = data.text.trim();
                textarea.value += (textarea.value ? "\n\n" : "") + `[TRECHO RECORTO DA PÁGINA ${currentPage}]:\n` + extractedText;
                textarea.dispatchEvent(new Event('input'));
                alert('Transcrição da região extraída e anexada ao editor com sucesso!');
                resetCrop();
            } else {
                alert('Erro na extração da região: ' + (data.error || 'Nenhum texto reconhecido.'));
            }
        })
        .catch(() => {
            btnExtractCrop.disabled = false;
            btnExtractCrop.innerHTML = '<i class="bi bi-cpu me-1"></i> Extrair Texto (IA)';
            alert('Falha de conexão ao extrair região.');
        });
    });

    // 6. Spec 8: Extração de Entidades & Grafo da Região em 1-Clique
    if (btnExtractRegionEnts) {
        btnExtractRegionEnts.addEventListener('click', function () {
            if (!cropCoords) return;

            btnExtractRegionEnts.disabled = true;
            btnExtractRegionEnts.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Extraindo Entidades...';

            const formData = new FormData();
            formData.append('x', Math.round(cropCoords.x));
            formData.append('y', Math.round(cropCoords.y));
            formData.append('width', Math.round(cropCoords.width));
            formData.append('height', Math.round(cropCoords.height));
            formData.append('canvas_w', Math.round(cropCoords.canvas_w));
            formData.append('canvas_h', Math.round(cropCoords.canvas_h));

            fetch(getBaseUrl() + `api/documentos/${docId}/pagina/${currentPage}/extrair-entidades-regiao`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                btnExtractRegionEnts.disabled = false;
                btnExtractRegionEnts.innerHTML = '<i class="bi bi-diagram-3-fill me-1"></i> ✨ Extrair Entidades (IA)';

                if (data.success) {
                    lastRegionResult = data;
                    renderRegionEntitiesModal(data);
                    if (regionModal) regionModal.show();
                } else {
                    alert('Erro ao extrair entidades da região: ' + (data.error || 'Falha.'));
                }
            })
            .catch(() => {
                btnExtractRegionEnts.disabled = false;
                btnExtractRegionEnts.innerHTML = '<i class="bi bi-diagram-3-fill me-1"></i> ✨ Extrair Entidades (IA)';
                alert('Erro de conexão ao extrair entidades da área selecionada.');
            });
        });
    }

    function renderRegionEntitiesModal(data) {
        modalTranscript.value = data.transcription || '';
        modalEntitiesList.innerHTML = '';
        modalRelsList.innerHTML = '';

        const entities = data.entities || [];
        const rels     = data.relationships || [];

        if (entities.length === 0) {
            modalEntitiesList.innerHTML = '<div class="col-12 text-muted" style="font-size:.8125rem">Nenhuma entidade identificada especificamente neste trecho.</div>';
        } else {
            entities.forEach((ent, idx) => {
                const badgeClass = ent.type === 'person' ? 'bg-primary' : (ent.type === 'location' ? 'bg-success' : 'bg-warning text-dark');
                const col = document.createElement('div');
                col.className = 'col-md-6';
                col.innerHTML = `
                    <div class="p-2 border rounded" style="background:var(--cbr-surface-2)">
                        <div class="form-check">
                            <input class="form-check-input chk-region-entity" type="checkbox" id="chkEnt_${idx}" value="${idx}" checked>
                            <label class="form-check-label fw-bold d-block" for="chkEnt_${idx}" style="font-size:.8125rem">
                                <span class="badge ${badgeClass} me-1">${escHtml(ent.type)}</span>
                                ${escHtml(ent.name)}
                            </label>
                        </div>
                    </div>
                `;
                modalEntitiesList.appendChild(col);
            });
        }

        if (rels.length === 0) {
            modalRelsList.innerHTML = '<li class="list-group-item bg-transparent text-muted px-0">Nenhuma relação detectada entre entidades neste trecho.</li>';
        } else {
            rels.forEach((rel, idx) => {
                const li = document.createElement('li');
                li.className = 'list-group-item bg-transparent text-subtle px-0 d-flex align-items-center justify-content-between';
                li.innerHTML = `
                    <div>
                        <strong>${escHtml(rel.source_name)}</strong> 
                        <span class="badge bg-outline-primary mx-1" style="border:1px solid var(--cbr-primary);color:var(--cbr-primary)">${escHtml(rel.relationship_type)}</span> 
                        <strong>${escHtml(rel.target_name)}</strong>
                    </div>
                    <span class="badge bg-secondary">${Math.round((rel.confidence || 0.85)*100)}%</span>
                `;
                modalRelsList.appendChild(li);
            });
        }
    }

    // 7. Confirmação das Entidades Selecionadas no Grafo (Spec 8)
    if (btnConfirmEntities) {
        btnConfirmEntities.addEventListener('click', function () {
            if (!lastRegionResult) return;

            const selectedEntities = [];
            document.querySelectorAll('.chk-region-entity:checked').forEach(chk => {
                const idx = parseInt(chk.value, 10);
                if (lastRegionResult.entities[idx]) {
                    selectedEntities.push(lastRegionResult.entities[idx]);
                }
            });

            btnConfirmEntities.disabled = true;
            btnConfirmEntities.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando no Grafo...';

            const payload = {
                transcription: modalTranscript.value,
                entities: selectedEntities,
                relationships: lastRegionResult.relationships || [],
            };

            fetch(getBaseUrl() + `api/documentos/${docId}/confirmar-entidades-regiao`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(r => r.json())
            .then(data => {
                btnConfirmEntities.disabled = false;
                btnConfirmEntities.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirmar e Adicionar ao Grafo';

                if (data.success) {
                    if (regionModal) regionModal.hide();
                    alert(data.message || 'Entidades e relações adicionadas ao Grafo com sucesso!');

                    if (modalTranscript.value.trim()) {
                        textarea.value += (textarea.value ? "\n\n" : "") + `[TRANSCRIÇÃO HTR RECORTE PÁG. ${currentPage}]:\n` + modalTranscript.value.trim();
                        textarea.dispatchEvent(new Event('input'));
                    }
                    resetCrop();
                } else {
                    alert('Erro ao gravar entidades: ' + (data.error || 'Falha.'));
                }
            })
            .catch(() => {
                btnConfirmEntities.disabled = false;
                btnConfirmEntities.innerHTML = '<i class="bi bi-check-circle-fill"></i> Confirmar e Adicionar ao Grafo';
                alert('Falha de conexão ao salvar entidades.');
            });
        });
    }

    // 8. Salvar Transcrição no Repositório
    btnSaveText.addEventListener('click', function () {
        const text = textarea.value;
        if (!text.trim()) {
            alert('O texto da transcrição não pode estar vazio.');
            return;
        }

        btnSaveText.disabled = true;
        btnSaveText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando no Repositório...';

        const formData = new FormData();
        formData.append('conteudo_transcrito', text);

        fetch(getBaseUrl() + `api/documentos/${docId}/pagina/${currentPage}/salvar-texto`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            btnSaveText.disabled = false;
            btnSaveText.innerHTML = '<i class="bi bi-save-fill"></i> Salvar Transcrição no Repositório';

            if (data.success) {
                alert(data.message || 'Transcrição salva com sucesso no repositório!');
            } else {
                alert('Erro ao salvar transcrição: ' + (data.error || 'Falha.'));
            }
        })
        .catch(() => {
            btnSaveText.disabled = false;
            btnSaveText.innerHTML = '<i class="bi bi-save-fill"></i> Salvar Transcrição no Repositório';
            alert('Erro de rede ao salvar a transcrição.');
        });
    });

    // 9. Extrair Página Completa por IA
    if (btnExtractFull) {
        btnExtractFull.addEventListener('click', function () {
            if (!confirm('Deseja executar a extração de entidades e relações por IA sobre o texto do documento?')) {
                return;
            }

            btnExtractFull.disabled = true;
            btnExtractFull.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processando por IA...';

            fetch(getBaseUrl() + `api/documentos/pendentes/extrair/${docId}`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                btnExtractFull.disabled = false;
                btnExtractFull.innerHTML = '<i class="bi bi-cpu-fill"></i> Extrair Página Inteira com IA';

                if (data.success) {
                    alert(`Extração por IA concluída!\n\nEntidades no Grafo: ${data.entitiesExtracted}\nRelações no Grafo: ${data.relsExtracted}`);
                } else {
                    alert('Erro na extração: ' + (data.error || 'Falha.'));
                }
            })
            .catch(() => {
                btnExtractFull.disabled = false;
                btnExtractFull.innerHTML = '<i class="bi bi-cpu-fill"></i> Extrair Página Inteira com IA';
                alert('Erro de conexão com o servidor.');
            });
        });
    }

    function escHtml(str) {
        return String(str || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
});

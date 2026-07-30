/**
 * Cerebro — Lógica JS Local para Gestão de Documentos Pendentes e Exclusão em Cascata (Spec 6)
 * Zero CDN — Utiliza Fetch API nativa
 */
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('modalTextEditor');
    let textModal = null;
    if (modalElement && typeof bootstrap !== 'undefined') {
        textModal = new bootstrap.Modal(modalElement);
    }

    const modalDocId          = document.getElementById('modalDocId');
    const modalDocName        = document.getElementById('modalDocName');
    const modalDocText        = document.getElementById('modalDocText');
    const modalCharCounter    = document.getElementById('modalCharCounter');
    const btnSaveText         = document.getElementById('btnSaveText');
    const btnExtractFromModal = document.getElementById('btnExtractFromModal');
    const btnProcessAll       = document.getElementById('btnProcessAll');
    const btnClearAllDatabase = document.getElementById('btnClearAllDatabase');

    function getBaseUrl() {
        const url = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || '/'));
        return url.replace(/\/+$/, '') + '/';
    }

    // 1. Abrir Modal de Edição de Texto
    document.querySelectorAll('.btn-view-text').forEach(button => {
        button.addEventListener('click', function () {
            const id   = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const text = this.getAttribute('data-text');

            if (modalDocId) modalDocId.value = id;
            if (modalDocName) modalDocName.textContent = name;
            if (modalDocText) {
                modalDocText.value = text;
                updateCharCounter(text.length);
            }

            if (textModal) textModal.show();
        });
    });

    // 2. Contador de Caracteres no Modal
    if (modalDocText) {
        modalDocText.addEventListener('input', function () {
            updateCharCounter(this.value.length);
        });
    }

    function updateCharCounter(count) {
        if (modalCharCounter) {
            modalCharCounter.textContent = count.toLocaleString('pt-BR') + ' caracteres';
        }
    }

    // 3. Salvar Texto Alterado no Repositório
    if (btnSaveText) {
        btnSaveText.addEventListener('click', function () {
            saveModalText(false);
        });
    }

    if (btnExtractFromModal) {
        btnExtractFromModal.addEventListener('click', function () {
            saveModalText(true);
        });
    }

    function saveModalText(triggerExtraction = false) {
        const id   = modalDocId.value;
        const text = modalDocText.value;

        if (!id || !text.trim()) {
            alert('O texto do documento não pode estar vazio.');
            return;
        }

        const formData = new FormData();
        formData.append('document_id', id);
        formData.append('conteudo_transcrito', text);

        btnSaveText.disabled = true;
        btnSaveText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';

        fetch(getBaseUrl() + 'api/documentos/pendentes/salvar-texto', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            btnSaveText.disabled = false;
            btnSaveText.innerHTML = '<i class="bi bi-save me-1"></i> Salvar Alterações no Repositório';

            if (data.success) {
                const rowBtn = document.querySelector(`.btn-view-text[data-id="${id}"]`);
                if (rowBtn) {
                    rowBtn.setAttribute('data-text', text);
                }

                if (triggerExtraction) {
                    if (textModal) textModal.hide();
                    triggerSingleExtraction(id);
                } else {
                    alert('Transcrição atualizada com sucesso no repositório!');
                    if (textModal) textModal.hide();
                }
            } else {
                alert('Erro ao salvar texto: ' + (data.error || 'Erro desconhecido.'));
            }
        })
        .catch(err => {
            btnSaveText.disabled = false;
            btnSaveText.innerHTML = '<i class="bi bi-save me-1"></i> Salvar Alterações no Repositório';
            alert('Falha de conexão ao salvar texto.');
        });
    }

    // 4. Extração Individual por IA
    document.querySelectorAll('.btn-extract-single').forEach(button => {
        button.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            triggerSingleExtraction(id, this);
        });
    });

    function triggerSingleExtraction(docId, btnElement = null) {
        const targetBtn = btnElement || document.querySelector(`.btn-extract-single[data-id="${docId}"]`);
        if (targetBtn) {
            targetBtn.disabled = true;
            targetBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analisando por IA...';
        }

        fetch(getBaseUrl() + `api/documentos/pendentes/extrair/${docId}`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.getElementById(`docRow-${docId}`);
                if (row) {
                    row.classList.add('table-success');
                    setTimeout(() => {
                        row.remove();
                        checkEmptyTable();
                    }, 800);
                }
                alert(`Extração concluída!\n\nEntidades Extraídas: ${data.entitiesExtracted}\nRelações no Grafo: ${data.relsExtracted}`);
            } else {
                if (targetBtn) {
                    targetBtn.disabled = false;
                    targetBtn.innerHTML = '<i class="bi bi-cpu me-1"></i> Tentar Novamente';
                }
                alert('Erro na extração: ' + (data.error || 'Falha ao processar IA.'));
            }
        })
        .catch(err => {
            if (targetBtn) {
                targetBtn.disabled = false;
                targetBtn.innerHTML = '<i class="bi bi-cpu me-1"></i> Tentar Novamente';
            }
            alert('Erro de rede ao comunicar com o servidor.');
        });
    }

    // 5. Excluir Documento e Todo o seu Rastro em Cascata
    document.querySelectorAll('.btn-delete-doc').forEach(button => {
        button.addEventListener('click', function () {
            const id   = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');

            if (!confirm(`Deseja APAGAR DEFINITIVAMENTE o documento:\n"${name}"\n\nIsso apagará o arquivo físico no servidor e todas as conexões geradas por ele no grafo!`)) {
                return;
            }

            this.disabled = true;

            fetch(getBaseUrl() + `documentos/${id}/deletar`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.getElementById(`docRow-${id}`);
                    if (row) {
                        row.remove();
                        checkEmptyTable();
                    }
                    alert(data.message);
                } else {
                    this.disabled = false;
                    alert('Erro ao excluir documento: ' + (data.error || 'Falha.'));
                }
            })
            .catch(err => {
                this.disabled = false;
                alert('Erro de comunicação com o servidor.');
            });
        });
    });

    // 6. Zerar Toda a Base de Ingestões e Rastreos (Coordenador)
    if (btnClearAllDatabase) {
        btnClearAllDatabase.addEventListener('click', function () {
            if (!confirm('⚠️ ATENÇÃO: Deseja ZERAR COMPLETAMENTE todos os documentos, entidades extraídas, conexões do grafo e arquivos salvos?\n\nEsta ação é irreversível!')) {
                return;
            }

            btnClearAllDatabase.disabled = true;
            btnClearAllDatabase.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Zerando base...';

            fetch(getBaseUrl() + 'api/limpar-banco-total', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    btnClearAllDatabase.disabled = false;
                    btnClearAllDatabase.innerHTML = '<i class="bi bi-trash3-fill"></i> Zerar Toda a Base';
                    alert('Erro ao zerar base: ' + (data.error || 'Acesso negado.'));
                }
            })
            .catch(err => {
                btnClearAllDatabase.disabled = false;
                btnClearAllDatabase.innerHTML = '<i class="bi bi-trash3-fill"></i> Zerar Toda a Base';
                alert('Erro de comunicação ao zerar a base.');
            });
        });
    }

    // 7. Processar Todos os Pendentes em Lote
    if (btnProcessAll) {
        btnProcessAll.addEventListener('click', function () {
            if (!confirm('Deseja iniciar a extração por IA de TODOS os documentos pendentes?')) {
                return;
            }

            const progressContainer = document.getElementById('batchProgressContainer');
            const progressBar      = document.getElementById('batchProgressBar');
            const progressPercent  = document.getElementById('batchProgressPercent');
            const progressStatus   = document.getElementById('batchProgressStatus');

            if (progressContainer) progressContainer.classList.remove('d-none');
            btnProcessAll.disabled = true;

            fetch(getBaseUrl() + 'api/documentos/pendentes/processar-todos', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (progressBar) progressBar.style.width = '100%';
                    if (progressPercent) progressPercent.textContent = '100%';
                    if (progressStatus) progressStatus.textContent = data.message;

                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    btnProcessAll.disabled = false;
                    alert('Erro no processamento em lote: ' + (data.error || 'Falha.'));
                }
            })
            .catch(err => {
                btnProcessAll.disabled = false;
                alert('Falha na requisição de lote.');
            });
        });
    }

    function checkEmptyTable() {
        const remainingRows = document.querySelectorAll('tbody tr');
        if (remainingRows.length === 0) {
            window.location.reload();
        }
    }
});

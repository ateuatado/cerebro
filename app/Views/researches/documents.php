<?php

/**
 * Cerebro — Views/researches/documents.php
 * Interface para vincular documentos primários a uma Pesquisa
 */

$research            = $research ?? [];
$allDocuments        = $allDocuments ?? [];
$attachedDocumentIds = $attachedDocumentIds ?? [];

ob_start();
?>

<div class="fade-in-up">
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Documentos da Pesquisa</h1>
            <p class="cbr-page-subtitle">
                Vinculando acervo documental à pesquisa <strong><?= esc($research['title']) ?></strong>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('grafo?research_id=' . $research['id']) ?>" class="btn btn-outline-info">
                <i class="bi bi-diagram-3 me-1"></i> Ver Grafo Desta Pesquisa
            </a>
            <a href="<?= base_url('pesquisas') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>

    <div class="cbr-card">
        <form action="<?= base_url('pesquisas/' . $research['id'] . '/documentos') ?>" method="POST">
            <?= csrf_field() ?>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-white">Selecione os Documentos Associados</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-light" id="btn-select-all">Selecionar Todos</button>
                    <button type="button" class="btn btn-sm btn-outline-light" id="btn-deselect-all">Desmarcar Todos</button>
                </div>
            </div>

            <div class="mb-3">
                <input type="text" class="form-control bg-dark text-white border-secondary" id="doc-search" placeholder="🔍 Filtrar documentos por título...">
            </div>

            <?php if (empty($allDocuments)): ?>
                <p class="text-subtle my-4 text-center">Nenhum documento cadastrado no acervo até o momento.</p>
            <?php else: ?>
                <div class="list-group mb-4" style="max-height: 450px; overflow-y: auto;" id="doc-list">
                    <?php foreach ($allDocuments as $doc): ?>
                        <?php 
                            $isChecked = in_array($doc['id'], $attachedDocumentIds);
                            $attrs = is_string($doc['attributes']) ? json_decode($doc['attributes'], true) : ($doc['attributes'] ?? []);
                            $sub = $attrs['titulo'] ?? $doc['name'];
                        ?>
                        <label class="list-group-item list-group-item-action bg-dark text-white border-secondary d-flex align-items-center gap-3 doc-item">
                            <input class="form-check-input flex-shrink-0 doc-checkbox" 
                                   type="checkbox" 
                                   name="documents[]" 
                                   value="<?= $doc['id'] ?>" 
                                   <?= $isChecked ? 'checked' : '' ?>>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-white mb-0 doc-title"><?= esc($doc['name']) ?></div>
                                <div class="small text-subtle">
                                    <i class="bi bi-file-earmark-text me-1"></i> ID #<?= $doc['id'] ?>
                                    <?php if (!empty($sub) && $sub !== $doc['name']): ?>
                                        &bull; <?= esc($sub) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge <?= $doc['status'] === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                <?= $doc['status'] === 'confirmed' ? 'Confirmado' : 'Hipótese' ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i> Salvar Vínculos
                </button>
                <a href="<?= base_url('pesquisas') ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('doc-search');
    const docItems = document.querySelectorAll('.doc-item');
    const btnSelectAll = document.getElementById('btn-select-all');
    const btnDeselectAll = document.getElementById('btn-deselect-all');
    const checkboxes = document.querySelectorAll('.doc-checkbox');

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            docItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(term) ? 'flex' : 'none';
            });
        });
    }

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', () => {
            checkboxes.forEach(cb => {
                if (cb.closest('.doc-item').style.display !== 'none') {
                    cb.checked = true;
                }
            });
        });
    }

    if (btnDeselectAll) {
        btnDeselectAll.addEventListener('click', () => {
            checkboxes.forEach(cb => {
                if (cb.closest('.doc-item').style.display !== 'none') {
                    cb.checked = false;
                }
            });
        });
    }
});
</script>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'       => 'Documentos da Pesquisa',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => base_url('/')],
        ['label' => 'Pesquisas', 'url' => base_url('pesquisas')],
        ['label' => esc($research['title']), 'url' => '']
    ],
    'content'     => $content,
]);
?>

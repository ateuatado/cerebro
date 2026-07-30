<?php
/**
 * Cerebro — Views/documents/batch.php
 * Ingestão e Extração em Lote via Web Interface (Drag & Drop + IA)
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

ob_start();
?>

<div class="fade-in-up">

    <!-- Header -->
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">
                <i class="bi bi-box-seam" style="color:var(--cbr-primary)"></i>
                Ingestão em Lote por IA
            </h1>
            <p class="cbr-page-subtitle">
                Envie múltiplos documentos de uma vez. A IA lerá o conteúdo e criará o grafo de hipóteses em tempo real.
            </p>
        </div>
        <div class="d-flex gap-2">
            <form action="<?= base_url('documentos/reprocessar-tudo') ?>" method="post" onsubmit="return confirm('Relê todos os documentos cadastrados utilizando OCR + IA?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="btn-reprocess-all">
                    <i class="bi bi-arrow-repeat"></i> Reprocessar Existentes (OCR + IA)
                </button>
            </form>
            <a href="<?= base_url('grafo') ?>" class="btn btn-outline-primary d-flex align-items-center gap-2" id="btn-view-graph-top">
                <i class="bi bi-diagram-3"></i> Ver Grafo Visual
            </a>
            <?php if ($role === 'coordenador') : ?>
                <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-2" onclick="clearFullDatabase();">
                    <i class="bi bi-trash3-fill"></i> Zerar Toda a Base
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dropzone Area -->
    <div class="cbr-card mb-4" id="dropzone-card" data-upload-url="<?= base_url('api/documentos/upload-item') ?>" style="border: 2px dashed var(--cbr-primary); background: var(--cbr-surface-2); text-align: center; padding: 3rem 1.5rem; transition: all var(--cbr-transition); cursor: pointer;">
        <input type="file" id="batch-file-input" multiple accept=".txt,.md,.json,.csv,.pdf,.jpg,.jpeg,.png,.webp,.bmp" style="display:none">
        
        <div id="dropzone-content">
            <div style="width:64px;height:64px;border-radius:50%;background:var(--cbr-primary-dim);color:var(--cbr-primary);display:inline-flex;align-items:center;justify-content:center;font-size:2rem;margin-bottom:1rem">
                <i class="bi bi-cloud-arrow-up-fill"></i>
            </div>
            <h2 style="font-size:1.25rem;font-weight:700;color:var(--cbr-text);margin-bottom:.5rem">
                Arraste e solte seus documentos aqui
            </h2>
            <p style="font-size:.875rem;color:var(--cbr-text-muted);margin-bottom:1.5rem">
                Suporta múltiplos arquivos <code>.pdf</code>, <code>.jpg</code>, <code>.jpeg</code>, <code>.png</code>, <code>.txt</code>, <code>.md</code>, <code>.json</code> e <code>.csv</code>
            </p>
            <button type="button" class="btn btn-primary btn-lg" id="btn-browse-files">
                <i class="bi bi-folder2-open me-2"></i> Selecionar Arquivos da Pasta
            </button>
        </div>
    </div>

    <!-- Progresso Geral (Escondido até iniciar) -->
    <div class="cbr-card mb-4" id="batch-progress-card" style="display:none">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem">
            <h3 style="font-size:1rem;font-weight:700;color:var(--cbr-text);margin:0" id="batch-status-title">
                Processando Lote...
            </h3>
            <span style="font-size:.875rem;font-weight:600;color:var(--cbr-primary)" id="batch-status-counts">
                0 / 0 concluídos
            </span>
        </div>

        <div class="progress mb-3" style="height:12px;background:var(--cbr-surface-2);border-radius:6px">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="batch-progress-bar" role="progressbar" style="width: 0%; background:var(--cbr-primary)"></div>
        </div>

        <!-- Resumo em cards pequenos -->
        <div class="row g-2 text-center" style="font-size:.8125rem">
            <div class="col-4">
                <div style="padding:.5rem;background:var(--cbr-surface-2);border-radius:var(--cbr-radius-sm)">
                    <span class="text-subtle d-block">Entidades Extraídas</span>
                    <strong style="font-size:1.125rem;color:var(--cbr-text)" id="count-entities">0</strong>
                </div>
            </div>
            <div class="col-4">
                <div style="padding:.5rem;background:var(--cbr-surface-2);border-radius:var(--cbr-radius-sm)">
                    <span class="text-subtle d-block">Relações Criadas</span>
                    <strong style="font-size:1.125rem;color:var(--cbr-text)" id="count-rels">0</strong>
                </div>
            </div>
            <div class="col-4">
                <div style="padding:.5rem;background:var(--cbr-hypothesis-bg);border:1px solid rgba(245,158,11,.25);border-radius:var(--cbr-radius-sm)">
                    <span style="color:var(--cbr-hypothesis)" class="d-block">Hipóteses no Grafo</span>
                    <strong style="font-size:1.125rem;color:var(--cbr-hypothesis)" id="count-hypotheses">0</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Lista de Fila de Arquivos -->
    <div class="cbr-card p-0 overflow-hidden" id="queue-card" style="display:none">
        <div style="padding:.875rem 1.125rem;background:var(--cbr-surface-2);border-bottom:1px solid var(--cbr-border);display:flex;align-items:center;justify-content:space-between">
            <h3 style="font-size:.9375rem;font-weight:600;color:var(--cbr-text);margin:0">
                <i class="bi bi-list-task me-1"></i> Fila de Processamento
            </h3>
            <a href="<?= base_url('grafo') ?>" class="btn btn-sm btn-primary d-none" id="btn-view-graph-summary">
                <i class="bi bi-diagram-3"></i> Ver no Grafo
            </a>
        </div>
        <ul class="cbr-recent-list" id="batch-queue-list" style="max-height:400px;overflow-y:auto">
            <!-- Itens inseridos dinamicamente via JS -->
        </ul>
    </div>

</div>

<script>
    window.BASE_URL = '<?= base_url() ?>/';
</script>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Ingestão em Lote por IA',
    'breadcrumbs'=> [
        ['label'=>'Dashboard',  'url'=>base_url('/')],
        ['label'=>'Documentos', 'url'=>base_url('documentos')],
        ['label'=>'Upload em Lote', 'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css', 'dashboard.css'],
    'pageJs'     => ['batch-ingest.js'],
]);
?>

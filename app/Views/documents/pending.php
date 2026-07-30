<?php
/**
 * Cerebro — Views/documents/pending.php
 * Repositório e Fila de Documentos Pendentes de Extração (Spec 6)
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

ob_start();
?>

<div class="fade-in-up">
    <!-- Cabeçalho -->
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">
                <i class="bi bi-journal-text" style="color:var(--cbr-warning)"></i>
                Repositório de Documentos Pendentes
            </h1>
            <p class="cbr-page-subtitle">
                Documentos com transcrição/OCR salvos no repositório aguardando extração por IA ou reprocessamento manual
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('documentos/lote') ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="bi bi-upload"></i> Fazer Novo Upload
            </a>
            <?php if (!empty($pendingDocs)) : ?>
                <button id="btnProcessAll" class="btn btn-warning text-dark font-weight-bold shadow-sm d-flex align-items-center gap-2">
                    <i class="bi bi-cpu-fill"></i> Processar Todos por IA (<?= $totalPending ?>)
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cards de Métricas -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="cbr-card text-light d-flex align-items-center">
                <div class="rounded-circle p-3 me-3 text-warning" style="background: rgba(245, 158, 11, 0.15);">
                    <i class="bi bi-hourglass-split display-6"></i>
                </div>
                <div>
                    <div class="text-subtle small">Documentos Pendentes</div>
                    <div class="h3 font-weight-bold mb-0 text-warning"><?= number_format($totalPending) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="cbr-card text-light d-flex align-items-center">
                <div class="rounded-circle p-3 me-3 text-info" style="background: rgba(59, 130, 246, 0.15);">
                    <i class="bi bi-file-earmark-code display-6"></i>
                </div>
                <div>
                    <div class="text-subtle small">Texto Transcrito Salvo</div>
                    <div class="h3 font-weight-bold mb-0 text-info"><?= number_format($totalChars) ?> <span class="fs-6 font-weight-normal text-muted">chars</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Progresso Global -->
    <div id="batchProgressContainer" class="cbr-card mb-4 d-none">
        <div class="d-flex justify-content-between mb-2">
            <span class="text-light font-weight-bold" id="batchProgressStatus">Processando extrações em lote...</span>
            <span class="text-warning font-weight-bold" id="batchProgressPercent">0%</span>
        </div>
        <div class="progress" style="height: 12px; background: var(--cbr-surface-2);">
            <div id="batchProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width: 0%"></div>
        </div>
    </div>

    <!-- Lista de Documentos Pendentes -->
    <div class="cbr-card p-0 overflow-hidden">
        <div style="padding:.875rem 1.125rem;background:var(--cbr-surface-2);border-bottom:1px solid var(--cbr-border);display:flex;align-items:center;justify-content:space-between">
            <h3 style="font-size:.9375rem;font-weight:600;color:var(--cbr-text);margin:0">
                <i class="bi bi-list-task me-2 text-warning"></i> Fila de Transcrições no Repositório
            </h3>
            <span class="badge bg-secondary"><?= count($pendingDocs) ?> itens</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pendingDocs)) : ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-check-circle-fill text-success display-4 mb-3 d-block"></i>
                    <h5 class="text-light">Nenhum documento pendente de extração!</h5>
                    <p class="small">Todos os documentos do repositório já foram processados pela IA ou validados.</p>
                    <a href="<?= base_url('documentos/lote') ?>" class="btn btn-primary mt-2">
                        <i class="bi bi-cloud-upload me-1"></i> Ingerir Novos Documentos
                    </a>
                </div>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0 border-secondary">
                        <thead>
                            <tr class="text-muted small">
                                <th style="width: 40px;">#</th>
                                <th>Nome do Arquivo / Documento</th>
                                <th>Formato</th>
                                <th>Texto Transcrito</th>
                                <th>Status</th>
                                <th class="text-end" style="width: 320px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingDocs as $idx => $doc) : ?>
                                <tr id="docRow-<?= $doc['id'] ?>">
                                    <td class="text-muted small"><?= $idx + 1 ?></td>
                                    <td>
                                        <div class="font-weight-bold text-light"><?= esc($doc['name']) ?></div>
                                        <small class="text-muted">Cadastrado em <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-outline-secondary border border-secondary text-light">
                                            <?= esc($doc['formato']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-info font-weight-bold"><?= number_format($doc['tamanho_caracteres']) ?> caracteres</div>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;">
                                            <?= esc(mb_substr($doc['conteudo_transcrito'], 0, 60)) ?>...
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($doc['status'] === 'error') : ?>
                                            <span class="badge bg-danger" title="<?= esc($doc['erro']) ?>">
                                                <i class="bi bi-exclamation-triangle me-1"></i> Erro Anterior
                                            </span>
                                        <?php else : ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-clock me-1"></i> Texto Pronto (Pendente)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-info me-1 btn-view-text" 
                                                data-id="<?= $doc['id'] ?>"
                                                data-name="<?= esc($doc['name']) ?>"
                                                data-text="<?= esc($doc['conteudo_transcrito']) ?>">
                                            <i class="bi bi-pencil-square me-1"></i> Ler/Editar Texto
                                        </button>
                                        <button class="btn btn-sm btn-warning text-dark font-weight-bold btn-extract-single" 
                                                data-id="<?= $doc['id'] ?>">
                                            <i class="bi bi-cpu me-1"></i> Extrair por IA
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal para Visualizar e Editar o Texto Bruto Transcrito -->
<div class="modal fade" id="modalTextEditor" tabindex="-1" aria-labelledby="modalTextEditorLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTextEditorLabel">
                    <i class="bi bi-file-earmark-text text-info me-2"></i> Repositório de Texto: <span id="modalDocName" class="text-warning"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalDocId" value="">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label for="modalDocText" class="form-label text-muted small mb-0">
                        Transcrição integral lida pelo OCR/Parser (edite o texto bruto se desejar corrigir termos):
                    </label>
                    <span class="badge bg-secondary" id="modalCharCounter">0 caracteres</span>
                </div>
                <textarea id="modalDocText" class="form-control bg-black text-light border-secondary font-monospace" rows="15" style="resize: vertical; font-size: 0.9rem;"></textarea>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" id="btnSaveText" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Salvar Alterações no Repositório
                </button>
                <button type="button" id="btnExtractFromModal" class="btn btn-warning text-dark font-weight-bold">
                    <i class="bi bi-cpu-fill me-1"></i> Salvar & Extrair por IA Agora
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_URL = '<?= base_url() ?>/';
</script>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Documentos Pendentes de Extração',
    'breadcrumbs'=> [
        ['label'=>'Dashboard',  'url'=>base_url('/')],
        ['label'=>'Documentos', 'url'=>base_url('documentos')],
        ['label'=>'Pendentes de Extração', 'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css', 'dashboard.css', 'pending.css'],
    'pageJs'     => ['pending.js'],
]);
?>

<?php
/**
 * Cerebro — Views/documents/review_workspace.php
 * Workspace Interativo de Transcrição Histórica (Spec 7 & Spec 8)
 * Permite rotação de página, seleção por região (Crop Tool com IA) e extração de entidades em 1-clique.
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

$doc               = $doc ?? [];
$attributes        = $attributes ?? [];
$totalPages        = $totalPages ?? 1;
$transcriptionText = $transcriptionText ?? '';
$docId             = $doc['id'] ?? 0;
$docName           = $doc['name'] ?? 'Documento sem nome';

ob_start();
?>

<div class="fade-in-up" data-doc-id="<?= $docId ?>" data-total-pages="<?= $totalPages ?>" id="workspace-container">

    <!-- Header -->
    <div class="cbr-page-header mb-3">
        <div>
            <h1 class="cbr-page-title d-flex align-items-center gap-2">
                <i class="bi bi-card-heading" style="color:var(--cbr-primary)"></i>
                Workspace de Transcrição & Revisionismo
            </h1>
            <p class="cbr-page-subtitle">
                <?= esc($docName) ?> · Fonte Primária Histórica
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('documentos/pendentes') ?>" class="btn btn-outline-secondary d-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Voltar aos Pendentes
            </a>
            <?php if ($role === 'coordenador') : ?>
                <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-1" onclick="deleteDocumentFromWorkspace(<?= $docId ?>, '<?= esc($docName, 'js') ?>');">
                    <i class="bi bi-trash3-fill"></i> Excluir Documento
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Layout Dividido em 2 Colunas -->
    <div class="row g-3" id="review-layout">

        <!-- Coluna Esquerda: Visualizador de Imagem com Rotação e Crop -->
        <div class="col-lg-7">
            <div class="cbr-card p-0 overflow-hidden d-flex flex-column" style="height: calc(100vh - 180px); min-height: 550px;">
                
                <!-- Barra de Ferramentas do Visualizador -->
                <div class="cbr-viewer-toolbar p-2 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2" style="background:var(--cbr-surface-2)">
                    
                    <!-- Paginação -->
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnPrevPage" title="Página Anterior">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <span style="font-size:.875rem;font-weight:600;color:var(--cbr-text)" class="px-2">
                            Pág. <span id="currentPageNum">1</span> / <?= $totalPages ?>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnNextPage" title="Próxima Página">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </div>

                    <!-- Botões de Rotação Visual e Física -->
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRotateCcw" title="Girar 90° Anti-horário">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> -90°
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRotateCw" title="Girar 90° Horário">
                            <i class="bi bi-arrow-clockwise me-1"></i> +90°
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRotate180" title="Girar 180°">
                            <i class="bi bi-arrow-repeat me-1"></i> 180°
                        </button>
                    </div>

                    <!-- Ferramenta de Recorte por Região (Crop Tool) -->
                    <div class="d-flex align-items-center gap-1">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnToggleCrop" title="Desenhar caixa sobre manuscrito/tabela">
                            <i class="bi bi-crop me-1"></i> Recortar Região
                        </button>
                        <button type="button" class="btn btn-sm btn-warning d-none" id="btnExtractCrop" title="Submeter área selecionada à IA">
                            <i class="bi bi-cpu me-1"></i> Extrair Texto (IA)
                        </button>
                        <button type="button" class="btn btn-sm btn-success d-none" id="btnExtractRegionEntities" title="Extrair Entidades e Relações do Recorte para o Grafo">
                            <i class="bi bi-diagram-3-fill me-1"></i> ✨ Extrair Entidades (IA)
                        </button>
                    </div>

                </div>

                <!-- Container do Canvas de Imagem com Zoom e Crop -->
                <div class="cbr-viewer-canvas flex-grow-1 position-relative overflow-auto p-3 text-center" style="background:#0f172a;" id="imageCanvasWrapper">
                    
                    <div id="cropOverlay" class="position-absolute d-none" style="border: 2px dashed #f59e0b; background: rgba(245, 158, 11, 0.15); cursor: crosshair; z-index: 10;">
                        <span class="badge bg-warning text-dark position-absolute top-0 start-0 translate-middle-y ms-1" style="font-size:.65rem">Área Selecionada</span>
                    </div>

                    <img id="pageImageViewer" 
                         src="<?= base_url("api/documentos/{$docId}/pagina/1/imagem") ?>" 
                         alt="Página do documento histórico" 
                         style="max-width: 100%; height: auto; border-radius: var(--cbr-radius-sm); box-shadow: 0 4px 20px rgba(0,0,0,0.5); transition: transform 0.2s ease-in-out; cursor: default;">

                    <div id="imageLoadingSpinner" class="position-absolute top-50 start-50 translate-middle d-none text-light">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="mt-2 text-subtle fw-semibold" style="font-size:.8125rem">Carregando imagem...</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Coluna Direita: Editor de Transcrição Paginado -->
        <div class="col-lg-5">
            <div class="cbr-card p-3 d-flex flex-column" style="height: calc(100vh - 180px); min-height: 550px;">
                
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h3 style="font-size:.9375rem;font-weight:700;color:var(--cbr-text);margin:0" class="d-flex align-items-center gap-2">
                        <i class="bi bi-pencil-square" style="color:var(--cbr-primary)"></i>
                        Transcrição do Documento
                    </h3>
                    <span id="charCountBadge" class="badge bg-secondary" style="font-size:.75rem">
                        <?= number_format(mb_strlen($transcriptionText), 0, ',', '.') ?> caracteres
                    </span>
                </div>

                <p style="font-size:.75rem;color:var(--cbr-text-muted);" class="mb-2">
                    Edite ou corrija o texto extraído. Trechos extraídos de regiões selecionadas serão anexados diretamente abaixo.
                </p>

                <!-- Textarea da Transcrição -->
                <textarea id="transcriptionTextarea" 
                          class="form-control flex-grow-1 font-monospace" 
                          style="background:var(--cbr-surface-2);color:var(--cbr-text);border-color:var(--cbr-border);font-size:.875rem;line-height:1.5;resize:none;padding:.875rem;" 
                          placeholder="Digite ou cole aqui a transcrição do documento..."><?= esc($transcriptionText) ?></textarea>

                <!-- Botões de Ação Inferiores -->
                <div class="mt-3 d-flex flex-column gap-2">
                    <button type="button" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2" id="btnSaveWorkspaceText">
                        <i class="bi bi-save-fill"></i> Salvar Transcrição no Repositório
                    </button>
                    <button type="button" class="btn btn-outline-warning w-100 d-flex align-items-center justify-content-center gap-2" id="btnExtractPageFullIa">
                        <i class="bi bi-cpu-fill"></i> Extrair Página Inteira com IA
                    </button>
                </div>

            </div>
        </div>

    </div>

</div>

<!-- Modal de Pre-visualização & Aprovação de Entidades da Região (Spec 8) -->
<div class="modal fade" id="modalRegionEntities" tabindex="-1" aria-labelledby="modalRegionEntitiesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" style="background:var(--cbr-surface-1);color:var(--cbr-text);border:1px solid var(--cbr-border)">
            <div class="modal-header border-bottom" style="background:var(--cbr-surface-2)">
                <h5 class="modal-title fs-6 fw-bold" id="modalRegionEntitiesLabel">
                    <i class="bi bi-diagram-3-fill text-success me-2"></i>
                    Entidades & Grafo Encontrados na Região
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-3">
                
                <!-- Transcrição da Região -->
                <div class="mb-3">
                    <label class="form-label text-subtle fw-semibold" style="font-size:.8125rem">Transcrição HTR da Área Selecionada:</label>
                    <textarea id="modalRegionTranscript" class="form-control font-monospace" style="height:90px;font-size:.8125rem;background:var(--cbr-surface-2);color:var(--cbr-text);border-color:var(--cbr-border)"></textarea>
                </div>

                <!-- Entidades Identificadas -->
                <div class="mb-3">
                    <h6 class="fw-bold" style="font-size:.875rem;color:var(--cbr-primary)">
                        <i class="bi bi-people-fill me-1"></i> Entidades Descobertas:
                    </h6>
                    <div id="modalRegionEntitiesList" class="row g-2">
                        <!-- Preenchido via JS -->
                    </div>
                </div>

                <!-- Conexões do Grafo -->
                <div>
                    <h6 class="fw-bold" style="font-size:.875rem;color:var(--cbr-accent)">
                        <i class="bi bi-share-fill me-1"></i> Relações para o Grafo:
                    </h6>
                    <ul id="modalRegionRelsList" class="list-group list-group-flush" style="font-size:.8125rem">
                        <!-- Preenchido via JS -->
                    </ul>
                </div>

            </div>
            <div class="modal-footer border-top" style="background:var(--cbr-surface-2)">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm d-flex align-items-center gap-1" id="btnConfirmRegionEntities">
                    <i class="bi bi-check-circle-fill"></i> Confirmar e Adicionar ao Grafo
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Revisão & Transcrição Histórica',
    'breadcrumbs'=> [
        ['label'=>'Dashboard',  'url'=>base_url('/')],
        ['label'=>'Documentos', 'url'=>base_url('documentos')],
        ['label'=>'Revisar',    'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css', 'review-workspace.css'],
    'pageJs'     => ['review-workspace.js'],
]);
?>

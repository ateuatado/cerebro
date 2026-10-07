<?php
/**
 * Cerebro — Views/entities/show.php
 * Detalhe de uma entidade: atributos, relações, fonte documental
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

$entity          = $entity          ?? [];
$relationsAsSource = $relationsAsSource ?? [];
$relationsAsTarget = $relationsAsTarget ?? [];
$relatedEntities   = $relatedEntities   ?? [];

$typeConfig = [
    'person'   => ['icon'=>'bi-person-fill',      'css'=>'person',   'bg'=>'var(--cbr-person-bg)',   'color'=>'var(--cbr-person)',   'label'=>'Pessoa'],
    'location' => ['icon'=>'bi-geo-alt-fill',      'css'=>'location', 'bg'=>'var(--cbr-location-bg)', 'color'=>'var(--cbr-location)', 'label'=>'Local'],
    'event'    => ['icon'=>'bi-calendar-event',    'css'=>'event',    'bg'=>'var(--cbr-event-bg)',    'color'=>'var(--cbr-event)',    'label'=>'Evento'],
    'document' => ['icon'=>'bi-file-earmark-text', 'css'=>'document', 'bg'=>'var(--cbr-document-bg)', 'color'=>'var(--cbr-document)', 'label'=>'Documento'],
];
$tc = $typeConfig[$entity['type'] ?? 'person'] ?? $typeConfig['person'];

// Rótulos amigáveis para atributos JSONB (inclui vocabulário Spec 11)
$attrLabels = [
    // person
    'ocupacao'               => 'Ocupação / Cargo',
    'patente'                => 'Patente / Graduação',
    'cargo'                  => 'Cargo ou Função',
    'unidade'                => 'Unidade / Batalhão',
    'faccao_politica'        => 'Facção Política',
    'apelido'                => 'Apelido / Alcunha',
    'naturalidade'           => 'Naturalidade',
    'nacionalidade'          => 'Nacionalidade',
    'data_nascimento'        => 'Data de Nascimento',
    'nascimento'             => 'Data de Nascimento',
    'filiacao'               => 'Filiação',
    'notas'                  => 'Notas',
    // location
    'tipo_local'             => 'Tipo de Local',
    'municipio'              => 'Município',
    'estado'                 => 'Estado / UF',
    'pais'                   => 'País',
    'endereco'               => 'Endereço',
    // event
    'data_evento'            => 'Data do Evento',
    'tipo_evento'            => 'Tipo de Evento',
    'desfecho'               => 'Desfecho',
    'local'                  => 'Local',
    'vitimas'                => 'Vítimas',
    // document
    'descricao'              => 'Descrição',
    'data'                   => 'Data',
    'titulo'                 => 'Título',
    'autor_responsavel'      => 'Autor / Responsável',
    'tipo_documento'         => 'Tipo de Documento',
    'instituicao_custodiadora'=> 'Instituição Custodiadora',
    'localizacao_arquivistica'=> 'Localização Arquivística',
    'data_acesso'            => 'Data de Acesso',
    // internal
    'conteudo_transcrito'    => 'Conteúdo Transcrito',
    'formato'                => 'Formato',
];

ob_start();
?>

<div class="fade-in-up">

    <!-- ─── Header do detalhe ──────────────────────────────────── -->
    <div class="cbr-entity-detail-header">
        <div class="cbr-entity-detail-icon"
             style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>"
             aria-hidden="true">
            <i class="bi <?= $tc['icon'] ?>"></i>
        </div>

        <div class="cbr-entity-detail-body">
            <h1 class="cbr-entity-detail-name"><?= esc($entity['name']) ?></h1>
            <div class="cbr-entity-detail-badges">
                <span class="badge-type badge-<?= $tc['css'] ?>"><?= $tc['label'] ?></span>
                <span class="<?= $entity['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                    <?= $entity['status'] === 'confirmed' ? '✅ Confirmado' : '🟡 Hipótese' ?>
                </span>
                <?php if (!empty($entity['created_at'])): ?>
                <span class="text-subtle" style="font-size:.75rem">
                    Criado em <?= date('d/m/Y', strtotime($entity['created_at'])) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="cbr-entity-detail-actions">
            <!-- Editar metadados (nome e atributos) — disponível sempre -->
            <button type="button"
                    class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"
                    data-bs-toggle="modal"
                    data-bs-target="#modalEditEntity"
                    id="btn-edit-entity">
                <i class="bi bi-pencil" aria-hidden="true"></i>
                <span>Editar</span>
            </button>

            <!-- Mesclar com outra entidade -->
            <button type="button"
                    class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1"
                    data-bs-toggle="modal"
                    data-bs-target="#modalMergeEntity"
                    id="btn-merge-entity">
                <i class="bi bi-union" aria-hidden="true"></i>
                <span>Mesclar</span>
            </button>

            <!-- Confirmar (coordenador, só hipóteses) -->
            <?php if ($role === 'coordenador' && $entity['status'] === 'hypothesis'): ?>
            <button class="btn-confirm"
                    data-confirm-entity="<?= $entity['id'] ?>"
                    data-entity-name="<?= esc($entity['name']) ?>"
                    data-confirm-url="<?= base_url('entidades/' . $entity['id'] . '/confirmar') ?>"
                    id="btn-confirm-entity">
                <i class="bi bi-patch-check" aria-hidden="true"></i>
                Confirmar como fato
            </button>
            <?php endif; ?>

            <!-- Processar com IA (para Documentos) -->
            <?php if ($entity['type'] === 'document'): ?>
            <form action="<?= base_url('documentos/' . $entity['id'] . '/extrair') ?>" method="post" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1" id="btn-ai-extract">
                    <i class="bi bi-robot" aria-hidden="true"></i>
                    <span>Processar com IA (DeepSeek)</span>
                </button>
            </form>
            <a href="<?= base_url('documentos/' . $entity['id'] . '/revisar') ?>"
               class="btn btn-warning btn-sm d-flex align-items-center gap-1 text-dark fw-bold"
               id="btn-ai-review">
                <i class="bi bi-crop" aria-hidden="true"></i>
                <span>✂️ Workspace de Transcrição & Crop IA</span>
            </a>
            <?php endif; ?>

            <a href="<?= base_url('relacoes/nova?origem=' . $entity['id']) ?>"
               class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1"
               id="btn-add-relation">
                <i class="bi bi-share" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Adicionar relação</span>
            </a>

            <form action="<?= base_url('entidades/' . $entity['id'] . '/deletar') ?>" method="post" style="display:inline" onsubmit="return confirm('Tem certeza que deseja APAGAR definitivamente \'<?= esc($entity['name']) ?>\' e todas as suas conexões no grafo?<?= $entity['type'] === 'document' ? ' Isto também apagará o arquivo físico no servidor!' : '' ?>');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1" id="btn-delete-entity">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    <span>Excluir</span>
                </button>
            </form>

            <a href="<?= base_url('entidades') ?>"
               class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1"
               id="btn-back-entities">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Voltar</span>
            </a>
        </div>
    </div>

    <!-- Grid de 2 colunas no desktop -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem" class="cbr-detail-grid">

        <!-- ─── Atributos ──────────────────────────────────────── -->
        <div class="cbr-detail-section">
            <div class="cbr-detail-section-header">
                <h2 class="cbr-detail-section-title">
                    <i class="bi bi-card-list" aria-hidden="true"></i>
                    Atributos
                </h2>
                <button type="button"
                        class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1 ms-auto"
                        data-bs-toggle="modal"
                        data-bs-target="#modalAddAttribute">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <span>Adicionar atributo</span>
                </button>
            </div>
            <div class="cbr-detail-section-body">
                <?php
                $attrs = is_string($entity['attributes'])
                    ? (json_decode($entity['attributes'], true) ?? [])
                    : ($entity['attributes'] ?? []);

                // Se houver arquivo original cadastrado/mapeado
                $filePath = $attrs['caminho_arquivo'] ?? '';
                $format   = strtolower($attrs['formato'] ?? pathinfo($entity['name'], PATHINFO_EXTENSION));
                $hasFile  = !empty($filePath) && file_exists($filePath);

                if (empty($attrs)):
                ?>
                <p class="text-subtle" style="font-size:.875rem;margin:0">
                    Nenhum atributo registrado.
                </p>
                <?php else: ?>
                <div class="cbr-attr-table" role="list">
                    <?php foreach ($attrs as $key => $value):
                        // Ocultar caminho técnico interno para manter limpo
                        if ($key === 'caminho_arquivo') continue;

                        $label = $attrLabels[$key] ?? ucwords(str_replace('_', ' ', $key));

                        // Limpar texto de EXIF cru se presente
                        if ($key === 'descricao' && is_string($value) && strpos($value, 'Metadados EXIF:') !== false) {
                            $parts = explode('Metadados EXIF:', $value);
                            $value = trim($parts[0]);
                        }

                        if (is_array($value)):
                    ?>
                    <div class="cbr-attr-row" role="listitem">
                        <div class="cbr-attr-key"><?= esc($label) ?></div>
                        <div class="cbr-attr-val d-flex justify-content-between align-items-start">
                            <div>
                                <?php foreach ($value as $subKey => $subVal): ?>
                                <div>
                                    <span class="text-subtle" style="font-size:.75rem"><?= esc(ucwords(str_replace('_',' ',$subKey))) ?>:</span>
                                    <?= esc($subVal) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <form action="<?= base_url('entidades/' . $entity['id'] . '/atributos/remover') ?>" method="post" style="display:inline" onsubmit="return confirm('Remover o atributo \'<?= esc($label) ?>\'?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="attr_key" value="<?= esc($key) ?>">
                                <button type="submit" class="btn btn-link text-danger p-0 border-0 ms-2" title="Remover atributo" style="font-size:.875rem">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="cbr-attr-row" role="listitem">
                        <div class="cbr-attr-key"><?= esc($label) ?></div>
                        <div class="cbr-attr-val d-flex justify-content-between align-items-start">
                            <div style="flex:1"><?= nl2br(esc($value)) ?></div>
                            <form action="<?= base_url('entidades/' . $entity['id'] . '/atributos/remover') ?>" method="post" style="display:inline" onsubmit="return confirm('Remover o atributo \'<?= esc($label) ?>\'?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="attr_key" value="<?= esc($key) ?>">
                                <button type="submit" class="btn btn-link text-danger p-0 border-0 ms-2 opacity-75" title="Remover atributo" style="font-size:.875rem">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ─── Visualizador do Arquivo Original (se a entidade for Documento) ─────── -->
        <?php if ($entity['type'] === 'document'): ?>
        <div class="cbr-detail-section" style="grid-column: 1 / -1">
            <div class="cbr-detail-section-header">
                <h2 class="cbr-detail-section-title">
                    <i class="bi bi-file-earmark-image" aria-hidden="true"></i>
                    Arquivo Original (Fonte Primária)
                </h2>
                <?php if ($hasFile): ?>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('documentos/' . $entity['id'] . '/revisar') ?>"
                       class="btn btn-sm btn-warning d-flex align-items-center gap-1 text-dark fw-bold">
                        <i class="bi bi-crop me-1"></i> Workspace de Transcrição & Crop IA
                    </a>
                    <a href="<?= base_url('documentos/' . $entity['id'] . '/arquivo') ?>"
                       target="_blank"
                       class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1">
                        <i class="bi bi-box-arrow-up-right"></i> Abrir em Tela Cheia
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <div class="cbr-detail-section-body" style="text-align:center;background:var(--cbr-surface-2)">
                <?php if ($hasFile): ?>
                    <?php if (in_array($format, ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'bmp'])): ?>
                    <div class="p-2" style="max-height:600px;overflow:auto">
                        <img src="<?= base_url('documentos/' . $entity['id'] . '/arquivo') ?>"
                             alt="<?= esc($entity['name']) ?>"
                             class="img-fluid rounded border shadow-sm"
                             style="max-height:550px;object-fit:contain">
                    </div>
                    <?php elseif ($format === 'pdf'): ?>
                    <iframe src="<?= base_url('documentos/' . $entity['id'] . '/arquivo') ?>"
                            style="width:100%;height:550px;border:1px solid var(--cbr-border);border-radius:var(--cbr-radius-sm)"></iframe>
                    <?php else: ?>
                    <div class="py-3">
                        <i class="bi bi-file-earmark-text" style="font-size:3rem;color:var(--cbr-text-muted)"></i>
                        <p class="mt-2" style="font-size:.875rem;color:var(--cbr-text)">
                            Arquivo de texto gravado: <strong><?= esc($entity['name']) ?></strong>
                        </p>
                        <a href="<?= base_url('documentos/' . $entity['id'] . '/arquivo') ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-download me-1"></i> Baixar Arquivo Original
                        </a>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="py-4 text-center">
                        <i class="bi bi-link-45deg" style="font-size:2.5rem;color:var(--cbr-primary)"></i>
                        <p class="mt-2" style="font-size:.875rem;color:var(--cbr-text)">
                            Este registro de documento <strong>não possui arquivo físico próprio</strong>
                            &mdash; foi gerado a partir da extração de outro documento primário.
                        </p>
                        <p style="font-size:.8125rem;color:var(--cbr-text-muted)">
                            <i class="bi bi-arrow-down-circle me-1"></i>
                            A fonte original e os trechos de comprovação estão listados abaixo em
                            <strong>"Fonte Primária &amp; Trecho de Origem"</strong>.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ─── Fonte / rastreabilidade ───────────────────────── -->
        <div class="cbr-detail-section">
            <div class="cbr-detail-section-header">
                <h2 class="cbr-detail-section-title">
                    <i class="bi bi-bookmark-check" aria-hidden="true"></i>
                    Rastreabilidade
                </h2>
            </div>
            <div class="cbr-detail-section-body">
                <?php
                $createdById   = $entity['created_by']   ?? null;
                $validatedById = $entity['validated_by'] ?? null;
                ?>
                <div class="cbr-attr-table">
                    <div class="cbr-attr-row">
                        <div class="cbr-attr-key">ID</div>
                        <div class="cbr-attr-val"><code>#<?= (int)$entity['id'] ?></code></div>
                    </div>
                    <div class="cbr-attr-row">
                        <div class="cbr-attr-key">Status</div>
                        <div class="cbr-attr-val">
                            <span class="<?= $entity['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                                <?= $entity['status'] === 'confirmed' ? 'Confirmado' : 'Hipótese' ?>
                            </span>
                        </div>
                    </div>
                    <div class="cbr-attr-row">
                        <div class="cbr-attr-key">Criado por</div>
                        <div class="cbr-attr-val">
                            <?php if (!empty($creatorUser)): ?>
                                <strong><?= esc($creatorUser['name']) ?></strong>
                                <?php if (!empty($creatorUser['role'])): ?>
                                <span class="badge bg-secondary ms-1" style="font-size:.65rem;vertical-align:middle"><?= esc(ucfirst($creatorUser['role'])) ?></span>
                                <?php endif; ?>
                            <?php elseif ($createdById): ?>
                                Usuário #<?= (int)$createdById ?>
                            <?php else: ?>
                                <span class="text-subtle">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($entity['status'] === 'confirmed'): ?>
                    <div class="cbr-attr-row">
                        <div class="cbr-attr-key">Validado por</div>
                        <div class="cbr-attr-val">
                            <?php if (!empty($validatorUser)): ?>
                                <strong><?= esc($validatorUser['name']) ?></strong>
                                <?php if (!empty($validatorUser['role'])): ?>
                                <span class="badge bg-secondary ms-1" style="font-size:.65rem;vertical-align:middle"><?= esc(ucfirst($validatorUser['role'])) ?></span>
                                <?php endif; ?>
                            <?php elseif ($validatedById): ?>
                                Usuário #<?= (int)$validatedById ?>
                            <?php else: ?>
                                <span class="text-subtle">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($entity['updated_at'])): ?>
                    <div class="cbr-attr-row">
                        <div class="cbr-attr-key">Última atualização</div>
                        <div class="cbr-attr-val"><?= date('d/m/Y H:i', strtotime($entity['updated_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($entity['status'] === 'hypothesis'): ?>
                <div class="mt-3 p-3 rounded" style="background:var(--cbr-hypothesis-bg);border:1px solid rgba(245,158,11,.25)">
                    <div class="d-flex gap-2 align-items-start">
                        <i class="bi bi-info-circle-fill flex-shrink-0" style="color:var(--cbr-hypothesis);margin-top:.1rem" aria-hidden="true"></i>
                        <p style="font-size:.8125rem;color:var(--cbr-hypothesis);margin:0">
                            Esta entidade ainda não foi confirmada como fato documentado.
                            <?= $role === 'coordenador' ? 'Como coordenadora, você pode confirmá-la acima.' : 'Aguarda revisão da coordenadora.' ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.cbr-detail-grid -->

    <!-- ─── Fonte Primária & Trecho de Origem (se extraído de documentos fontes) ─── -->
    <?php if (!empty($sourceDocuments)): ?>
    <div class="cbr-detail-section mt-3">
        <div class="cbr-detail-section-header">
            <h2 class="cbr-detail-section-title">
                <i class="bi bi-file-earmark-code" aria-hidden="true"></i>
                Fonte Primária & Trecho de Origem (<?= count($sourceDocuments) ?>)
            </h2>
        </div>
        <div class="cbr-detail-section-body">
            <?php foreach ($sourceDocuments as $sDoc): ?>
            <div class="p-3 mb-3 rounded border" style="background:var(--cbr-surface-2);border-color:var(--cbr-border)!important">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-text text-primary" style="font-size:1.25rem"></i>
                        <strong>
                            <a href="<?= base_url('entidades/' . $sDoc['id']) ?>" class="text-decoration-none text-light">
                                <?= esc($sDoc['name']) ?>
                            </a>
                        </strong>
                        <span class="badge bg-secondary" style="font-size:.7rem">Documento Fonte #<?= $sDoc['id'] ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($sDoc['uploader']): ?>
                        <span class="text-subtle" style="font-size:.8rem">
                            <i class="bi bi-person-check me-1"></i> Pesquisador: <strong><?= esc($sDoc['uploader']['name']) ?></strong>
                        </span>
                        <?php endif; ?>
                        <?php if ($sDoc['has_file']): ?>
                        <a href="<?= base_url('documentos/' . $sDoc['id'] . '/revisar') ?>" class="btn btn-sm btn-warning text-dark fw-bold" style="font-size:.75rem">
                            <i class="bi bi-crop me-1"></i> Abrir Workspace
                        </a>
                        <a href="<?= base_url('documentos/' . $sDoc['id'] . '/arquivo') ?>" target="_blank" class="btn btn-sm btn-outline-primary" style="font-size:.75rem">
                            <i class="bi bi-box-arrow-up-right me-1"></i> Ver Arquivo
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($sDoc['excerpts'])): ?>
                <div class="mt-2">
                    <p class="text-subtle mb-1" style="font-size:.75rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase">
                        <i class="bi bi-quote me-1"></i> Trecho de Origem / Citação Documental:
                    </p>
                    <?php foreach ($sDoc['excerpts'] as $exc): ?>
                    <blockquote class="p-2 mb-2 rounded border-start border-3 border-warning" style="background:var(--cbr-surface-1);font-style:italic;font-size:.875rem;color:var(--cbr-text)">
                        "<?= nl2br(esc($exc)) ?>"
                    </blockquote>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($sDoc['has_file']): ?>
                <div class="mt-3 text-center p-2 rounded border" style="background:var(--cbr-surface-1);max-height:400px;overflow:auto;border-color:var(--cbr-border)!important">
                    <?php if (in_array($sDoc['format'], ['jpg', 'jpeg', 'jfif', 'png', 'webp', 'bmp'])): ?>
                    <img src="<?= base_url('documentos/' . $sDoc['id'] . '/arquivo') ?>"
                         alt="<?= esc($sDoc['name']) ?>"
                         class="img-fluid rounded border shadow-sm"
                         style="max-height:350px;object-fit:contain">
                    <?php elseif ($sDoc['format'] === 'pdf'): ?>
                    <iframe src="<?= base_url('documentos/' . $sDoc['id'] . '/arquivo') ?>"
                            style="width:100%;height:350px;border:none"></iframe>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ─── Relações ──────────────────────────────────────────── -->
    <?php
    $totalRelations = count($relationsAsSource) + count($relationsAsTarget);
    ?>
    <div class="cbr-detail-section mt-3">
        <div class="cbr-detail-section-header">
            <h2 class="cbr-detail-section-title">
                <i class="bi bi-share" aria-hidden="true"></i>
                Relações
                <?php if ($totalRelations > 0): ?>
                <span class="ms-1 text-subtle" style="font-weight:400;font-size:.8125rem">(<?= $totalRelations ?>)</span>
                <?php endif; ?>
            </h2>
            <a href="<?= base_url('relacoes/nova?origem=' . $entity['id']) ?>"
               class="btn btn-primary btn-sm d-flex align-items-center gap-1"
               id="btn-add-rel-section">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Nova relação
            </a>
        </div>
        <div class="cbr-detail-section-body">

            <?php if ($totalRelations === 0): ?>
            <div class="cbr-empty-state py-3">
                <i class="bi bi-share" aria-hidden="true"></i>
                <p>Nenhuma relação cadastrada para esta entidade.</p>
                <a href="<?= base_url('relacoes/nova?origem=' . $entity['id']) ?>"
                   class="btn btn-primary btn-sm"
                   id="btn-first-rel">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Adicionar primeira relação
                </a>
            </div>
            <?php else: ?>

            <?php if (!empty($relationsAsSource)): ?>
            <p class="text-subtle mb-2" style="font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase">
                Esta entidade como <strong>origem</strong>
            </p>
            <?php foreach ($relationsAsSource as $rel):
                $targetEntity = $relatedEntities[$rel['target_entity_id']] ?? null;
                $tConf = $typeConfig[$targetEntity['type'] ?? 'person'] ?? $typeConfig['person'];
                $confidence = round(($rel['confidence'] ?? 0.75) * 100);
            ?>
            <div class="cbr-relation-item">
                <div style="display:flex;align-items:center;gap:.5rem;min-width:0">
                    <span style="color:<?= $tc['color'] ?>;font-size:.875rem">
                        <i class="bi <?= $tc['icon'] ?>" aria-hidden="true"></i>
                    </span>
                    <span class="cbr-relation-arrow" aria-hidden="true">→</span>
                    <span class="cbr-relation-type"><?= esc($rel['relationship_type']) ?></span>
                    <span class="cbr-relation-arrow" aria-hidden="true">→</span>
                    <?php if ($targetEntity): ?>
                    <a href="<?= base_url('entidades/' . $targetEntity['id']) ?>"
                       class="cbr-relation-entity">
                        <i class="bi <?= $tConf['icon'] ?>" style="color:<?= $tConf['color'] ?>" aria-hidden="true"></i>
                        <?= esc($targetEntity['name']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-subtle">#<?= $rel['target_entity_id'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="cbr-relation-confidence"><?= $confidence ?>%</span>
                    <span class="<?= $rel['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>" style="font-size:.625rem">
                        <?= $rel['status'] === 'confirmed' ? 'Confirmada' : 'Hipótese' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($relationsAsTarget)): ?>
            <?php if (!empty($relationsAsSource)): ?><hr class="cbr-divider"><?php endif; ?>
            <p class="text-subtle mb-2" style="font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase">
                Esta entidade como <strong>destino</strong>
            </p>
            <?php foreach ($relationsAsTarget as $rel):
                $sourceEntity = $relatedEntities[$rel['source_entity_id']] ?? null;
                $sConf = $typeConfig[$sourceEntity['type'] ?? 'person'] ?? $typeConfig['person'];
                $confidence = round(($rel['confidence'] ?? 0.75) * 100);
            ?>
            <div class="cbr-relation-item">
                <div style="display:flex;align-items:center;gap:.5rem;min-width:0;flex-wrap:wrap">
                    <?php if ($sourceEntity): ?>
                    <a href="<?= base_url('entidades/' . $sourceEntity['id']) ?>"
                       class="cbr-relation-entity">
                        <i class="bi <?= $sConf['icon'] ?>" style="color:<?= $sConf['color'] ?>" aria-hidden="true"></i>
                        <?= esc($sourceEntity['name']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-subtle">#<?= $rel['source_entity_id'] ?></span>
                    <?php endif; ?>
                    <span class="cbr-relation-arrow" aria-hidden="true">→</span>
                    <span class="cbr-relation-type"><?= esc($rel['relationship_type']) ?></span>
                    <span class="cbr-relation-arrow" aria-hidden="true">→</span>
                    <span style="color:<?= $tc['color'] ?>;font-size:.875rem">
                        <i class="bi <?= $tc['icon'] ?>" aria-hidden="true"></i>
                    </span>
                </div>
                <div class="ms-auto d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="cbr-relation-confidence"><?= $confidence ?>%</span>
                    <span class="<?= $rel['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>" style="font-size:.625rem">
                        <?= $rel['status'] === 'confirmed' ? 'Confirmada' : 'Hipótese' ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php endif; // totalRelations ?>
        </div>
    </div>

</div><!-- /.fade-in-up -->

    <!-- Modal Adicionar Atributo -->
    <div class="modal fade" id="modalAddAttribute" tabindex="-1" aria-labelledby="modalAddAttributeLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <form action="<?= base_url('entidades/' . $entity['id'] . '/atributos') ?>" method="post" class="modal-content cbr-modal-dark">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddAttributeLabel">
                        <i class="bi bi-plus-circle me-1"></i> Adicionar Atributo
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="attr_key_select" class="form-label cbr-modal-label">Nome do Atributo</label>
                        <select name="attr_key" id="attr_key_select" class="form-select cbr-modal-input">
                            <option value="" disabled selected>Selecione um atributo...</option>
                            <?php
                            // Carregar vocabulário controlado da Spec 11 (se disponível)
                            $vocabModel = new \App\Models\EntityAttributeVocabularyModel();
                            $vocabItems = $vocabModel->getByType($entity['type'] ?? 'person');
                            if (!empty($vocabItems)):
                                foreach ($vocabItems as $vi):
                            ?>
                            <option value="<?= esc($vi['key_name']) ?>"><?= esc($vi['label']) ?></option>
                            <?php endforeach; endif; ?>
                            <option value="outro">Outro (Personalizado)...</option>
                        </select>
                    </div>
                    <div class="mb-3" id="custom_attr_key_group" style="display:none">
                        <label for="custom_attr_key" class="form-label cbr-modal-label">Nome Personalizado <span class="text-subtle">(ex: religiao, partido)</span></label>
                        <input type="text" name="custom_attr_key" id="custom_attr_key" class="form-control cbr-modal-input" placeholder="digite_o_nome_do_atributo">
                    </div>
                    <div class="mb-0">
                        <label for="attr_value" class="form-label cbr-modal-label">Valor</label>
                        <textarea name="attr_value" id="attr_value" class="form-control cbr-modal-input" rows="3" required placeholder="Digite o valor do atributo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i> Salvar Atributo
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Modal de Atributo (adicionar/remover)
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('attr_key_select');
        const customGroup = document.getElementById('custom_attr_key_group');
        if (select && customGroup) {
            select.addEventListener('change', function() {
                if (this.value === 'outro') {
                    customGroup.style.display = 'block';
                    document.getElementById('custom_attr_key')?.focus();
                } else {
                    customGroup.style.display = 'none';
                }
            });
        }

        // ─── Modal de Edição de Entidade ─────────────────────
        const editModal = document.getElementById('modalEditEntity');
        const attrContainer = document.getElementById('edit-attributes-container');
        const btnAddAttr = document.getElementById('btn-add-attr-row');
        const deletedContainer = document.getElementById('deleted-attrs-container');

        // Focar o campo Nome quando o modal abrir e manter foco preso
        if (editModal) {
            editModal.addEventListener('shown.bs.modal', function () {
                const nameInput = document.getElementById('edit_name');
                if (nameInput) {
                    setTimeout(() => {
                        nameInput.focus();
                        nameInput.select();
                    }, 150);
                }
            });

            // Se algo roubar o foco, traz de volta ao clicar em qualquer parte do modal
            editModal.addEventListener('click', function (e) {
                // Pequeno delay para deixar o clique processar
                setTimeout(() => {
                    const focused = document.activeElement;
                    // Se o foco escapou do modal, traz de volta
                    if (focused && !editModal.contains(focused) && focused !== document.body) {
                        const nameInput = document.getElementById('edit_name');
                        if (nameInput && document.contains(nameInput)) {
                            nameInput.focus();
                        }
                    }
                }, 50);
            });

            // Ao fechar, limpar foco do modal
            editModal.addEventListener('hidden.bs.modal', function () {
                if (document.activeElement) {
                    document.activeElement.blur();
                }
            });
        }

        // Template de linha de atributo
        function createAttrRow(key, value) {
            const div = document.createElement('div');
            div.className = 'input-group input-group-sm mb-1 attr-row';
            div.innerHTML = `
                <input type="text" name="attr_keys[]" class="form-control cbr-modal-input" style="max-width:35%"
                       value="${escapeHtml(key || '')}" placeholder="chave">
                <input type="text" name="attr_values[]" class="form-control cbr-modal-input"
                       value="${escapeHtml(value || '')}" placeholder="valor">
                <button type="button" class="btn btn-outline-danger btn-delete-attr" title="Remover">
                    <i class="bi bi-x-lg"></i>
                </button>`;
            return div;
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // Adicionar nova linha de atributo
        if (btnAddAttr) {
            btnAddAttr.addEventListener('click', function() {
                const noMsg = document.getElementById('no-attrs-msg');
                if (noMsg) noMsg.remove();
                attrContainer.appendChild(createAttrRow('', ''));
            });
        }

        // Delegar eventos de remoção de atributo
        if (attrContainer) {
            attrContainer.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-delete-attr');
                if (!btn) return;

                const row = btn.closest('.attr-row');
                const keyInput = row.querySelector('input[name="attr_keys[]"]');
                if (keyInput && keyInput.value.trim() !== '') {
                    // Registrar chave para exclusão no backend
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'attr_deletes[]';
                    hidden.value = keyInput.value.trim();
                    deletedContainer.appendChild(hidden);
                }
                row.remove();

                // Se não houver mais linhas, mostrar mensagem
                if (attrContainer.querySelectorAll('.attr-row').length === 0) {
                    const msg = document.createElement('p');
                    msg.className = 'text-subtle';
                    msg.id = 'no-attrs-msg';
                    msg.style.fontSize = '.8125rem';
                    msg.textContent = 'Nenhum atributo cadastrado.';
                    attrContainer.appendChild(msg);
                }
            });
        }

        // ─── Modal de Mesclagem de Entidade ────────────────
        const mergeSearch = document.getElementById('merge_target_search');
        const mergeResults = document.getElementById('merge_search_results');
        const mergeTargetId = document.getElementById('merge_target_id');
        const mergeTargetName = document.getElementById('merge_target_name');
        const mergeTargetSelected = document.getElementById('merge_target_selected');
        const mergeConfirmBtn = document.getElementById('btn-confirm-merge');
        let searchTimer = null;

        if (mergeSearch) {
            mergeSearch.addEventListener('input', function () {
                const q = this.value.trim();
                if (q.length < 2) {
                    mergeResults.style.display = 'none';
                    return;
                }
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    fetch('/api/entidades/busca?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        mergeResults.innerHTML = '';
                        const filtered = (data || []).filter(e => e.id != <?= $entity['id'] ?> && e.type === '<?= $entity['type'] ?>');
                        if (filtered.length === 0) {
                            mergeResults.innerHTML = '<div class="list-group-item text-subtle" style="font-size:.8125rem">Nenhuma entidade do mesmo tipo encontrada.</div>';
                        } else {
                            filtered.forEach(e => {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action';
                                item.style.cssText = 'background:var(--cbr-surface-2);color:var(--cbr-text);border-color:var(--cbr-border);font-size:.8125rem;text-align:left';
                                item.innerHTML = '<strong>' + e.name + '</strong> <span class="text-subtle">(#' + e.id + ')</span>';
                                item.addEventListener('click', () => {
                                    mergeTargetId.value = e.id;
                                    mergeTargetName.textContent = e.name + ' (#' + e.id + ')';
                                    mergeTargetSelected.style.display = 'block';
                                    mergeSearch.value = e.name;
                                    mergeResults.style.display = 'none';
                                    mergeConfirmBtn.disabled = false;
                                });
                                mergeResults.appendChild(item);
                            });
                        }
                        mergeResults.style.display = 'block';
                    });
                }, 250);
            });

            document.addEventListener('click', function(e) {
                if (!mergeSearch.contains(e.target) && !mergeResults.contains(e.target)) {
                    mergeResults.style.display = 'none';
                }
            });
        }
    });
    </script>

    <!-- Modal Editar Entidade (posicionado no topo) -->
    <div class="modal fade" id="modalEditEntity" tabindex="-1" aria-labelledby="modalEditEntityLabel" aria-hidden="true" data-bs-backdrop="static" style="z-index:1070">
        <div class="modal-dialog modal-lg" style="margin-top:2rem;pointer-events:auto">
            <form action="<?= base_url('entidades/' . $entity['id'] . '/editar') ?>" method="post" class="modal-content cbr-modal-dark">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEditEntityLabel">
                        <i class="bi bi-pencil me-1"></i> Editar Entidade
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <!-- Nome -->
                    <div class="mb-3">
                        <label for="edit_name" class="form-label cbr-modal-label">Nome da Entidade <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control cbr-modal-input" required
                               value="<?= esc($entity['name']) ?>"
                               placeholder="Ex: Benedicto de Andrade">
                    </div>

                    <!-- Tipo -->
                    <div class="mb-3">
                        <label for="edit_type" class="form-label cbr-modal-label">
                            Tipo de Entidade
                            <?php if ($entity['status'] === 'confirmed'): ?>
                            <span class="text-subtle" style="font-size:.75rem">(bloqueado — entidade confirmada)</span>
                            <?php endif; ?>
                        </label>
                        <select name="type" id="edit_type" class="form-select cbr-modal-input"
                                <?= ($entity['status'] ?? '') === 'confirmed' ? 'disabled' : '' ?>>
                            <?php foreach (['person'=>'Pessoa','location'=>'Local','event'=>'Evento','document'=>'Documento'] as $tVal => $tLabel): ?>
                            <option value="<?= $tVal ?>" <?= ($entity['type'] ?? '') === $tVal ? 'selected' : '' ?>>
                                <?= $tLabel ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($entity['status'] === 'confirmed'): ?>
                        <input type="hidden" name="type" value="<?= esc($entity['type']) ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Atributos editáveis -->
                    <div class="mb-2">
                        <label class="form-label cbr-modal-label">
                            Atributos
                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="btn-add-attr-row" style="font-size:.75rem">
                                <i class="bi bi-plus-lg"></i> Adicionar
                            </button>
                        </label>
                        <div id="edit-attributes-container" style="max-height:250px;overflow-y:auto">
                            <?php
                            $editAttrs = is_string($entity['attributes'] ?? '{}')
                                ? (json_decode($entity['attributes'], true) ?? [])
                                : ($entity['attributes'] ?? []);
                            $hasAttrs = false;
                            foreach ($editAttrs as $key => $value):
                                if (is_array($value)) continue; // pula objetos aninhados
                                $hasAttrs = true;
                            ?>
                            <div class="input-group input-group-sm mb-1 attr-row">
                                <input type="text" name="attr_keys[]" class="form-control cbr-modal-input" style="max-width:35%"
                                       value="<?= esc($key) ?>" placeholder="chave">
                                <input type="text" name="attr_values[]" class="form-control cbr-modal-input"
                                       value="<?= esc($value) ?>" placeholder="valor">
                                <button type="button" class="btn btn-outline-danger btn-delete-attr" title="Remover">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!$hasAttrs): ?>
                            <p class="text-subtle" style="font-size:.8125rem" id="no-attrs-msg">Nenhum atributo cadastrado.</p>
                            <?php endif; ?>
                        </div>
                        <!-- Campos ocultos para remoção de atributos -->
                        <div id="deleted-attrs-container"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Mesclar Entidade -->
    <div class="modal fade" id="modalMergeEntity" tabindex="-1" aria-labelledby="modalMergeEntityLabel" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <form action="<?= base_url('entidades/merge') ?>" method="post" class="modal-content cbr-modal-dark">
                <?= csrf_field() ?>
                <input type="hidden" name="source_id" value="<?= $entity['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalMergeEntityLabel">
                        <i class="bi bi-union me-1"></i> Mesclar Entidade
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 p-3 rounded" style="background:var(--cbr-surface-2);border:1px solid var(--cbr-border)">
                        <p class="mb-1" style="font-size:.8125rem;color:var(--cbr-text-muted)">Entidade a ser absorvida (será apagada):</p>
                        <strong style="color:var(--cbr-danger)">
                            <i class="bi <?= $tc['icon'] ?> me-1"></i><?= esc($entity['name']) ?>
                        </strong>
                        <span class="badge" style="background:<?= $tc['bg'] ?>;color:<?= $tc['color'] ?>"><?= $tc['label'] ?></span>
                    </div>

                    <div class="mb-3">
                        <label for="merge_target_search" class="form-label cbr-modal-label">
                            Buscar entidade de destino (manterá o nome e receberá as relações):
                        </label>
                        <input type="text" id="merge_target_search" class="form-control cbr-modal-input"
                               placeholder="Digite o nome da entidade..." autocomplete="off">
                        <div id="merge_search_results" class="list-group mt-1" style="max-height:200px;overflow-y:auto;display:none"></div>
                        <input type="hidden" name="target_id" id="merge_target_id" value="">
                        <div id="merge_target_selected" class="mt-2" style="display:none">
                            <span class="text-subtle" style="font-size:.75rem">Destino selecionado:</span>
                            <strong id="merge_target_name" class="ms-1" style="color:var(--cbr-confirmed)"></strong>
                        </div>
                    </div>

                    <div class="p-2 rounded" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3)">
                        <p class="mb-0" style="font-size:.75rem;color:var(--cbr-hypothesis)">
                            <i class="bi bi-info-circle me-1"></i>
                            As relações da entidade atual serão transferidas para o destino.
                            Atributos serão combinados. A entidade atual será apagada.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btn-confirm-merge" disabled>
                        <i class="bi bi-union me-1"></i> Mesclar
                    </button>
                </div>
            </form>
        </div>
    </div>

<style>
@media (max-width: 767.98px) {
    .cbr-detail-grid { grid-template-columns: 1fr !important; }
}
</style>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => esc($entity['name'] ?? 'Entidade'),
    'breadcrumbs'=> [
        ['label'=>'Dashboard',  'url'=>base_url('/')],
        ['label'=>'Entidades',  'url'=>base_url('entidades')],
        ['label'=>esc($entity['name'] ?? '#' . ($entity['id'] ?? '')), 'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css'],
    'pageJs'     => ['entities.js'],
]);
?>

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

// Rótulos amigáveis para atributos JSONB
$attrLabels = [
    'ocupacao'               => 'Ocupação / cargo',
    'nascimento'             => 'Data de nascimento',
    'naturalidade'           => 'Naturalidade',
    'filiacao'               => 'Filiação',
    'notas'                  => 'Notas',
    'municipio'              => 'Município',
    'estado'                 => 'Estado / UF',
    'descricao'              => 'Descrição',
    'data'                   => 'Data',
    'tipo_evento'            => 'Tipo de evento',
    'titulo'                 => 'Título',
    'autor_responsavel'      => 'Autor / responsável',
    'tipo_documento'         => 'Tipo de documento',
    'instituicao_custodiadora'=> 'Instituição custodiadora',
    'localizacao_arquivistica'=> 'Localização arquivística',
    'data_acesso'            => 'Data de acesso',
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
               class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1"
               id="btn-ai-review">
                <i class="bi bi-eye" aria-hidden="true"></i>
                <span>Ver Revisão IA</span>
            </a>
            <?php endif; ?>

            <a href="<?= base_url('relacoes/nova?origem=' . $entity['id']) ?>"
               class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1"
               id="btn-add-relation">
                <i class="bi bi-share" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Adicionar relação</span>
            </a>

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
            </div>
            <div class="cbr-detail-section-body">
                <?php
                $attrs = is_string($entity['attributes'])
                    ? (json_decode($entity['attributes'], true) ?? [])
                    : ($entity['attributes'] ?? []);

                if (empty($attrs)):
                ?>
                <p class="text-subtle" style="font-size:.875rem;margin:0">
                    Nenhum atributo registrado.
                </p>
                <?php else: ?>
                <div class="cbr-attr-table" role="list">
                    <?php foreach ($attrs as $key => $value):
                        $label = $attrLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
                        if (is_array($value)):
                    ?>
                    <div class="cbr-attr-row" role="listitem">
                        <div class="cbr-attr-key"><?= esc($label) ?></div>
                        <div class="cbr-attr-val">
                            <?php foreach ($value as $subKey => $subVal): ?>
                            <div>
                                <span class="text-subtle" style="font-size:.75rem"><?= esc(ucwords(str_replace('_',' ',$subKey))) ?>:</span>
                                <?= esc($subVal) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="cbr-attr-row" role="listitem">
                        <div class="cbr-attr-key"><?= esc($label) ?></div>
                        <div class="cbr-attr-val"><?= nl2br(esc($value)) ?></div>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

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
                            <?= $createdById ? 'Usuário #' . (int)$createdById : '<span class="text-subtle">—</span>' ?>
                        </div>
                    </div>
                    <?php if ($entity['status'] === 'confirmed'): ?>
                    <div class="cbr-attr-row">
                        <div class="cbr-attr-key">Validado por</div>
                        <div class="cbr-attr-val">
                            <?= $validatedById ? 'Usuário #' . (int)$validatedById : '<span class="text-subtle">—</span>' ?>
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

<?php
/**
 * Cerebro — Views/extraction/review.php
 * Interface de revisão Human-in-the-Loop (Documento vs Hipóteses da IA)
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

$doc             = $doc             ?? [];
$relationships   = $relationships   ?? [];
$relatedEntities = $relatedEntities ?? [];

$attrs = is_string($doc['attributes'])
    ? (json_decode($doc['attributes'], true) ?? [])
    : ($doc['attributes'] ?? []);

$typeConfig = [
    'person'   => ['icon'=>'bi-person-fill',      'css'=>'person',   'color'=>'var(--cbr-person)'],
    'location' => ['icon'=>'bi-geo-alt-fill',      'css'=>'location', 'color'=>'var(--cbr-location)'],
    'event'    => ['icon'=>'bi-calendar-event',    'css'=>'event',    'color'=>'var(--cbr-event)'],
    'document' => ['icon'=>'bi-file-earmark-text', 'css'=>'document', 'color'=>'var(--cbr-document)'],
];

$pendingHypotheses = array_filter($relationships, fn($r) => $r['status'] === 'hypothesis');

ob_start();
?>

<div class="fade-in-up">

    <!-- Header -->
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">
                <i class="bi bi-robot" style="color:var(--cbr-primary)"></i>
                Revisão de extração por IA
            </h1>
            <p class="cbr-page-subtitle">
                Documento: <strong><?= esc($doc['name']) ?></strong> —
                <?= count($pendingHypotheses) ?> hipótese(s) aguardando confirmação
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($role === 'coordenador' && !empty($pendingHypotheses)): ?>
            <form action="<?= base_url('documentos/' . $doc['id'] . '/aprovar-todas') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" id="btn-approve-all">
                    <i class="bi bi-patch-check-fill"></i> Aprovar todas as hipóteses
                </button>
            </form>
            <?php endif; ?>

            <a href="<?= base_url('entidades/' . $doc['id']) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Voltar ao documento
            </a>
        </div>
    </div>

    <!-- Layout Dividido (Split-View) -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem" class="cbr-split-layout">

        <!-- LADO ESQUERDO: Texto e Metadados do Documento -->
        <div>
            <div class="cbr-card mb-3">
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;padding-bottom:.75rem;border-bottom:1px solid var(--cbr-border)">
                    <div style="width:40px;height:40px;border-radius:.5rem;background:var(--cbr-document-bg);color:var(--cbr-document);display:flex;align-items:center;justify-content:center;font-size:1.25rem">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <h2 style="font-size:1.125rem;font-weight:700;color:var(--cbr-text);margin:0"><?= esc($doc['name']) ?></h2>
                        <span class="badge-type badge-document">Fonte Primária</span>
                    </div>
                </div>

                <!-- Atributos do Documento -->
                <div class="cbr-attr-table mb-3">
                    <?php foreach ($attrs as $k => $v): if ($k === 'descricao' || $k === 'notas' || is_array($v)) continue; ?>
                    <div class="cbr-attr-row">
                        <div class="cbr-attr-key"><?= esc(ucwords(str_replace('_',' ',$k))) ?></div>
                        <div class="cbr-attr-val"><?= esc($v) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Conteúdo/Transcrição -->
                <h3 style="font-size:.875rem;font-weight:700;color:var(--cbr-text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem">
                    Texto / Transcrição Analisada
                </h3>
                <div style="padding:1rem;background:var(--cbr-surface-2);border:1px solid var(--cbr-border);border-radius:var(--cbr-radius-sm);font-size:.875rem;color:var(--cbr-text);max-height:400px;overflow-y:auto;white-space:pre-line;line-height:1.6">
                    <?= esc($attrs['descricao'] ?? $attrs['notas'] ?? $attrs['titulo'] ?? $doc['name']) ?>
                </div>
            </div>
        </div>

        <!-- LADO DIREITO: Hipóteses Extraídas pela IA -->
        <div>
            <div class="cbr-card p-0 overflow-hidden">
                <div style="padding:.875rem 1.125rem;background:var(--cbr-surface-2);border-bottom:1px solid var(--cbr-border);display:flex;align-items:center;justify-content:space-between">
                    <h2 style="font-size:.9375rem;font-weight:600;color:var(--cbr-text);margin:0;display:flex;align-items:center;gap:.5rem">
                        <i class="bi bi-diagram-3" style="color:var(--cbr-primary)"></i>
                        Hipóteses Extraídas pela IA
                    </h2>
                    <span style="font-size:.75rem;color:var(--cbr-text-subtle)"><?= count($relationships) ?> relação(ões)</span>
                </div>

                <div style="padding:1.125rem">
                    <?php if (empty($relationships)): ?>
                    <div class="cbr-empty-state py-4">
                        <i class="bi bi-robot" style="font-size:2.5rem;color:var(--cbr-text-subtle)" aria-hidden="true"></i>
                        <p style="font-size:.875rem;margin-top:.5rem">
                            Nenhuma hipótese extraída para este documento ainda.
                        </p>
                        <form action="<?= base_url('documentos/' . $doc['id'] . '/extrair') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-play-fill"></i> Executar Extração com DeepSeek
                            </button>
                        </form>
                    </div>
                    <?php else: ?>

                    <div style="display:flex;flex-direction:column;gap:.75rem">
                        <?php foreach ($relationships as $rel):
                            $srcEntity = $relatedEntities[$rel['source_entity_id']] ?? null;
                            $tgtEntity = $relatedEntities[$rel['target_entity_id']] ?? null;
                            $srcConf   = $typeConfig[$srcEntity['type'] ?? ''] ?? ['icon'=>'bi-circle','color'=>'var(--cbr-text-muted)'];
                            $tgtConf   = $typeConfig[$tgtEntity['type'] ?? ''] ?? ['icon'=>'bi-circle','color'=>'var(--cbr-text-muted)'];
                            $confidence = round(($rel['confidence'] ?? 0.75) * 100);
                            $isConfirmed = $rel['status'] === 'confirmed';

                            $ref = is_string($rel['source_reference'])
                                ? (json_decode($rel['source_reference'], true) ?? [])
                                : ($rel['source_reference'] ?? []);
                            $excerpt = $ref['trecho'] ?? '';
                        ?>
                        <div class="cbr-card p-3 mb-0" style="background:var(--cbr-surface);border:1px solid <?= $isConfirmed ? 'var(--cbr-confirmed)' : 'var(--cbr-hypothesis)' ?>">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.5rem">
                                <span class="<?= $isConfirmed ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                                    <?= $isConfirmed ? '✅ Fato Confirmado' : '🟡 Hipótese da IA' ?>
                                </span>
                                <span style="font-size:.75rem;font-weight:600;color:var(--cbr-primary)">
                                    Confiança: <?= $confidence ?>%
                                </span>
                            </div>

                            <!-- Tripla: Origem -> Tipo -> Destino -->
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;font-size:.875rem;margin-bottom:.5rem">
                                <?php if ($srcEntity): ?>
                                <a href="<?= base_url('entidades/' . $srcEntity['id']) ?>" style="font-weight:600;color:var(--cbr-text)">
                                    <i class="bi <?= $srcConf['icon'] ?>" style="color:<?= $srcConf['color'] ?>"></i>
                                    <?= esc($srcEntity['name']) ?>
                                </a>
                                <?php endif; ?>

                                <span class="cbr-relation-type"><?= esc($rel['relationship_type']) ?></span>

                                <?php if ($tgtEntity): ?>
                                <a href="<?= base_url('entidades/' . $tgtEntity['id']) ?>" style="font-weight:600;color:var(--cbr-text)">
                                    <i class="bi <?= $tgtConf['icon'] ?>" style="color:<?= $tgtConf['color'] ?>"></i>
                                    <?= esc($tgtEntity['name']) ?>
                                </a>
                                <?php endif; ?>
                            </div>

                            <!-- Trecho de Origem (Rastreabilidade) -->
                            <?php if ($excerpt): ?>
                            <div style="padding:.5rem;background:var(--cbr-surface-2);border-radius:var(--cbr-radius-sm);font-size:.75rem;color:var(--cbr-text-muted);font-style:italic">
                                "<i class="bi bi-quote"></i> <?= esc($excerpt) ?>"
                            </div>
                            <?php endif; ?>

                            <!-- Botão individual de confirmação (para Coordenador) -->
                            <?php if ($role === 'coordenador' && !$isConfirmed): ?>
                            <div class="mt-2 text-end">
                                <button class="btn-confirm"
                                        data-confirm-rel="<?= $rel['id'] ?>"
                                        data-confirm-url="<?= base_url('relacoes/' . $rel['id'] . '/confirmar') ?>">
                                    <i class="bi bi-patch-check"></i> Confirmar Fato
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<style>
@media (max-width: 991.98px) {
    .cbr-split-layout { grid-template-columns: 1fr !important; }
}
</style>

<script>
// Confirmar relação via AJAX / Form POST
document.querySelectorAll('[data-confirm-rel]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var url = this.dataset.confirmUrl;
        if (!url) return;
        if (!confirm('Confirmar esta relação como fato documentado?')) return;
        var form = document.createElement('form');
        form.method = 'POST'; form.action = url;
        var csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) {
            var h = document.createElement('input');
            h.type = 'hidden';
            h.name = csrf.dataset.name || 'csrf_token';
            h.value = csrf.content;
            form.appendChild(h);
        }
        document.body.appendChild(form);
        form.submit();
    });
});
</script>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Revisão IA — ' . esc($doc['name']),
    'breadcrumbs'=> [
        ['label'=>'Dashboard', 'url'=>base_url('/')],
        ['label'=>'Entidades', 'url'=>base_url('entidades')],
        ['label'=>esc($doc['name']), 'url'=>base_url('entidades/' . $doc['id'])],
        ['label'=>'Revisão IA', 'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css'],
    'pageJs'     => [],
]);
?>

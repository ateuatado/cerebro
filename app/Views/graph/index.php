<?php
/**
 * Cerebro — Views/graph/index.php
 * Página dedicada ao grafo — vis-network em tela cheia com painel lateral de detalhes
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

$graphData    = $graphData    ?? ['entities'=>[], 'relationships'=>[]];
$entityCount  = count($graphData['entities']);
$relCount     = count($graphData['relationships']);

ob_start();
?>

<div class="fade-in-up">

    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Visualização do grafo</h1>
            <p class="cbr-page-subtitle">
                <?= $entityCount ?> entidade<?= $entityCount !== 1 ? 's' : '' ?> ·
                <?= $relCount ?> relação<?= $relCount !== 1 ? 'ões' : '' ?>
            </p>
        </div>
        <a href="<?= base_url('entidades/nova') ?>"
           class="btn btn-primary d-flex align-items-center gap-2"
           id="graph-new-entity">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span class="d-none d-sm-inline">Nova entidade</span>
        </a>
    </div>

    <?php if (empty($graphData['entities'])): ?>

    <div class="cbr-empty-state" style="padding:4rem 1.5rem">
        <i class="bi bi-diagram-3" aria-hidden="true" style="font-size:3.5rem"></i>
        <p style="font-size:1rem">O grafo ainda não tem entidades.</p>
        <a href="<?= base_url('entidades/nova') ?>" class="btn btn-primary" id="graph-empty-cta">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Criar primeira entidade
        </a>
    </div>

    <?php else: ?>

    <!-- Layout grafo + painel lateral -->
    <div id="graph-layout" style="display:grid;grid-template-columns:1fr 300px;gap:1rem;align-items:start">

        <!-- Grafo principal -->
        <div class="cbr-graph-container" style="position:sticky;top:calc(var(--cbr-topbar-h) + 1rem)">

            <div class="cbr-graph-header" style="flex-wrap:wrap;gap:.5rem">
                <h2 class="cbr-graph-title">
                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                    Grafo interativo
                </h2>
                <div class="cbr-graph-controls" role="toolbar">
                    <!-- Filtros de tipo -->
                    <button class="cbr-graph-btn active" data-graph-filter="all">Todos</button>
                    <button class="cbr-graph-btn" data-graph-filter="person"
                            title="Pessoas" data-bs-toggle="tooltip" data-bs-title="Filtrar pessoas">
                        <i class="bi bi-person" style="color:var(--cbr-person)"></i>
                    </button>
                    <button class="cbr-graph-btn" data-graph-filter="location"
                            title="Locais" data-bs-toggle="tooltip" data-bs-title="Filtrar locais">
                        <i class="bi bi-geo-alt" style="color:var(--cbr-location)"></i>
                    </button>
                    <button class="cbr-graph-btn" data-graph-filter="event"
                            title="Eventos" data-bs-toggle="tooltip" data-bs-title="Filtrar eventos">
                        <i class="bi bi-calendar-event" style="color:var(--cbr-event)"></i>
                    </button>
                    <button class="cbr-graph-btn" data-graph-filter="document"
                            title="Documentos" data-bs-toggle="tooltip" data-bs-title="Filtrar documentos">
                        <i class="bi bi-file-earmark-text" style="color:var(--cbr-document)"></i>
                    </button>
                    <div style="width:1px;background:var(--cbr-border);height:20px;flex-shrink:0"></div>
                    <!-- Filtros de status -->
                    <button class="cbr-graph-btn" data-graph-filter="confirmed">
                        <i class="bi bi-check-circle" style="color:var(--cbr-confirmed)"></i>
                    </button>
                    <button class="cbr-graph-btn" data-graph-filter="hypothesis">
                        <i class="bi bi-hourglass-split" style="color:var(--cbr-hypothesis)"></i>
                    </button>
                    <div style="width:1px;background:var(--cbr-border);height:20px;flex-shrink:0"></div>
                    <!-- Controles de navegação -->
                    <button class="cbr-graph-btn" id="graph-fit" title="Ajustar ao canvas">
                        <i class="bi bi-fullscreen"></i>
                    </button>
                    <button class="cbr-graph-btn" id="graph-zoom-in" title="Aproximar">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                    <button class="cbr-graph-btn" id="graph-zoom-out" title="Afastar">
                        <i class="bi bi-zoom-out"></i>
                    </button>
                    <button class="cbr-graph-btn" id="graph-physics" title="Física">
                        <i class="bi bi-lightning-charge"></i>
                    </button>
                </div>
            </div>

            <div id="cerebro-graph"
                 data-graph-base-url="<?= base_url() ?>"
                 style="height:560px"
                 role="img"
                 aria-label="Grafo interativo — clique num nó para ver detalhes, clique duplo para abrir a entidade"></div>

            <div class="cbr-graph-legend">
                <div class="cbr-legend-item"><div class="cbr-legend-dot" style="background:var(--cbr-person)"></div><span>Pessoa</span></div>
                <div class="cbr-legend-item"><div class="cbr-legend-dot" style="background:var(--cbr-location)"></div><span>Local</span></div>
                <div class="cbr-legend-item"><div class="cbr-legend-dot" style="background:var(--cbr-event)"></div><span>Evento</span></div>
                <div class="cbr-legend-item"><div class="cbr-legend-dot" style="background:var(--cbr-document)"></div><span>Documento</span></div>
                <div class="cbr-legend-item"><div class="cbr-legend-dot" style="background:var(--cbr-primary)"></div><span>Rel. confirmada</span></div>
                <div class="cbr-legend-item"><div class="cbr-legend-dot" style="background:var(--cbr-hypothesis);border-radius:0"></div><span>Hipótese</span></div>
            </div>
        </div>

        <!-- Painel lateral de detalhes (preenchido ao clicar em nó) -->
        <div id="graph-detail-panel">
            <div class="cbr-card p-0 overflow-hidden">
                <div style="padding:.875rem 1.125rem;border-bottom:1px solid var(--cbr-border)">
                    <h3 style="font-size:.9375rem;font-weight:600;color:var(--cbr-text);margin:0;display:flex;align-items:center;gap:.5rem">
                        <i class="bi bi-info-circle" style="color:var(--cbr-primary)"></i>
                        Detalhes
                    </h3>
                </div>
                <!-- Placeholder -->
                <div id="graph-detail-placeholder" style="padding:2rem 1.25rem;text-align:center;color:var(--cbr-text-subtle)">
                    <i class="bi bi-cursor" style="font-size:2rem;display:block;margin-bottom:.75rem"></i>
                    <p style="font-size:.8125rem;margin:0">
                        Clique em um nó do grafo para ver os detalhes
                    </p>
                </div>
                <!-- Conteúdo (preenchido pelo JS) -->
                <div id="graph-detail-content" style="display:none;padding:1.125rem"></div>
            </div>

            <!-- Estatísticas rápidas -->
            <div class="cbr-card mt-3" style="padding:1rem">
                <h3 style="font-size:.8125rem;font-weight:700;color:var(--cbr-text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.875rem">
                    Contagem
                </h3>
                <?php
                $counts = ['person'=>0,'location'=>0,'event'=>0,'document'=>0,'confirmed'=>0,'hypothesis'=>0];
                foreach ($graphData['entities'] as $e) {
                    if (isset($counts[$e['type']])) $counts[$e['type']]++;
                    if (isset($counts[$e['status']])) $counts[$e['status']]++;
                }
                $types = [
                    'person'   => ['label'=>'Pessoas',   'icon'=>'bi-person-fill',      'color'=>'var(--cbr-person)'],
                    'location' => ['label'=>'Locais',    'icon'=>'bi-geo-alt-fill',      'color'=>'var(--cbr-location)'],
                    'event'    => ['label'=>'Eventos',   'icon'=>'bi-calendar-event',    'color'=>'var(--cbr-event)'],
                    'document' => ['label'=>'Documentos','icon'=>'bi-file-earmark-text', 'color'=>'var(--cbr-document)'],
                ];
                foreach ($types as $type => $cfg):
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.375rem 0;border-bottom:1px solid var(--cbr-border)">
                    <div style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;color:var(--cbr-text-muted)">
                        <i class="bi <?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>"></i>
                        <?= $cfg['label'] ?>
                    </div>
                    <strong style="font-size:.9375rem;color:var(--cbr-text)"><?= $counts[$type] ?></strong>
                </div>
                <?php endforeach; ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem 0 0">
                    <div style="font-size:.8125rem;color:var(--cbr-hypothesis)">🟡 Hipóteses</div>
                    <strong style="color:var(--cbr-hypothesis)"><?= $counts['hypothesis'] ?></strong>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.25rem 0 0">
                    <div style="font-size:.8125rem;color:var(--cbr-confirmed)">✅ Confirmados</div>
                    <strong style="color:var(--cbr-confirmed)"><?= $counts['confirmed'] ?></strong>
                </div>
            </div>
        </div>

    </div><!-- /#graph-layout -->

    <?php endif; ?>

</div>

<!-- Dados para o vis-network -->
<?php if (!empty($graphData['entities'])): ?>
<script id="graph-data" type="application/json">
<?= json_encode($graphData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
<script id="entity-index-json" type="application/json">
<?= json_encode(array_column($graphData['entities'], null, 'id'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
<?php endif; ?>

<style>
@media (max-width: 991.98px) {
    #graph-layout { grid-template-columns: 1fr !important; }
    #cerebro-graph { height: 320px !important; }
}
</style>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Grafo de conhecimento',
    'breadcrumbs'=> [
        ['label'=>'Dashboard', 'url'=>base_url('/')],
        ['label'=>'Grafo',     'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['dashboard.css'],
    'pageJs'     => !empty($graphData['entities'])
                    ? ['../vendor/vis-network/vis-network.min.js', 'graph.js', 'graph-page.js']
                    : [],
]);
?>

<?php if (!empty($graphData['entities'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var raw = document.getElementById('graph-data');
    var idx = document.getElementById('entity-index-json');
    if (!raw || typeof CerebroGraph === 'undefined') return;

    var entityIndex = {};
    try { entityIndex = JSON.parse(idx.textContent); } catch(e) {}

    CerebroGraph.init('cerebro-graph', JSON.parse(raw.textContent));

    // Painel de detalhe ao clicar em nó
    if (typeof CerebroGraph.onNodeClick === 'function') {
        CerebroGraph.onNodeClick(function(nodeId) {
            var entity = entityIndex[nodeId];
            if (!entity) return;
            var placeholder = document.getElementById('graph-detail-placeholder');
            var content     = document.getElementById('graph-detail-content');
            if (!placeholder || !content) return;

            var typeLabels = {person:'Pessoa',location:'Local',event:'Evento',document:'Documento'};
            var typeColors = {
                person:'var(--cbr-person)',location:'var(--cbr-location)',
                event:'var(--cbr-event)',document:'var(--cbr-document)'
            };
            var color  = typeColors[entity.type] || 'var(--cbr-primary)';
            var label  = typeLabels[entity.type]  || entity.type;
            var status = entity.status === 'confirmed'
                ? '<span class="badge-confirmed">✅ Confirmado</span>'
                : '<span class="badge-hypothesis">🟡 Hipótese</span>';

            content.innerHTML = `
                <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem">
                    <div style="width:40px;height:40px;border-radius:.5rem;background:${color}22;color:${color};display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div>
                        <div style="font-weight:600;color:var(--cbr-text);font-size:.9375rem">${entity.name}</div>
                        <div style="font-size:.75rem;color:var(--cbr-text-muted)">${label}</div>
                    </div>
                </div>
                <div style="margin-bottom:1rem">${status}</div>
                <a href="<?= base_url() ?>/entidades/${entity.id}"
                   class="btn btn-primary btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                    <i class="bi bi-box-arrow-up-right"></i> Abrir entidade
                </a>
                <a href="<?= base_url() ?>/relacoes/nova?origem=${entity.id}"
                   class="btn btn-outline-secondary btn-sm w-100 d-flex align-items-center justify-content-center gap-1 mt-2">
                    <i class="bi bi-share"></i> Nova relação
                </a>
            `;
            placeholder.style.display = 'none';
            content.style.display = 'block';
        });
    }
});
</script>
<?php endif; ?>

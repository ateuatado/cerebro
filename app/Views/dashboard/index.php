<?php
/**
 * Cerebro — Views/dashboard/index.php
 * Dashboard principal: estatísticas + grafo + recentes
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

$content = ob_get_clean(); // limpa qualquer buffer antes

// Dados (injetados pelo controller; fallback para 0)
$stats = $stats ?? [
    'persons'       => 0,
    'locations'     => 0,
    'events'        => 0,
    'documents'     => 0,
    'relationships' => 0,
    'pending'       => 0,
    'total'         => 0,
];
$recentEntities      = $recentEntities      ?? [];
$recentRelationships = $recentRelationships ?? [];
$graphData           = $graphData           ?? ['entities' => [], 'relationships' => []];
?>

<?php ob_start(); ?>

<div class="fade-in-up">

    <!-- Page header -->
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Dashboard</h1>
            <p class="cbr-page-subtitle">
                Visão geral do grafo de pesquisa histórica
                <?php if ($stats['pending'] > 0): ?>
                — <span style="color:var(--cbr-hypothesis)"><?= $stats['pending'] ?> hipótese<?= $stats['pending'] > 1 ? 's' : '' ?> pendente<?= $stats['pending'] > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($role === 'coordenador'): ?>
        <a href="<?= base_url('entidades/nova') ?>"
           class="btn btn-primary d-flex align-items-center gap-2"
           id="dash-new-entity">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            Nova entidade
        </a>
        <?php endif; ?>
    </div>

    <!-- Stats grid -->
    <div class="cbr-stats-grid" role="region" aria-label="Resumo de dados">

        <div class="cbr-stat-card stat-person">
            <div class="cbr-stat-icon"><i class="bi bi-person-fill" aria-hidden="true"></i></div>
            <div class="cbr-stat-value"><?= number_format($stats['persons']) ?></div>
            <div class="cbr-stat-label">Pessoas</div>
        </div>

        <div class="cbr-stat-card stat-location">
            <div class="cbr-stat-icon"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i></div>
            <div class="cbr-stat-value"><?= number_format($stats['locations']) ?></div>
            <div class="cbr-stat-label">Locais</div>
        </div>

        <div class="cbr-stat-card stat-event">
            <div class="cbr-stat-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></div>
            <div class="cbr-stat-value"><?= number_format($stats['events']) ?></div>
            <div class="cbr-stat-label">Eventos</div>
        </div>

        <div class="cbr-stat-card stat-document">
            <div class="cbr-stat-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div>
            <div class="cbr-stat-value"><?= number_format($stats['documents']) ?></div>
            <div class="cbr-stat-label">Documentos</div>
        </div>

        <div class="cbr-stat-card stat-relation">
            <div class="cbr-stat-icon"><i class="bi bi-share" aria-hidden="true"></i></div>
            <div class="cbr-stat-value"><?= number_format($stats['relationships']) ?></div>
            <div class="cbr-stat-label">Relações</div>
        </div>

        <div class="cbr-stat-card stat-pending">
            <div class="cbr-stat-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
            <div class="cbr-stat-value"><?= number_format($stats['pending']) ?></div>
            <div class="cbr-stat-label">Hipóteses pendentes</div>
        </div>

    </div><!-- /.cbr-stats-grid -->

    <!-- Grafo vis-network -->
    <div class="cbr-graph-container mb-3" role="region" aria-label="Visualização do grafo">

        <div class="cbr-graph-header">
            <h2 class="cbr-graph-title">
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                Grafo de conhecimento
            </h2>
            <div class="cbr-graph-controls" role="toolbar" aria-label="Controles do grafo">
                <!-- Filtros de tipo/status -->
                <button class="cbr-graph-btn active" data-graph-filter="all" id="gf-all">Todos</button>
                <button class="cbr-graph-btn" data-graph-filter="confirmed" id="gf-confirmed">
                    <i class="bi bi-check-circle" aria-hidden="true"></i> Confirmados
                </button>
                <button class="cbr-graph-btn" data-graph-filter="hypothesis" id="gf-hypothesis">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i> Hipóteses
                </button>
                <button class="cbr-graph-btn" data-graph-filter="person" id="gf-person">
                    <i class="bi bi-person" aria-hidden="true"></i>
                </button>
                <button class="cbr-graph-btn" data-graph-filter="location" id="gf-location">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                </button>
                <button class="cbr-graph-btn" data-graph-filter="event" id="gf-event">
                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                </button>
                <button class="cbr-graph-btn" data-graph-filter="document" id="gf-document">
                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                </button>
                <!-- Zoom -->
                <button class="cbr-graph-btn" id="graph-fit" aria-label="Ajustar ao canvas">
                    <i class="bi bi-fullscreen" aria-hidden="true"></i>
                </button>
                <button class="cbr-graph-btn" id="graph-zoom-in" aria-label="Aproximar">
                    <i class="bi bi-zoom-in" aria-hidden="true"></i>
                </button>
                <button class="cbr-graph-btn" id="graph-zoom-out" aria-label="Afastar">
                    <i class="bi bi-zoom-out" aria-hidden="true"></i>
                </button>
                <button class="cbr-graph-btn" id="graph-physics" aria-label="Ativar/desativar física">
                    <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- Div do vis-network -->
        <div id="cerebro-graph"
             data-graph-base-url="<?= base_url() ?>"
             aria-label="Grafo interativo — clique duplo num nó para abrir o detalhe"
             role="img">
            <?php if (empty($graphData['entities'])): ?>
            <div class="cbr-empty-state">
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                <p>O grafo ainda não tem entidades.</p>
                <a href="<?= base_url('entidades/nova') ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Criar primeira entidade
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Legenda -->
        <div class="cbr-graph-legend" role="list" aria-label="Legenda do grafo">
            <div class="cbr-legend-item" role="listitem">
                <div class="cbr-legend-dot" style="background:#60a5fa"></div>
                <span>Pessoa</span>
            </div>
            <div class="cbr-legend-item" role="listitem">
                <div class="cbr-legend-dot" style="background:#34d399"></div>
                <span>Local</span>
            </div>
            <div class="cbr-legend-item" role="listitem">
                <div class="cbr-legend-dot" style="background:#f472b6"></div>
                <span>Evento</span>
            </div>
            <div class="cbr-legend-item" role="listitem">
                <div class="cbr-legend-dot" style="background:#fb923c"></div>
                <span>Documento</span>
            </div>
            <div class="cbr-legend-item" role="listitem">
                <div class="cbr-legend-dot" style="background:#7c6af7"></div>
                <span>Relação confirmada</span>
            </div>
            <div class="cbr-legend-item" role="listitem">
                <div class="cbr-legend-dot" style="background:#f59e0b;border-radius:0"></div>
                <span>Relação hipótese (tracejada)</span>
            </div>
        </div>

    </div><!-- /.cbr-graph-container -->

    <!-- Recentes -->
    <div class="cbr-dash-grid">

        <!-- Entidades recentes -->
        <div class="cbr-recent-card">
            <div class="cbr-recent-header">
                <h2 class="cbr-recent-title">Entidades recentes</h2>
                <a href="<?= base_url('entidades') ?>" class="cbr-recent-link">Ver todas</a>
            </div>
            <?php if (empty($recentEntities)): ?>
            <div class="cbr-empty-state">
                <i class="bi bi-people" aria-hidden="true"></i>
                <p>Nenhuma entidade cadastrada ainda.</p>
            </div>
            <?php else: ?>
            <ul class="cbr-recent-list" aria-label="Entidades recentes">
                <?php
                $typeIconMap = [
                    'person'   => ['icon' => 'bi-person-fill',      'css' => 'badge-person',   'bg' => 'var(--cbr-person-bg)',   'color' => 'var(--cbr-person)'],
                    'location' => ['icon' => 'bi-geo-alt-fill',      'css' => 'badge-location', 'bg' => 'var(--cbr-location-bg)', 'color' => 'var(--cbr-location)'],
                    'event'    => ['icon' => 'bi-calendar-event',    'css' => 'badge-event',    'bg' => 'var(--cbr-event-bg)',    'color' => 'var(--cbr-event)'],
                    'document' => ['icon' => 'bi-file-earmark-text', 'css' => 'badge-document', 'bg' => 'var(--cbr-document-bg)', 'color' => 'var(--cbr-document)'],
                ];
                foreach ($recentEntities as $entity):
                    $ti = $typeIconMap[$entity['type']] ?? $typeIconMap['person'];
                ?>
                <li>
                    <a href="<?= base_url('entidades/' . $entity['id']) ?>" class="cbr-recent-item">
                        <div class="cbr-recent-icon"
                             style="background:<?= $ti['bg'] ?>;color:<?= $ti['color'] ?>">
                            <i class="bi <?= $ti['icon'] ?>" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="cbr-recent-name"><?= esc($entity['name']) ?></div>
                            <div class="cbr-recent-meta"><?= esc($entity['type']) ?></div>
                        </div>
                        <div class="cbr-recent-badges">
                            <span class="<?= $entity['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                                <?= $entity['status'] === 'confirmed' ? 'Confirmado' : 'Hipótese' ?>
                            </span>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Relações recentes -->
        <div class="cbr-recent-card">
            <div class="cbr-recent-header">
                <h2 class="cbr-recent-title">Relações recentes</h2>
                <a href="<?= base_url('relacoes') ?>" class="cbr-recent-link">Ver todas</a>
            </div>
            <?php if (empty($recentRelationships)): ?>
            <div class="cbr-empty-state">
                <i class="bi bi-share" aria-hidden="true"></i>
                <p>Nenhuma relação cadastrada ainda.</p>
            </div>
            <?php else: ?>
            <ul class="cbr-recent-list" aria-label="Relações recentes">
                <?php foreach ($recentRelationships as $rel): ?>
                <li>
                    <a href="<?= base_url('relacoes') ?>" class="cbr-recent-item">
                        <div class="cbr-recent-icon"
                             style="background:var(--cbr-primary-dim);color:var(--cbr-primary)">
                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="cbr-recent-name">
                                <?= esc($rel['source_name'] ?? '—') ?>
                                <i class="bi bi-arrow-right mx-1" aria-hidden="true"></i>
                                <?= esc($rel['target_name'] ?? '—') ?>
                            </div>
                            <div class="cbr-recent-meta">
                                <?= esc($rel['relationship_type']) ?>
                                · <?= round(($rel['confidence'] ?? 0) * 100) ?>%
                            </div>
                        </div>
                        <div class="cbr-recent-badges">
                            <span class="<?= $rel['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                                <?= $rel['status'] === 'confirmed' ? 'Confirmada' : 'Hipótese' ?>
                            </span>
                        </div>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

    </div><!-- /.cbr-dash-grid -->

</div><!-- /.fade-in-up -->

<!-- Dados do grafo para o JS -->
<?php if (!empty($graphData['entities'])): ?>
<script id="graph-data" type="application/json">
<?= json_encode($graphData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();

echo view('layout/base', [
    'title'      => 'Dashboard',
    'content'    => $content,
    'pageCss'    => ['dashboard.css'],
    'pageJs'     => !empty($graphData['entities'])
                    ? ['../vendor/vis-network/vis-network.min.js', 'graph.js']
                    : [],
]);
?>

<?php if (!empty($graphData['entities'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var raw = document.getElementById('graph-data');
    if (raw && typeof CerebroGraph !== 'undefined') {
        try {
            CerebroGraph.init('cerebro-graph', JSON.parse(raw.textContent));
        } catch(e) { console.error('Graph init error:', e); }
    }
});
</script>
<?php endif; ?>

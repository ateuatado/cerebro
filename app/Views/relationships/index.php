<?php
/**
 * Cerebro — Views/relationships/index.php
 * Listagem de relações com filtros de status e busca
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';
$relationships = $relationships ?? [];

$typeConfig = [
    'person'   => ['icon'=>'bi-person-fill',      'color'=>'var(--cbr-person)'],
    'location' => ['icon'=>'bi-geo-alt-fill',      'color'=>'var(--cbr-location)'],
    'event'    => ['icon'=>'bi-calendar-event',    'color'=>'var(--cbr-event)'],
    'document' => ['icon'=>'bi-file-earmark-text', 'color'=>'var(--cbr-document)'],
];

ob_start();
?>

<div class="fade-in-up">

    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Relações</h1>
            <p class="cbr-page-subtitle">
                <span id="rel-count"><?= count($relationships) ?></span>
                relação<?= count($relationships) !== 1 ? 'ões' : '' ?> no grafo
            </p>
        </div>
        <a href="<?= base_url('relacoes/nova') ?>"
           class="btn btn-primary d-flex align-items-center gap-2"
           id="rel-new-btn">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span class="d-none d-sm-inline">Nova relação</span>
        </a>
    </div>

    <!-- Filtros -->
    <div class="cbr-filter-bar" role="search">
        <div class="cbr-search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" id="rel-search" class="form-control"
                   placeholder="Buscar por tipo de relação ou entidade…"
                   aria-label="Buscar relações">
        </div>
        <div class="cbr-filter-chips" role="group" aria-label="Filtrar por status">
            <button class="cbr-chip active" data-filter-status="all">Todas</button>
            <button class="cbr-chip" data-filter-status="confirmed">✅ Confirmadas</button>
            <button class="cbr-chip" data-filter-status="hypothesis">🟡 Hipóteses</button>
        </div>
    </div>

    <?php if (empty($relationships)): ?>
    <div class="cbr-empty-state">
        <i class="bi bi-share" aria-hidden="true"></i>
        <p>Nenhuma relação cadastrada ainda.<br>
           Toda relação deve ser rastreada a uma fonte documental.</p>
        <a href="<?= base_url('relacoes/nova') ?>" class="btn btn-primary btn-sm" id="rel-empty-cta">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Criar primeira relação
        </a>
    </div>
    <?php else: ?>

    <!-- Lista vazia (filtro) -->
    <div id="rel-empty-filter" class="cbr-empty-state" style="display:none">
        <i class="bi bi-search" aria-hidden="true"></i>
        <p>Nenhuma relação encontrada com esses filtros.</p>
    </div>

    <!-- Cards de relação -->
    <div id="rel-list" style="display:flex;flex-direction:column;gap:.625rem">
        <?php foreach ($relationships as $rel):
            $srcEntity = $relatedEntities[$rel['source_entity_id']] ?? null;
            $tgtEntity = $relatedEntities[$rel['target_entity_id']] ?? null;
            $srcConf   = $typeConfig[$srcEntity['type'] ?? ''] ?? ['icon'=>'bi-circle','color'=>'var(--cbr-text-muted)'];
            $tgtConf   = $typeConfig[$tgtEntity['type'] ?? ''] ?? ['icon'=>'bi-circle','color'=>'var(--cbr-text-muted)'];
            $confidence= round(($rel['confidence'] ?? 0.75) * 100);
            $isConfirmed = $rel['status'] === 'confirmed';
            $searchStr = strtolower(
                ($srcEntity['name'] ?? '') . ' ' .
                ($tgtEntity['name'] ?? '') . ' ' .
                ($rel['relationship_type'] ?? '')
            );
        ?>
        <div class="cbr-card p-0 overflow-hidden rel-item"
             data-status="<?= esc($rel['status']) ?>"
             data-search="<?= esc($searchStr) ?>">

            <!-- Barra de cor por status -->
            <div style="height:3px;background:<?= $isConfirmed ? 'var(--cbr-confirmed)' : 'var(--cbr-hypothesis)' ?>"></div>

            <div style="padding:1rem 1.25rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap">

                <!-- Entidades da relação -->
                <div style="flex:1;display:flex;align-items:center;gap:.625rem;min-width:0;flex-wrap:wrap">

                    <!-- Origem -->
                    <?php if ($srcEntity): ?>
                    <a href="<?= base_url('entidades/' . $srcEntity['id']) ?>"
                       class="d-flex align-items-center gap-1 text-decoration-none"
                       style="color:var(--cbr-text);font-weight:500;font-size:.9375rem">
                        <i class="bi <?= $srcConf['icon'] ?>"
                           style="color:<?= $srcConf['color'] ?>" aria-hidden="true"></i>
                        <?= esc($srcEntity['name']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-subtle">#<?= $rel['source_entity_id'] ?></span>
                    <?php endif; ?>

                    <!-- Seta e tipo -->
                    <div style="display:flex;align-items:center;gap:.375rem;flex-shrink:0">
                        <?php if ($rel['direction'] === 'directed'): ?>
                        <i class="bi bi-arrow-right-short text-subtle" aria-hidden="true"></i>
                        <?php else: ?>
                        <i class="bi bi-arrow-left-right text-subtle" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span class="cbr-relation-type"><?= esc($rel['relationship_type']) ?></span>
                        <?php if ($rel['direction'] === 'directed'): ?>
                        <i class="bi bi-arrow-right-short text-subtle" aria-hidden="true"></i>
                        <?php else: ?>
                        <i class="bi bi-arrow-left-right text-subtle" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>

                    <!-- Destino -->
                    <?php if ($tgtEntity): ?>
                    <a href="<?= base_url('entidades/' . $tgtEntity['id']) ?>"
                       class="d-flex align-items-center gap-1 text-decoration-none"
                       style="color:var(--cbr-text);font-weight:500;font-size:.9375rem">
                        <i class="bi <?= $tgtConf['icon'] ?>"
                           style="color:<?= $tgtConf['color'] ?>" aria-hidden="true"></i>
                        <?= esc($tgtEntity['name']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-subtle">#<?= $rel['target_entity_id'] ?></span>
                    <?php endif; ?>
                </div>

                <!-- Metadados e ações -->
                <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0;flex-wrap:wrap">

                    <!-- Confiança -->
                    <div style="display:flex;align-items:center;gap:.375rem">
                        <div style="width:40px;height:4px;background:var(--cbr-border);border-radius:2px;overflow:hidden">
                            <div style="width:<?= $confidence ?>%;height:100%;background:<?= $isConfirmed ? 'var(--cbr-confirmed)' : 'var(--cbr-hypothesis)' ?>;border-radius:2px"></div>
                        </div>
                        <span style="font-size:.75rem;color:var(--cbr-text-muted)"><?= $confidence ?>%</span>
                    </div>

                    <!-- Status badge -->
                    <span class="<?= $isConfirmed ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                        <?= $isConfirmed ? 'Confirmada' : 'Hipótese' ?>
                    </span>

                    <!-- Confirmar (coordenador) -->
                    <?php if ($role === 'coordenador' && !$isConfirmed): ?>
                    <button class="btn-confirm"
                            data-confirm-rel="<?= $rel['id'] ?>"
                            data-confirm-url="<?= base_url('relacoes/' . $rel['id'] . '/confirmar') ?>"
                            style="font-size:.75rem;padding:.25rem .6rem"
                            id="confirm-rel-<?= $rel['id'] ?>">
                        <i class="bi bi-patch-check" aria-hidden="true"></i>
                    </button>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Fonte documental -->
            <?php
            $srcRef = is_string($rel['source_reference'])
                ? (json_decode($rel['source_reference'], true) ?? [])
                : ($rel['source_reference'] ?? []);
            $docEntity = !empty($rel['source_document_id'])
                ? ($relatedEntities[$rel['source_document_id']] ?? null)
                : null;
            if ($docEntity || !empty($srcRef)):
            ?>
            <div style="padding:.625rem 1.25rem;background:var(--cbr-surface-2);border-top:1px solid var(--cbr-border);font-size:.8125rem;color:var(--cbr-text-muted);display:flex;gap:.75rem;flex-wrap:wrap;align-items:center">
                <i class="bi bi-file-earmark-text" style="color:var(--cbr-document)" aria-hidden="true"></i>
                <?php if ($docEntity): ?>
                <a href="<?= base_url('entidades/' . $docEntity['id']) ?>"
                   style="color:var(--cbr-document)">
                    <?= esc($docEntity['name']) ?>
                </a>
                <?php endif; ?>
                <?php if (!empty($srcRef)): ?>
                <?php foreach ($srcRef as $k => $v): ?>
                <span><span class="text-subtle"><?= esc(ucwords(str_replace('_',' ',$k))) ?>:</span> <?= esc($v) ?></span>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

</div>

<script>
// Filtro inline (simples, sem arquivo extra)
(function() {
    var searchInput  = document.getElementById('rel-search');
    var statusChips  = document.querySelectorAll('[data-filter-status]');
    var items        = document.querySelectorAll('.rel-item');
    var emptyFilter  = document.getElementById('rel-empty-filter');
    var activeStatus = 'all';
    var searchTerm   = '';

    function apply() {
        var visible = 0;
        items.forEach(function(item) {
            var statusOk = activeStatus === 'all' || item.dataset.status === activeStatus;
            var searchOk = !searchTerm || (item.dataset.search || '').includes(searchTerm);
            var show = statusOk && searchOk;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (emptyFilter) emptyFilter.style.display = visible === 0 ? 'block' : 'none';
        var counter = document.getElementById('rel-count');
        if (counter) counter.textContent = visible;
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            searchTerm = this.value.toLowerCase().trim();
            apply();
        });
    }

    statusChips.forEach(function(chip) {
        chip.addEventListener('click', function() {
            statusChips.forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');
            activeStatus = this.dataset.filterStatus;
            apply();
        });
    });

    // Confirmar relação (form submit)
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
})();
</script>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Relações',
    'breadcrumbs'=> [
        ['label'=>'Dashboard', 'url'=>base_url('/')],
        ['label'=>'Relações',  'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css'],
    'pageJs'     => [],
]);
?>

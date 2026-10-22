<?php if (!empty($unresolvedRelationships)): ?>
<section class="cbr-detail-section mt-3" aria-labelledby="unresolved-title">
    <div class="cbr-detail-section-header">
        <h2 class="cbr-detail-section-title" id="unresolved-title">Sugestões de relação para revisão (<?= count($unresolvedRelationships) ?>)</h2>
    </div>
    <div class="cbr-detail-section-body">
        <p>Estas sugestões não foram incluídas no grafo histórico. Consulte a fonte, corrija as entidades e cadastre a relação pela revisão existente. O registro original fica preservado aqui.</p>
        <a class="btn btn-outline-primary mb-3" href="<?= base_url('relacoes/nova') ?>">Cadastrar relação após revisão</a>
        <?php foreach ($unresolvedRelationships as $pending):
            $suggestion = is_string($pending['suggestion']) ? json_decode($pending['suggestion'], true) : $pending['suggestion'];
        ?>
        <details class="border rounded p-3 mb-2">
            <summary><?= esc($suggestion['source_name'] ?? 'Origem ausente') ?> → <?= esc($suggestion['target_name'] ?? 'Destino ausente') ?> <span class="badge-hypothesis">Hipótese não resolvida</span></summary>
            <p class="mt-2"><?= esc($pending['reason']) ?></p>
            <p>Relação sugerida: <?= esc($suggestion['relationship_type'] ?? 'Não informada') ?></p>
            <?php if (!empty($suggestion['excerpt'])): ?>
            <blockquote><?= esc($suggestion['excerpt']) ?></blockquote>
            <?php endif; ?>
        </details>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

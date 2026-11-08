<?php foreach ($sources as $source): ?>
    <article class="border-bottom py-2">
        <div class="fw-semibold"><?= esc($source['source_title']) ?><?= empty($source['locator']) ? '' : ' · ' . esc($source['locator']) ?></div>
        <?php if ($overview): ?>
            <a href="<?= base_url('produtos/' . $source['product_id'] . '/partes/' . $source['part_id'] . '/editar#fontes-desta-parte') ?>"><?= esc($source['part_title']) ?></a> ·
        <?php endif ?>
        <?php if ($source['note_id'] !== null): ?>
            <a href="<?= base_url('caderno/fichamentos/' . $source['note_id'] . '/editar') ?>">Abrir ficha</a> ·
        <?php endif ?>
        <?php if ($source['original_id'] !== null): ?>
            <?php $page = preg_match('/^p\. ([1-9][0-9]{0,8})$/D', $source['locator'] ?? '', $match) ? '?pagina=' . $match[1] : ''; ?>
            <a href="<?= base_url('documentos/' . $source['original_id'] . '/revisar') . $page ?>"><?= $page === '' ? 'Abrir documento' : 'Voltar à página' ?></a>
        <?php else: ?>
            <span>Original indisponível</span>
        <?php endif ?>
        <?php if (!$overview): ?>
            <form class="d-inline-block" method="post" action="<?= base_url('produtos/' . $source['product_id'] . '/partes/' . $source['part_id'] . '/fontes/' . $source['id'] . '/retirar') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary" aria-label="Retirar vínculo com <?= esc($source['source_title']) ?>">Retirar vínculo</button>
            </form>
        <?php endif ?>
    </article>
<?php endforeach ?>

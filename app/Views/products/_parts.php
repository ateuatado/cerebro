<ol class="mb-0">
    <?php foreach ($parts as $index => $part): ?>
        <?php $path = 'produtos/' . $productId . '/partes/' . $part['id']; ?>
        <li class="mb-2" id="parte-<?= esc($part['id']) ?>">
            <a href="<?= base_url($path . '/editar') ?>"><?= esc($part['title']) ?></a>
            <?php if (count($parts) > 1): ?>
                <form class="d-inline-flex gap-1" method="post" action="<?= base_url($path . '/mover') ?>">
                    <?= csrf_field() ?>
                    <?php if ($index > 0): ?>
                        <button class="btn btn-sm btn-outline-secondary" name="direction" value="up" aria-label="Subir <?= esc($part['title']) ?>">↑</button>
                    <?php endif ?>
                    <?php if ($index < count($parts) - 1): ?>
                        <button class="btn btn-sm btn-outline-secondary" name="direction" value="down" aria-label="Descer <?= esc($part['title']) ?>">↓</button>
                    <?php endif ?>
                </form>
            <?php endif ?>
            <?php if (isset($outline[$part['id']])): ?>
                <?= view('products/_parts', ['parts' => $outline[$part['id']], 'outline' => $outline, 'productId' => $productId]) ?>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ol>

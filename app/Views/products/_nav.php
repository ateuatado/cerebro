<nav class="nav nav-tabs mb-3" aria-label="Produto intelectual">
    <a class="nav-link<?= $tab === 'structure' ? ' active' : '' ?>" href="<?= base_url('produtos/' . $productId) ?>">Estrutura e texto</a>
    <a class="nav-link<?= $tab === 'bibliography' ? ' active' : '' ?>" href="<?= base_url('produtos/' . $productId . '/bibliografia') ?>">Bibliografia</a>
</nav>

<?php
$graphHidden = in_array($entity['graph_hidden'] ?? false, [true, 1, '1', 't', 'true'], true);
?>
<form action="<?= base_url('entidades/' . (int) $entity['id'] . '/visibilidade-grafo') ?>" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="graph_hidden" value="<?= $graphHidden ? '0' : '1' ?>">
    <button type="submit" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1">
        <i class="bi <?= $graphHidden ? 'bi-eye' : 'bi-eye-slash' ?>" aria-hidden="true"></i>
        <span><?= $graphHidden ? 'Mostrar no grafo' : 'Ocultar no grafo' ?></span>
    </button>
</form>

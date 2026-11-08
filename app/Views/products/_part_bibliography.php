<?php $path = 'produtos/' . $product['id'] . '/partes/' . $part['id'] . '/obras'; ?>
<section class="cbr-card mt-4 p-3" id="obras-desta-parte" aria-labelledby="part-works-title">
    <h2 id="part-works-title">Obras desta parte</h2>
    <p>Separe as leituras para este trecho. Salve o texto antes de alterar a lista.</p>
    <?php if ($selectedWorks === []): ?><p>Nenhuma obra vinculada a esta parte.</p><?php endif ?>
    <?php foreach ($selectedWorks as $work): ?>
        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
            <a href="<?= base_url('biblioteca/' . $work['id']) ?>"><?= esc($work['title']) ?></a>
            <span><?= esc($work['authors'] ?? '') ?></span>
            <form method="post" action="<?= base_url($path . '/' . $work['id'] . '/retirar') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary" aria-label="Retirar <?= esc($work['title']) ?> desta parte">Retirar desta parte</button>
            </form>
        </div>
    <?php endforeach ?>
    <?php if ($availableWorks !== []): ?>
        <form method="post" action="<?= base_url($path . '/adicionar') ?>" class="row g-2 align-items-end mb-3">
            <?= csrf_field() ?>
            <div class="col-sm">
                <label class="form-label" for="part-work-query">Filtrar por título, autoria ou identificador</label>
                <input type="search" class="form-control mb-2" id="part-work-query" autocomplete="off" aria-controls="part-work">
                <label class="form-label" for="part-work">Obras disponíveis na bibliografia do produto</label>
                <select class="form-select" id="part-work" name="work_id" required>
                    <option value="">Selecione uma obra</option>
                    <?php foreach ($availableWorks as $work): ?>
                        <option value="<?= esc($work['id']) ?>" data-identifier="<?= esc($work['identifier'] ?? '') ?>"><?= esc($work['title']) ?><?= empty($work['authors']) ? '' : ' — ' . esc($work['authors']) ?></option>
                    <?php endforeach ?>
                </select>
                <p class="form-text mb-0" id="part-work-count" role="status"></p>
            </div>
            <div class="col-sm-auto"><button class="btn btn-outline-primary">Adicionar a esta parte</button></div>
        </form>
    <?php endif ?>
    <a href="<?= base_url('produtos/' . $product['id'] . '/bibliografia') ?>">Abrir bibliografia do produto</a>
</section>

<section class="cbr-card mt-4 p-3" id="fontes-desta-parte" aria-labelledby="part-sources-title">
    <h2 id="part-sources-title">Fontes desta parte</h2>
    <p>Vincule um documento do acervo ou uma ficha para indicar uma passagem. Salve o texto antes de alterar os vínculos.</p>
    <?php if ($sources === []): ?><p>Nenhuma fonte vinculada a esta parte.</p><?php endif ?>
    <?= view('products/_source_links', ['sources' => $sources, 'overview' => false]) ?>
    <?php if ($availableDocuments !== [] || $availableNotes !== []): ?>
        <form class="row g-2 align-items-end mt-2" method="post" action="<?= base_url('produtos/' . $product['id'] . '/partes/' . $part['id'] . '/fontes/adicionar') ?>">
            <?= csrf_field() ?>
            <div class="col-sm">
                <label for="part-source" class="form-label">Documento ou ficha</label>
                <select id="part-source" name="source" class="form-select" required>
                    <option value="">Selecione uma fonte</option>
                    <?php if ($availableDocuments !== []): ?>
                        <optgroup label="Documentos do acervo">
                            <?php foreach ($availableDocuments as $document): ?>
                                <option value="document:<?= esc($document['id']) ?>"><?= esc($document['name']) ?> (#<?= esc($document['id']) ?>)</option>
                            <?php endforeach ?>
                        </optgroup>
                    <?php endif ?>
                    <?php if ($availableNotes !== []): ?>
                        <optgroup label="Fichas documentais">
                            <?php foreach ($availableNotes as $note): ?>
                                <option value="note:<?= esc($note['id']) ?>"><?= esc($note['document_label']) ?><?= $note['locator'] === '' ? '' : ' · ' . esc($note['locator']) ?> (ficha #<?= esc($note['id']) ?>)</option>
                            <?php endforeach ?>
                        </optgroup>
                    <?php endif ?>
                </select>
            </div>
            <div class="col-sm-auto"><button class="btn btn-outline-primary">Vincular fonte</button></div>
        </form>
    <?php endif ?>
</section>

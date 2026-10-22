<?php
/** Evidências sugeridas: separadas dos atributos revisados da identidade. */
$displayValue = static function ($value) use (&$displayValue): string {
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $key => $item) {
            $parts[] = (is_int($key) ? '' : $key . ': ') . $displayValue($item);
        }
        return implode('; ', $parts);
    }
    return is_bool($value) ? ($value ? 'Sim' : 'Não') : (string) $value;
};
?>
<?php if (!empty($observations)): ?>
<section class="cbr-detail-section mt-3" aria-labelledby="observations-title">
    <div class="cbr-detail-section-header">
        <h2 class="cbr-detail-section-title" id="observations-title">Ocorrências nas fontes (<?= count($observations) ?>)</h2>
    </div>
    <div class="cbr-detail-section-body">
        <p>Informações sugeridas pela extração, aguardando revisão. Valores diferentes são preservados por fonte e não substituem os atributos da entidade. Use a edição da entidade para registrar informações revisadas.</p>
        <?php foreach ($observations as $observation):
            $values = is_string($observation['attributes']) ? json_decode($observation['attributes'], true) : $observation['attributes'];
            $reference = is_string($observation['source_reference']) ? json_decode($observation['source_reference'], true) : $observation['source_reference'];
            $suggestedType = $observation['suggested_type'];
        ?>
        <details class="border rounded p-3 mb-2">
            <summary>
                <?= esc($observation['document_name']) ?>
                <span class="badge-hypothesis">Hipótese</span>
                <?php if ($suggestedType !== $entity['type']): ?>
                <span class="text-warning">Classificação divergente: <?= esc($typeConfig[$suggestedType]['label'] ?? $suggestedType) ?></span>
                <?php endif; ?>
            </summary>
            <p class="mt-2"><a href="<?= base_url('entidades/' . (int) $observation['source_document_id']) ?>">Consultar fonte primária</a></p>
            <?php if (!empty($reference['parte'])): ?>
            <p>Bloco de extração: <?= esc($reference['parte']) ?></p>
            <?php endif; ?>
            <?php if (!empty($reference['trecho'])): ?>
            <blockquote><?= esc($reference['trecho']) ?></blockquote>
            <?php endif; ?>
            <?php if (empty($values)): ?>
            <p>Menção registrada sem atributos adicionais.</p>
            <?php else: ?>
            <dl>
                <?php foreach ($values as $key => $value): ?>
                <dt><?= esc($attrLabels[$key] ?? ucwords(str_replace('_', ' ', $key))) ?></dt>
                <dd><?= esc($displayValue($value)) ?></dd>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>
        </details>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

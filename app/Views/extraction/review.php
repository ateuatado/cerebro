<?php
/**
 * Cerebro — Views/extraction/review.php
 * Interface de revisão Human-in-the-Loop (Documento Original vs Hipóteses da IA)
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

$doc             = $doc             ?? [];
$relationships   = $relationships   ?? [];
$relatedEntities = $relatedEntities ?? [];

$attrs = is_string($doc['attributes'])
    ? (json_decode($doc['attributes'], true) ?? [])
    : ($doc['attributes'] ?? []);

$filePath = $attrs['caminho_arquivo'] ?? '';
$format   = strtolower($attrs['formato'] ?? pathinfo($doc['name'], PATHINFO_EXTENSION));
$hasFile  = !empty($filePath) && file_exists($filePath);

$typeConfig = [
    'person'   => ['icon'=>'bi-person-fill',      'css'=>'person',   'color'=>'var(--cbr-person)'],
    'location' => ['icon'=>'bi-geo-alt-fill',      'css'=>'location', 'color'=>'var(--cbr-location)'],
    'event'    => ['icon'=>'bi-calendar-event',    'css'=>'event',    'color'=>'var(--cbr-event)'],
    'document' => ['icon'=>'bi-file-earmark-text', 'css'=>'document', 'color'=>'var(--cbr-document)'],
];

$pendingHypotheses = array_filter($relationships, fn($r) => $r['status'] === 'hypothesis');

ob_start();
?>

<div class="fade-in-up">

    <!-- Header -->
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">
                <i class="bi bi-robot" style="color:var(--cbr-primary)"></i>
                Curadoria & Revisão de Fonte Primária
            </h1>
            <p class="cbr-page-subtitle">
                Documento: <strong><?= esc($doc['name']) ?></strong> —
                <?= count($pendingHypotheses) ?> hipótese(s) aguardando validação do pesquisador
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?php if ($role === 'coordenador' && !empty($pendingHypotheses)): ?>
            <form action="<?= base_url('documentos/' . $doc['id'] . '/aprovar-todas') ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" id="btn-approve-all">
                    <i class="bi bi-patch-check-fill"></i> Aprovar todas as hipóteses
                </button>
            </form>
            <?php endif; ?>

            <a href="<?= base_url('entidades/' . $doc['id']) ?>" class="btn btn-outline-secondary d-flex align-items-center gap-1">
                <i class="bi bi-arrow-left"></i> Voltar à entidade
            </a>
        </div>
    </div>

    <!-- Layout Dividido (Split-View) -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem" class="cbr-split-layout">

        <!-- LADO ESQUERDO: Imagem Original do Documento + Texto OCR -->
        <div>
            <!-- Card de Visualização do Arquivo Original -->
            <div class="cbr-card mb-3 p-0 overflow-hidden" style="border: 1px solid var(--cbr-primary-dim)">
                <div style="padding:.75rem 1rem;background:var(--cbr-surface-2);border-bottom:1px solid var(--cbr-border);display:flex;align-items:center;justify-content:space-between">
                    <h2 style="font-size:.9375rem;font-weight:700;color:var(--cbr-text);margin:0;display:flex;align-items:center;gap:.5rem">
                        <i class="bi bi-file-earmark-image" style="color:var(--cbr-primary)"></i>
                        Arquivo Original (Fonte Primária)
                    </h2>
                    <?php if ($hasFile): ?>
                    <a href="<?= base_url('documentos/' . $doc['id'] . '/arquivo') ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Abrir em Tela Cheia
                    </a>
                    <?php endif; ?>
                </div>

                <div style="background:#0f172a;text-align:center;padding:1rem;max-height:500px;overflow:auto">
                    <?php if ($hasFile): ?>
                        <?php if (in_array($format, ['jpg', 'jpeg', 'png', 'webp', 'bmp'])): ?>
                            <img src="<?= base_url('documentos/' . $doc['id'] . '/arquivo') ?>"
                                 alt="<?= esc($doc['name']) ?>"
                                 class="img-fluid rounded border shadow-sm"
                                 style="max-height:460px;object-fit:contain;cursor:zoom-in"
                                 title="Clique para abrir imagem em tamanho real"
                                 onclick="window.open(this.src, '_blank')">
                        <?php elseif ($format === 'pdf'): ?>
                            <iframe src="<?= base_url('documentos/' . $doc['id'] . '/arquivo') ?>"
                                    style="width:100%;height:460px;border:none"></iframe>
                        <?php else: ?>
                            <div class="py-4 text-center">
                                <i class="bi bi-file-earmark-text" style="font-size:3rem;color:var(--cbr-primary)"></i>
                                <p class="mt-2" style="font-size:.875rem;color:var(--cbr-text)">
                                    Documento de texto original disponível.
                                </p>
                                <a href="<?= base_url('documentos/' . $doc['id'] . '/arquivo') ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-download me-1"></i> Baixar Arquivo Original
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="py-4 text-center">
                            <i class="bi bi-exclamation-triangle" style="font-size:2.5rem;color:var(--cbr-hypothesis)"></i>
                            <p class="mt-2" style="font-size:.875rem;color:var(--cbr-text);margin:0">
                                O arquivo físico desta imagem não foi encontrado no servidor neste caminho:
                            </p>
                            <code style="font-size:.75rem;color:var(--cbr-primary);word-break:break-all" class="d-block my-2"><?= esc($filePath ?: 'Nenhum caminho registrado') ?></code>
                            <p class="text-subtle" style="font-size:.75rem;margin:0">
                                Execute o comando <code>php spark ingest:folder "C:\sua\pasta"</code> para vincular o arquivo original.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Texto / Transcrição Analisada -->
            <div class="cbr-card mb-3">
                <h3 style="font-size:.875rem;font-weight:700;color:var(--cbr-text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.5rem">
                    <i class="bi bi-body-text me-1"></i> Texto / Transcrição OCR Analisada
                </h3>
                <div style="padding:1rem;background:var(--cbr-surface-2);border:1px solid var(--cbr-border);border-radius:var(--cbr-radius-sm);font-size:.875rem;color:var(--cbr-text);max-height:350px;overflow-y:auto;white-space:pre-line;line-height:1.6">
                    <?php
                    $desc = $attrs['descricao'] ?? $attrs['notas'] ?? $attrs['titulo'] ?? $doc['name'];
                    if (strpos($desc, 'Metadados EXIF:') !== false) {
                        $parts = explode('Metadados EXIF:', $desc);
                        $desc = trim($parts[0]);
                    }
                    echo esc($desc);
                    ?>
                </div>
            </div>
        </div>

        <!-- LADO DIREITO: Hipóteses Extraídas pela IA -->
        <div>
            <div class="cbr-card p-0 overflow-hidden">
                <div style="padding:.875rem 1.125rem;background:var(--cbr-surface-2);border-bottom:1px solid var(--cbr-border);display:flex;align-items:center;justify-content:space-between">
                    <h2 style="font-size:.9375rem;font-weight:600;color:var(--cbr-text);margin:0;display:flex;align-items:center;gap:.5rem">
                        <i class="bi bi-diagram-3" style="color:var(--cbr-primary)"></i>
                        Hipóteses Extraídas pela IA
                    </h2>
                    <span style="font-size:.75rem;color:var(--cbr-text-subtle)"><?= count($relationships) ?> relação(ões)</span>
                </div>

                <div style="padding:1.125rem">
                    <?php if (empty($relationships)): ?>
                    <div class="cbr-empty-state py-4">
                        <i class="bi bi-robot" style="font-size:2.5rem;color:var(--cbr-text-subtle)" aria-hidden="true"></i>
                        <p style="font-size:.875rem;margin-top:.5rem">
                            Nenhuma hipótese extraída para este documento ainda.
                        </p>
                        <form action="<?= base_url('documentos/' . $doc['id'] . '/extrair') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2">
                                <i class="bi bi-play-fill"></i> Executar Extração por IA (OCR + DeepSeek)
                            </button>
                        </form>
                    </div>
                    <?php else: ?>

                    <div style="display:flex;flex-direction:column;gap:.75rem">
                        <?php foreach ($relationships as $rel):
                            $srcEntity = $relatedEntities[$rel['source_entity_id']] ?? null;
                            $tgtEntity = $relatedEntities[$rel['target_entity_id']] ?? null;
                            $srcConf   = $typeConfig[$srcEntity['type'] ?? ''] ?? ['icon'=>'bi-circle','color'=>'var(--cbr-text-muted)'];
                            $tgtConf   = $typeConfig[$tgtEntity['type'] ?? ''] ?? ['icon'=>'bi-circle','color'=>'var(--cbr-text-muted)'];
                            $confidence = round(($rel['confidence'] ?? 0.75) * 100);
                            $isConfirmed = $rel['status'] === 'confirmed';

                            $ref = is_string($rel['source_reference'])
                                ? (json_decode($rel['source_reference'], true) ?? [])
                                : ($rel['source_reference'] ?? []);
                            $excerpt = $ref['trecho'] ?? '';
                        ?>
                        <div class="cbr-card p-3 mb-0" style="background:var(--cbr-surface);border:1px solid <?= $isConfirmed ? 'var(--cbr-confirmed)' : 'var(--cbr-hypothesis)' ?>">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.5rem">
                                <span class="<?= $isConfirmed ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                                    <?= $isConfirmed ? '✅ Fato Confirmado' : '🟡 Hipótese da IA' ?>
                                </span>
                                <span style="font-size:.75rem;font-weight:600;color:var(--cbr-primary)">
                                    Confiança: <?= $confidence ?>%
                                </span>
                            </div>

                            <!-- Tripla: Origem -> Tipo -> Destino -->
                            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;font-size:.875rem;margin-bottom:.5rem">
                                <?php if ($srcEntity): ?>
                                <a href="<?= base_url('entidades/' . $srcEntity['id']) ?>" style="font-weight:600;color:var(--cbr-text)">
                                    <i class="bi <?= $srcConf['icon'] ?>" style="color:<?= $srcConf['color'] ?>"></i>
                                    <?= esc($srcEntity['name']) ?>
                                </a>
                                <?php endif; ?>

                                <span class="cbr-relation-type"><?= esc($rel['relationship_type']) ?></span>

                                <?php if ($tgtEntity): ?>
                                <a href="<?= base_url('entidades/' . $tgtEntity['id']) ?>" style="font-weight:600;color:var(--cbr-text)">
                                    <i class="bi <?= $tgtConf['icon'] ?>" style="color:<?= $tgtConf['color'] ?>"></i>
                                    <?= esc($tgtEntity['name']) ?>
                                </a>
                                <?php endif; ?>
                            </div>

                            <!-- Trecho de Origem (Rastreabilidade) -->
                            <?php if ($excerpt): ?>
                            <div style="padding:.5rem;background:var(--cbr-surface-2);border-radius:var(--cbr-radius-sm);font-size:.75rem;color:var(--cbr-text-muted);font-style:italic">
                                "<i class="bi bi-quote"></i> <?= esc($excerpt) ?>"
                            </div>
                            <?php endif; ?>

                            <!-- Botão individual de confirmação (para Coordenador) -->
                            <?php if ($role === 'coordenador' && !$isConfirmed): ?>
                            <div class="mt-2 text-end">
                                <button class="btn-confirm"
                                        data-confirm-rel="<?= $rel['id'] ?>"
                                        data-confirm-url="<?= base_url('relacoes/' . $rel['id'] . '/confirmar') ?>">
                                    <i class="bi bi-patch-check"></i> Confirmar Fato
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm-rel]').forEach(btn => {
        btn.addEventListener('click', async function() {
            const url = this.dataset.confirmUrl;
            this.disabled = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: '<?= csrf_token() ?>=<?= csrf_hash() ?>'
                });
                const data = await res.json();
                if (data.success) {
                    const card = this.closest('.cbr-card');
                    card.style.borderColor = 'var(--cbr-confirmed)';
                    const badge = card.querySelector('.badge-hypothesis');
                    if (badge) {
                        badge.className = 'badge-confirmed';
                        badge.textContent = '✅ Fato Confirmado';
                    }
                    this.remove();
                } else {
                    alert(data.error || 'Erro ao confirmar');
                    this.disabled = false;
                }
            } catch (e) {
                alert('Erro de conexão');
                this.disabled = false;
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'content'   => $content,
    'pageTitle' => 'Revisão: ' . esc($doc['name']),
]);

<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\EntityModel;
use App\Models\DocumentModel;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\RelationshipModel;
use App\Services\DeepSeekService;
use App\Services\DocumentParserService;

/**
 * IngestFolderCommand — Comando CLI para varrer uma pasta e processar documentos de múltiplos formatos (TXT, PDF, JPG, PNG, etc.) em lote.
 *
 * Uso: php spark ingest:folder "C:\caminho\para\pasta\com\documentos"
 */
class IngestFolderCommand extends BaseCommand
{
    protected $group       = 'Cerebro';
    protected $name        = 'ingest:folder';
    protected $description = 'Varre uma pasta de documentos históricos (TXT, PDF, JPG, PNG) e faz a extração automática em lote por IA.';
    protected $usage       = 'ingest:folder <caminho_da_pasta> [opcoes]';
    protected $arguments   = [
        'caminho_da_pasta' => 'Caminho absoluto ou relativo do diretório contendo os arquivos históricos (.txt, .pdf, .jpg, .png, .json)',
    ];
    protected $options     = [
        '--auto-confirm-100' => 'Se presente, confirma automaticamente relações com 100% de confiança (1.0). As demais entram como hipótese.',
        '--limit'            => 'Limita a quantidade de arquivos a processar nesta rodada (ex: --limit 50)',
    ];

    public function run(array $params)
    {
        $folderPath = $params[0] ?? CLI::prompt('Informe o caminho da pasta com os documentos');

        if (empty($folderPath) || !is_dir($folderPath)) {
            CLI::error("Diretório inválido ou não encontrado: '{$folderPath}'");
            return;
        }

        $autoConfirm100 = CLI::getOption('auto-confirm-100') !== null;
        $limitOption    = CLI::getOption('limit');
        $maxFiles       = $limitOption ? intval($limitOption) : 0;

        CLI::write("═══════════════════════════════════════════════════════════", 'yellow');
        CLI::write("  CEREBRO — INGESTÃO E EXTRAÇÃO EM LOTE VIA IA (DEEPSEEK)", 'yellow');
        CLI::write("═══════════════════════════════════════════════════════════", 'yellow');
        CLI::write("Pasta: " . realpath($folderPath));
        CLI::write("Formatos: TXT, PDF, JPG, JPEG, PNG, WEBP, JSON, CSV");
        CLI::write("Modo: Relações < 100% gravadas como HIPÓTESE" . ($autoConfirm100 ? " (100% auto-confirmadas)" : ""));
        CLI::write("");

        // Encontrar arquivos suportados
        $files = $this->findDocumentFiles($folderPath);
        $totalFound = count($files);

        if ($totalFound === 0) {
            CLI::error("Nenhum arquivo suportado (.txt, .pdf, .jpg, .png, .json, .csv) encontrado na pasta informada.");
            return;
        }

        if ($maxFiles > 0 && $maxFiles < $totalFound) {
            $files = array_slice($files, 0, $maxFiles);
            CLI::write("Processando os primeiros {$maxFiles} de {$totalFound} arquivos encontrados...\n", 'light_cyan');
        } else {
            CLI::write("Total de arquivos encontrados: {$totalFound}\n", 'light_cyan');
        }

        $deepSeek    = new DeepSeekService();
        $docParser   = new DocumentParserService();
        $docModel    = new DocumentModel();
        $entityModel = new EntityModel();
        $relModel    = new RelationshipModel();
        $modelMap    = [
            'person'   => new PersonModel(),
            'location' => new LocationModel(),
            'event'    => new EventModel(),
        ];

        $processedCount = 0;
        $extractedEntitiesTotal = 0;
        $extractedRelsTotal     = 0;
        $hypothesesTotal        = 0;

        foreach ($files as $index => $filePath) {
            $fileName = basename($filePath);
            $ext      = pathinfo($filePath, PATHINFO_EXTENSION);
            $num      = $index + 1;
            CLI::write("[{$num}/" . count($files) . "] Lendo arquivo [{$ext}]: {$fileName}...", 'green');

            try {
                // Ler e extrair texto do arquivo (TXT, PDF, JPG, PNG)
                $parseResult = $docParser->parseFile($filePath, $ext);
                $content     = $parseResult['text'] ?? '';

                if (empty(trim($content))) {
                    CLI::write("  └─ Sem conteúdo legível. Ignorado.", 'gray');
                    continue;
                }

                // Verificar se o documento já foi cadastrado
                $existingDoc = $entityModel->where('name', $fileName)->where('type', 'document')->first();
                if ($existingDoc) {
                    $documentId = $existingDoc['id'];
                    $currentAttrs = is_string($existingDoc['attributes']) ? (json_decode($existingDoc['attributes'], true) ?? []) : ($existingDoc['attributes'] ?? []);
                    $currentAttrs['descricao'] = mb_substr($content, 0, 4000);
                    $currentAttrs['formato'] = strtoupper($ext);
                    $entityModel->update($documentId, [
                        'attributes' => json_encode($currentAttrs, JSON_UNESCAPED_UNICODE)
                    ]);
                    CLI::write("  └─ Documento existente (ID: {$documentId}). Atualizando com OCR e re-processando...", 'yellow');
                } else {
                    // Cadastrar novo documento
                    $documentId = $docModel->insert([
                        'name'       => $fileName,
                        'type'       => 'document',
                        'status'     => 'confirmed',
                        'created_by' => 1,
                        'validated_by' => 1,
                        'attributes' => json_encode([
                            'titulo'                 => $fileName,
                            'formato'                => strtoupper($ext),
                            'caminho_arquivo'        => realpath($filePath),
                            'descricao'              => mb_substr($content, 0, 4000),
                            'instituicao_custodiadora'=> 'Ingestão em Lote',
                        ], JSON_UNESCAPED_UNICODE),
                    ]);
                    CLI::write("  └─ Documento cadastrado (ID: {$documentId}).", 'light_gray');
                }

                // Invocar IA DeepSeek
                $result = $deepSeek->extractKnowledge($fileName, mb_substr($content, 0, 8000));
                $entities = $result['entities'] ?? [];
                $rels     = $result['relationships'] ?? [];

                // 1. Mapear e salvar Entidades
                $entityNameMap = [];
                foreach ($entities as $eData) {
                    $eName = trim($eData['name'] ?? '');
                    $eType = $eData['type'] ?? 'person';
                    if (empty($eName)) continue;

                    $existingE = $entityModel->where('name', $eName)->first();
                    if ($existingE) {
                        $entityNameMap[$eName] = $existingE['id'];
                        continue;
                    }

                    $model = $modelMap[$eType] ?? $modelMap['person'];
                    $eid = $model->insert([
                        'type'       => in_array($eType, ['person','location','event']) ? $eType : 'person',
                        'name'       => $eName,
                        'status'     => 'hypothesis',
                        'attributes' => json_encode($eData['attributes'] ?? [], JSON_UNESCAPED_UNICODE),
                        'created_by' => 1,
                    ]);
                    if ($eid) {
                        $entityNameMap[$eName] = $eid;
                    }
                }

                // 2. Mapear e salvar Relações
                $docHypothesisCount = 0;
                $docRelsCount       = 0;
                foreach ($rels as $rData) {
                    $srcName = trim($rData['source_name'] ?? '');
                    $tgtName = trim($rData['target_name'] ?? '');
                    $relType = trim($rData['relationship_type'] ?? 'associado_a');

                    $srcId = $entityNameMap[$srcName] ?? null;
                    $tgtId = $entityNameMap[$tgtName] ?? null;

                    if (!$srcId || !$tgtId || $srcId === $tgtId) continue;

                    $confidence = floatval($rData['confidence'] ?? 0.75);
                    $is100Pct   = $confidence >= 0.999;

                    $status = ($is100Pct && $autoConfirm100) ? 'confirmed' : 'hypothesis';

                    $relModel->insert([
                        'source_entity_id'  => $srcId,
                        'target_entity_id'  => $tgtId,
                        'relationship_type' => $relType,
                        'direction'         => ($rData['direction'] ?? 'directed') === 'symmetric' ? 'symmetric' : 'directed',
                        'confidence'        => round(max(0.5, min(1.0, $confidence)), 2),
                        'source_document_id'=> $documentId,
                        'source_reference'  => json_encode(['trecho' => $rData['excerpt'] ?? ''], JSON_UNESCAPED_UNICODE),
                        'status'            => $status,
                        'created_by'        => 1,
                        'validated_by'      => $status === 'confirmed' ? 1 : null,
                    ]);

                    $docRelsCount++;
                    if ($status === 'hypothesis') {
                        $docHypothesisCount++;
                    }
                }

                $extractedEntitiesTotal += count($entities);
                $extractedRelsTotal     += $docRelsCount;
                $hypothesesTotal        += $docHypothesisCount;
                $processedCount++;

                CLI::write("     ✓ Extraídas " . count($entities) . " entidades e {$docRelsCount} relações ({$docHypothesisCount} hipóteses gravadas).", 'white');

            } catch (\Exception $e) {
                CLI::write("     ❌ Erro ao ler/extrair '{$fileName}': " . $e->getMessage(), 'red');
            }

            usleep(250000);
        }

        CLI::write("\n═══════════════════════════════════════════════════════════", 'yellow');
        CLI::write("  RESUMO DA INGESTÃO EM LOTE", 'yellow');
        CLI::write("═══════════════════════════════════════════════════════════", 'yellow');
        CLI::write("Arquivos Processados: {$processedCount} de " . count($files));
        CLI::write("Entidades Identificadas: {$extractedEntitiesTotal}");
        CLI::write("Relações Criadas: {$extractedRelsTotal}");
        CLI::write("Hipóteses Pendentes de Revisão: {$hypothesesTotal}", 'light_yellow');
        CLI::write("\nConsulte e revise o grafo em: https://cerebro.test/grafo", 'cyan');
    }

    /**
     * Procura recursivamente por arquivos suportados no diretório.
     */
    private function findDocumentFiles(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $allowedExtensions = ['txt', 'md', 'json', 'csv', 'pdf', 'jpg', 'jpeg', 'png', 'webp', 'bmp'];

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $allowedExtensions)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);
        return $files;
    }
}

<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\EntityModel;
use App\Models\RelationshipModel;
use App\Services\DocumentExtractionService;

/**
 * ExtractionAcceptanceSeeder — Valida os 6 critérios de aceite da Spec 6
 * (Ingestão em 2 Estágios, Repositório de Texto Bruto, Sanitização e Extração Tokenizada).
 */
class ExtractionAcceptanceSeeder extends Seeder
{
    public function run()
    {
        echo "=== INICIANDO VALIDAÇÃO DOS CRITÉRIOS DE ACEITE DA SPEC 6 ===\n\n";

        $db                 = \Config\Database::connect();
        $entityModel        = new EntityModel();
        $relModel           = new RelationshipModel();
        $extractionService  = new DocumentExtractionService();

        $passed = 0;
        $total  = 6;

        // -----------------------------------------------------------------
        // Critério 1: Gravar Documento no Repositório com conteudo_transcrito e status 'pending'
        // -----------------------------------------------------------------
        $sampleText = "Edgard Leuenroth publicou o jornal A Plebe na cidade de São Paulo durante o ano de 1917.\n"
                    . "Militantes operários como Neno Vasco e Astrojildo Pereira participaram das reuniões do Brás.";

        $docId = $entityModel->insert([
            'name'         => 'Teste Ingestao Estagio 1 — Spec 6',
            'type'         => 'document',
            'status'       => 'confirmed',
            'created_by'   => 1,
            'validated_by' => 1,
            'attributes'   => json_encode([
                'titulo'              => 'Teste Ingestao Estagio 1',
                'conteudo_transcrito' => $sampleText,
                'extraction_status'   => 'pending',
                'formato'             => 'TXT',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $savedDoc = $entityModel->find($docId);
        $savedAttrs = is_string($savedDoc['attributes']) ? json_decode($savedDoc['attributes'], true) : $savedDoc['attributes'];

        if ($docId && ($savedAttrs['extraction_status'] ?? '') === 'pending' && ($savedAttrs['conteudo_transcrito'] ?? '') === $sampleText) {
            echo "[✓] Critério 1/6 PASSOU: Texto bruto completo salvo no repositório com status 'pending'.\n";
            $passed++;
        } else {
            echo "[X] Critério 1/6 FALHOU: Falha ao salvar texto bruto no repositório.\n";
        }

        // -----------------------------------------------------------------
        // Critério 2: Documento pendente é recuperado nas consultas da Fila
        // -----------------------------------------------------------------
        $pendingQuery = $db->query("SELECT * FROM entities WHERE type = 'document' AND attributes->>'extraction_status' = 'pending'")
            ->getResultArray();

        $foundInPending = false;
        foreach ($pendingQuery as $pDoc) {
            if ((int)$pDoc['id'] === $docId) {
                $foundInPending = true;
                break;
            }
        }

        if ($foundInPending) {
            echo "[✓] Critério 2/6 PASSOU: Documento pendente localizado na fila de extração.\n";
            $passed++;
        } else {
            echo "[X] Critério 2/6 FALHOU: Documento pendente não listado na fila.\n";
        }

        // -----------------------------------------------------------------
        // Critério 3: Edição manual do texto transcrito no repositório
        // -----------------------------------------------------------------
        $updatedText = $sampleText . "\nNovo trecho adicionado manualmente pelo pesquisador no modal de edição.";
        $savedAttrs['conteudo_transcrito'] = $updatedText;

        $entityModel->update($docId, [
            'attributes' => json_encode($savedAttrs, JSON_UNESCAPED_UNICODE)
        ]);

        $checkEditDoc = $entityModel->find($docId);
        $checkEditAttrs = is_string($checkEditDoc['attributes']) ? json_decode($checkEditDoc['attributes'], true) : $checkEditDoc['attributes'];

        if (($checkEditAttrs['conteudo_transcrito'] ?? '') === $updatedText) {
            echo "[✓] Critério 3/6 PASSOU: Transcrição editada com sucesso no repositório.\n";
            $passed++;
        } else {
            echo "[X] Critério 3/6 FALHOU: Falha ao atualizar texto editado no repositório.\n";
        }

        // -----------------------------------------------------------------
        // Critério 4: Sanitização de Caracteres de Controle em JSON
        // -----------------------------------------------------------------
        $dirtyText = "Texto com caractere nulo \x00 e camp \x07 e quebra \x0D em UTF-8.";
        $cleanText = $extractionService->sanitizeTextForJson($dirtyText);

        if (strpos($cleanText, "\x00") === false && strpos($cleanText, "\x07") === false) {
            echo "[✓] Critério 4/6 PASSOU: Sanitização de caracteres de controle ASCII concluída.\n";
            $passed++;
        } else {
            echo "[X] Critério 4/6 FALHOU: Caracteres de controle não removidos.\n";
        }

        // -----------------------------------------------------------------
        // Critério 5: Chunking/Tokenização inteligente por blocos
        // -----------------------------------------------------------------
        $longText = str_repeat("Este é um parágrafo longo de teste para validar o chunking da Spec 6.\n", 80);
        $chunks   = $extractionService->chunkText($longText, 1000);

        if (count($chunks) > 1 && strlen($chunks[0]) <= 1200) {
            echo "[✓] Critério 5/6 PASSOU: Chunking tokenizado dividiu texto longo em " . count($chunks) . " blocos.\n";
            $passed++;
        } else {
            echo "[X] Critério 5/6 FALHOU: Chunking tokenizado não dividiu o texto.\n";
        }

        // -----------------------------------------------------------------
        // Critério 6: Persistência de Entidades e Hipóteses vinculadas ao Documento
        // -----------------------------------------------------------------
        $db->table('entities')->where('type !=', 'document')->like('name', 'Teste', 'both')->delete();

        $mockEntities = [
            ['name' => 'Edgard Leuenroth Teste', 'type' => 'person'],
            ['name' => 'São Paulo Teste', 'type' => 'location'],
        ];
        $mockRels = [
            [
                'source_name' => 'Edgard Leuenroth Teste',
                'target_name' => 'São Paulo Teste',
                'relationship_type' => 'militou_em',
                'direction' => 'directed',
                'confidence' => 0.9,
                'excerpt' => 'Edgard Leuenroth militou em São Paulo no ano de 1917.'
            ]
        ];

        $persisted = $extractionService->persistGraphData($docId, 'Doc Teste Spec 6', $mockEntities, $mockRels, 1);

        if (count($persisted['nameToIdMap']) >= 2 && $persisted['newRels'] >= 1) {
            echo "[✓] Critério 6/6 PASSOU: Grafo populado com hipóteses vinculadas ao documento fonte.\n";
            $passed++;
        } else {
            echo "[X] Critério 6/6 FALHOU: Entidades e relações não foram gravadas corretamente.\n";
        }

        // Limpeza dos dados de teste
        $db->table('relationships')->where('source_document_id', $docId)->delete();
        $db->table('entities')->where('id', $docId)->delete();

        echo "\n=======================================================\n";
        echo "   RESULTADO FINAL SPEC 6: {$passed}/{$total} CRITÉRIOS APROVADOS\n";
        echo "=======================================================\n\n";

        if ($passed !== $total) {
            throw new \RuntimeException("FALHA NA VALIDAÇÃO DA SPEC 6 ({$passed}/{$total})");
        }
    }
}

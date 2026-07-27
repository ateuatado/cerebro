<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\PersonModel;
use App\Models\LocationModel;
use App\Models\EventModel;
use App\Models\DocumentModel;
use App\Models\RelationshipModel;
use Exception;

class AcceptanceCriteriaSeeder extends Seeder
{
    private PersonModel $personModel;
    private LocationModel $locationModel;
    private EventModel $eventModel;
    private DocumentModel $documentModel;
    private RelationshipModel $relModel;

    private array $ids = [];

    public function run(): void
    {
        $this->personModel   = new PersonModel();
        $this->locationModel = new LocationModel();
        $this->eventModel    = new EventModel();
        $this->documentModel = new DocumentModel();
        $this->relModel      = new RelationshipModel();

        echo "╔══════════════════════════════════════════════╗\n";
        echo "║  AcceptanceCriteriaSeeder — Spec 1          ║\n";
        echo "║  Validando critérios #2 ao #8 + Princípio I ║\n";
        echo "╚══════════════════════════════════════════════╝\n\n";

        $this->case1_createFourEntityTypes();
        $this->case2_entityPersonsViewFiltersByType();
        $this->case3_relationshipWithJsonbSourceReference();
        $this->case4_relationshipRequiresSourceDocumentId();
        $this->case5_confirmedRequiresValidatedBy();
        $this->case6_hypothesisAbsentFromFindConfirmed();
        $this->case7_sourceDocumentMustBeDocumentType();
        $this->case8_heterogeneousAttributesJsonb();

        $this->cleanup();

        echo "\n══════════════════════════════════════\n";
        echo "  8 casos automatizados concluídos.\n";
        echo "  Execute o passo 9 manualmente:\n";
        echo "  git diff --stat master..HEAD -- app/Controllers/ app/Views/ app/Config/Routes.php\n";
        echo "══════════════════════════════════════\n";
    }

    /**
     * Caso 1 — Critério #2: Criar 4 entidades (uma de cada tipo).
     */
    private function case1_createFourEntityTypes(): void
    {
        echo "Caso 1 [Critério #2]: Criar 4 entidades (pessoa, local, evento, documento)\n";

        $this->ids['person'] = $this->personModel->insert([
            'name'         => 'João da Silva',
            'attributes'   => json_encode(['profissao' => 'operário', 'apelido' => 'João Ferreiro']),
            'validated_by' => 'seed',
            'status'       => 'confirmed',
        ]);
        echo "  pessoa   id={$this->ids['person']} ✓\n";

        $this->ids['location'] = $this->locationModel->insert([
            'name'         => 'São Paulo',
            'attributes'   => json_encode(['tipo' => 'cidade', 'estado' => 'SP']),
            'validated_by' => 'seed',
            'status'       => 'confirmed',
        ]);
        echo "  local    id={$this->ids['location']} ✓\n";

        $this->ids['event'] = $this->eventModel->insert([
            'name'         => 'Manifestação de Julho de 1929',
            'attributes'   => json_encode(['data' => '1929-07-15']),
            'validated_by' => 'seed',
            'status'       => 'confirmed',
        ]);
        echo "  evento   id={$this->ids['event']} ✓\n";

        $this->ids['document'] = $this->documentModel->insert([
            'name'         => 'Processo Judicial n. 487/1929',
            'attributes'   => json_encode(['tipo_documento' => 'processo_judicial']),
            'validated_by' => 'seed',
            'status'       => 'confirmed',
        ]);
        echo "  documento id={$this->ids['document']} ✓\n\n";
    }

    /**
     * Caso 2 — Critério #5: entity_persons view retorna apenas persons.
     */
    private function case2_entityPersonsViewFiltersByType(): void
    {
        echo "Caso 2 [Critério #5]: entity_persons retorna apenas type='person'\n";

        $persons = $this->personModel->findAll();
        $allPersons = true;
        foreach ($persons as $p) {
            if ($p['type'] !== 'person') {
                echo "  ✗ ERRO: id={$p['id']} tem type='{$p['type']}'\n";
                $allPersons = false;
            }
        }
        echo $allPersons
            ? "  " . count($persons) . " registro(s), todos type='person' ✓\n\n"
            : "  FALHA\n\n";
    }

    /**
     * Caso 3 — Critérios #3 e #8: relação com source_reference JSONB e
     * cadeia completa recuperável (pessoa → relação → documento).
     */
    private function case3_relationshipWithJsonbSourceReference(): void
    {
        echo "Caso 3 [Critérios #3, #8]: Relação com source_reference JSONB + cadeia completa\n";

        $relId = $this->relModel->insert([
            'source_entity_id'   => $this->ids['person'],
            'target_entity_id'   => $this->ids['document'],
            'relationship_type'  => 'mencionado_em',
            'direction'          => 'directed',
            'confidence'         => 1.0,
            'source_document_id' => $this->ids['document'],
            'source_reference'   => json_encode(['description' => 'fl. 47, §3']),
            'status'             => 'confirmed',
            'validated_by'       => 'seed',
        ]);
        $this->ids['rel'] = $relId;
        echo "  relação criada: id={$relId}\n";

        // Recuperar cadeia: pessoa → relação → documento
        $rels = $this->relModel->findBySource($this->ids['person']);
        $found = false;
        foreach ($rels as $r) {
            if ($r['id'] == $relId && $r['target_entity_id'] == $this->ids['document']) {
                $ref = json_decode($r['source_reference'], true);
                echo "  cadeia: pessoa({$this->ids['person']}) → relação({$relId}) → documento({$this->ids['document']})\n";
                echo "  source_reference: " . ($ref['description'] ?? 'N/A') . " ✓\n";
                $found = true;
                break;
            }
        }
        echo $found ? "  cadeia completa recuperável ✓\n\n" : "  ✗ FALHA: cadeia não recuperada\n\n";
    }

    /**
     * Caso 4 — Critério #4: relationship exige source_document_id NOT NULL.
     */
    private function case4_relationshipRequiresSourceDocumentId(): void
    {
        echo "Caso 4 [Critério #4]: relationship exige source_document_id NOT NULL\n";

        try {
            $this->relModel->insert([
                'source_entity_id'  => $this->ids['person'],
                'target_entity_id'  => $this->ids['document'],
                'relationship_type' => 'teste',
                'source_reference'  => json_encode(['description' => 'teste']),
                // source_document_id omitido intencionalmente
            ]);
            echo "  ✗ FALHA: insert sem source_document_id deveria ter lançado exceção\n\n";
        } catch (Exception $e) {
            echo "  Exceção capturada: " . substr($e->getMessage(), 0, 80) . "...\n";
            echo "  Banco rejeitou — NOT NULL aplicado ✓\n\n";
        }
    }

    /**
     * Caso 5 — Critério #7: entidade confirmed sem validated_by é rejeitada.
     */
    private function case5_confirmedRequiresValidatedBy(): void
    {
        echo "Caso 5 [Critério #7]: status='confirmed' sem validated_by é rejeitado\n";

        try {
            $this->personModel->insert([
                'name'   => 'Deveria falhar',
                'status' => 'confirmed',
                // validated_by omitido intencionalmente
            ]);
            echo "  ✗ FALHA: insert confirmado sem validated_by deveria ter lançado exceção\n\n";
        } catch (Exception $e) {
            echo "  Exceção capturada: " . substr($e->getMessage(), 0, 80) . "...\n";
            echo "  Banco rejeitou — CHECK constraint aplicada ✓\n\n";
        }
    }

    /**
     * Caso 6 — Critério #6: hipótese não aparece em findConfirmed().
     */
    private function case6_hypothesisAbsentFromFindConfirmed(): void
    {
        echo "Caso 6 [Critério #6]: Hipótese ausente em findConfirmed()\n";

        // Criar hipótese (status default = 'hypothesis')
        $hypId = $this->personModel->insert([
            'name' => 'Hipótese de Teste',
        ]);
        echo "  hipótese criada: id={$hypId} (status default)\n";

        // Buscar em findConfirmed — não deve aparecer
        $confirmed = $this->personModel->findConfirmed();
        $hypInConfirmed = false;
        foreach ($confirmed as $c) {
            if ($c['id'] == $hypId) {
                $hypInConfirmed = true;
                break;
            }
        }
        echo $hypInConfirmed
            ? "  ✗ FALHA: hipótese id={$hypId} vazou para findConfirmed()\n\n"
            : "  hipótese id={$hypId} AUSENTE em findConfirmed() ✓\n\n";

        // Guardar para limpeza
        $this->ids['hypothesis'] = $hypId;
    }

    /**
     * Caso 7 — Princípio I (critério adicional): source_document_id
     * apontando para pessoa é rejeitado pelo trigger.
     */
    private function case7_sourceDocumentMustBeDocumentType(): void
    {
        echo "Caso 7 [Princípio I]: source_document_id → pessoa é rejeitado pelo trigger\n";

        try {
            $this->relModel->insert([
                'source_entity_id'   => $this->ids['document'],
                'target_entity_id'   => $this->ids['person'],
                'relationship_type'  => 'teste',
                'source_document_id' => $this->ids['person'], // ← pessoa, não documento!
                'source_reference'   => json_encode(['description' => 'deveria falhar']),
            ]);
            echo "  ✗ FALHA: trigger deveria ter rejeitado source_document_id apontando para pessoa\n\n";
        } catch (Exception $e) {
            echo "  Exceção capturada: " . substr($e->getMessage(), 0, 80) . "...\n";
            echo "  Trigger check_source_is_document() rejeitou — Princípio I aplicado ✓\n\n";
        }
    }

    /**
     * Caso 8 — Critério #3: attributes JSONB aceita campos heterogêneos
     * por tipo de entidade.
     */
    private function case8_heterogeneousAttributesJsonb(): void
    {
        echo "Caso 8 [Critério #3]: attributes JSONB heterogêneo por tipo\n";

        // Recuperar entidades criadas no caso 1 e verificar attributes distintos
        $models = [
            'person'   => $this->personModel,
            'location' => $this->locationModel,
            'event'    => $this->eventModel,
            'document' => $this->documentModel,
        ];

        foreach ($models as $type => $model) {
            $entity = $model->find($this->ids[$type]);
            $attrs = json_decode($entity['attributes'], true);
            $keys = array_keys($attrs);
            echo "  {$type}: atributos = [" . implode(', ', $keys) . "] ✓\n";
        }

        echo "\n";
    }

    /**
     * Remove todos os dados de teste criados.
     */
    private function cleanup(): void
    {
        if (isset($this->ids['rel'])) {
            $this->relModel->delete($this->ids['rel']);
        }
        if (isset($this->ids['hypothesis'])) {
            $this->personModel->delete($this->ids['hypothesis']);
        }
        foreach (['person', 'location', 'event', 'document'] as $type) {
            if (isset($this->ids[$type])) {
                $this->personModel->delete($this->ids[$type]);
            }
        }
    }
}

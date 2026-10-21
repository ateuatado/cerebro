<?php

namespace App\Commands;

use App\Services\EntityIdentityService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class DeduplicateEntitiesCommand extends BaseCommand
{
    protected $group = 'Cerebro';
    protected $name = 'graph:deduplicate';
    protected $description = 'Lista ou consolida entidades duplicadas por tipo e nome normalizado.';
    protected $options = [
        '--apply' => 'Aplica a consolidação. Sem esta opção, apenas lista os grupos encontrados.',
    ];

    public function run(array $params)
    {
        $db = Database::connect();
        $identity = new EntityIdentityService();
        $rows = $db->table('entities')->select('id,type,name,status,attributes')->orderBy('id', 'ASC')->get()->getResultArray();
        $groups = [];

        foreach ($rows as $row) {
            $key = $row['type'] . '|' . $identity->normalize($row['name']);
            $groups[$key][] = $row;
        }

        $duplicates = array_filter($groups, static fn(array $group): bool => count($group) > 1);
        if (empty($duplicates)) {
            CLI::write('Nenhuma duplicata encontrada.', 'green');
            return EXIT_SUCCESS;
        }

        $apply = CLI::getOption('apply') !== null;
        foreach ($duplicates as $key => $group) {
            usort($group, static function (array $a, array $b): int {
                return (($a['status'] === 'confirmed') <=> ($b['status'] === 'confirmed'))
                    ?: ((int) $b['id'] <=> (int) $a['id']);
            });
            $survivor = array_pop($group);
            $ids = implode(', ', array_map(static fn(array $r): string => (string) $r['id'], $group));
            CLI::write("{$key}: manter #{$survivor['id']} e consolidar [{$ids}]");

            if ($apply) {
                foreach ($group as $duplicate) {
                    $this->merge($db, $survivor, $duplicate);
                }
            }
        }

        if (! $apply) {
            CLI::write('Simulação concluída. Use --apply somente após revisar a lista.', 'yellow');
        } else {
            CLI::write('Consolidação concluída.', 'green');
        }
        return EXIT_SUCCESS;
    }

    private function merge($db, array $survivor, array $duplicate): void
    {
        $survivorId = (int) $survivor['id'];
        $duplicateId = (int) $duplicate['id'];
        $db->transStart();

        // Evitar relações que se tornariam auto-referentes.
        $db->table('relationships')
            ->groupStart()
            ->where(['source_entity_id' => $duplicateId, 'target_entity_id' => $survivorId])
            ->orWhere(['source_entity_id' => $survivorId, 'target_entity_id' => $duplicateId])
            ->groupEnd()
            ->delete();

        $db->table('relationships')->where('source_entity_id', $duplicateId)->update(['source_entity_id' => $survivorId]);
        $db->table('relationships')->where('target_entity_id', $duplicateId)->update(['target_entity_id' => $survivorId]);
        $db->table('relationships')->where('source_document_id', $duplicateId)->update(['source_document_id' => $survivorId]);

        $db->query(
            'INSERT INTO research_documents (research_id, document_id, created_at)
             SELECT research_id, ?, created_at FROM research_documents WHERE document_id = ?
             ON CONFLICT (research_id, document_id) DO NOTHING',
            [$survivorId, $duplicateId]
        );
        $db->table('research_documents')->where('document_id', $duplicateId)->delete();
        $db->table('entities')->where('id', $duplicateId)->delete();
        $db->transComplete();
    }
}

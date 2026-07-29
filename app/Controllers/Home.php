<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\RelationshipModel;
use App\Services\AuthService;

/**
 * Home — Dashboard principal
 */
class Home extends BaseController
{
    public function index(): string
    {
        $entityModel = new EntityModel();
        $relModel    = new RelationshipModel();

        // Contadores por tipo
        $allEntities = $entityModel->findAllRaw();

        $stats = [
            'persons'       => 0,
            'locations'     => 0,
            'events'        => 0,
            'documents'     => 0,
            'relationships' => count($relModel->findAll()),
            'pending'       => 0,
            'total'         => count($allEntities),
        ];

        foreach ($allEntities as $e) {
            match ($e['type']) {
                'person'   => $stats['persons']++,
                'location' => $stats['locations']++,
                'event'    => $stats['events']++,
                'document' => $stats['documents']++,
                default    => null,
            };
            if ($e['status'] === 'hypothesis') {
                $stats['pending']++;
            }
        }

        // Recentes
        $recentEntities = $entityModel
            ->orderBy('created_at', 'DESC')
            ->findAll(8);

        // Relações recentes (com nomes enriquecidos)
        $recentRels = $relModel
            ->orderBy('created_at', 'DESC')
            ->findAll(8);

        foreach ($recentRels as &$rel) {
            $src = $entityModel->find($rel['source_entity_id']);
            $tgt = $entityModel->find($rel['target_entity_id']);
            $rel['source_name'] = $src['name'] ?? '?';
            $rel['target_name'] = $tgt['name'] ?? '?';
        }
        unset($rel);

        // Dados do grafo para vis-network
        $graphEntities = array_map(fn($e) => [
            'id'     => $e['id'],
            'name'   => $e['name'],
            'type'   => $e['type'],
            'status' => $e['status'],
        ], $allEntities);

        $graphRels = array_map(fn($r) => [
            'id'               => $r['id'],
            'source_entity_id' => $r['source_entity_id'],
            'target_entity_id' => $r['target_entity_id'],
            'relationship_type'=> $r['relationship_type'],
            'direction'        => $r['direction'],
            'confidence'       => $r['confidence'],
            'status'           => $r['status'],
        ], $recentRels);

        return view('dashboard/index', [
            'stats'               => $stats,
            'recentEntities'      => $recentEntities,
            'recentRelationships' => $recentRels,
            'graphData'           => [
                'entities'      => $graphEntities,
                'relationships' => $graphRels,
            ],
        ]);
    }
}

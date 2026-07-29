<?php

namespace App\Controllers;

use App\Models\EntityModel;
use App\Models\RelationshipModel;

/**
 * GraphController — Página dedicada ao visualizador de grafo
 */
class GraphController extends BaseController
{
    public function index(): string
    {
        $entityModel = new EntityModel();
        $relModel    = new RelationshipModel();

        $allEntities = $entityModel->findAllRaw();
        $allRels     = $relModel->findAllRaw();

        // Mapear somente campos necessários para o vis-network
        $graphEntities = array_map(fn($e) => [
            'id'     => $e['id'],
            'name'   => $e['name'],
            'type'   => $e['type'],
            'status' => $e['status'],
        ], $allEntities);

        $graphRels = array_map(fn($r) => [
            'id'                => $r['id'],
            'source_entity_id'  => $r['source_entity_id'],
            'target_entity_id'  => $r['target_entity_id'],
            'relationship_type' => $r['relationship_type'],
            'direction'         => $r['direction'],
            'confidence'        => $r['confidence'],
            'status'            => $r['status'],
        ], $allRels);

        return view('graph/index', [
            'graphData' => [
                'entities'      => $graphEntities,
                'relationships' => $graphRels,
            ],
        ]);
    }
}

<?php

namespace App\Models;

class DocumentModel extends EntityModel
{
    protected $table = 'entity_documents';

    public function insert($data = null, bool $returnID = true)
    {
        if (is_array($data)) {
            $data['type'] = 'document';
        }
        return parent::insert($data, $returnID);
    }
}

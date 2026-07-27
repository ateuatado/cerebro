<?php

namespace App\Models;

class PersonModel extends EntityModel
{
    protected $table = 'entity_persons';

    public function insert($data = null, bool $returnID = true)
    {
        if (is_array($data)) {
            $data['type'] = 'person';
        }
        return parent::insert($data, $returnID);
    }
}

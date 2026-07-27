<?php

namespace App\Models;

class LocationModel extends EntityModel
{
    protected $table = 'entity_locations';

    public function insert($data = null, bool $returnID = true)
    {
        if (is_array($data)) {
            $data['type'] = 'location';
        }
        return parent::insert($data, $returnID);
    }
}

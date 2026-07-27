<?php

namespace App\Models;

class EventModel extends EntityModel
{
    protected $table = 'entity_events';

    public function insert($data = null, bool $returnID = true)
    {
        if (is_array($data)) {
            $data['type'] = 'event';
        }
        return parent::insert($data, $returnID);
    }
}

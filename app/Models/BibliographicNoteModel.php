<?php

namespace App\Models;

use CodeIgniter\Model;

class BibliographicNoteModel extends Model
{
    protected $table = 'bibliographic_notes';
    protected $allowedFields = ['work_id', 'locator', 'excerpt', 'observations'];
}

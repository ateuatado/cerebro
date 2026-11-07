<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentNoteModel extends Model
{
    protected $table = 'document_notes';
    protected $allowedFields = ['document_id', 'document_label', 'kind', 'locator', 'excerpt', 'observations'];

    public const KINDS = ['excerto' => 'Excerto', 'parafrase' => 'Paráfrase', 'comentario' => 'Comentário'];

    public function documents(): array
    {
        return (new EntityModel())->select('id, name')->where('type', 'document')->orderBy('name')->findAll();
    }
}

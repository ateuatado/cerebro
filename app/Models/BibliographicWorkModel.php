<?php

namespace App\Models;

use CodeIgniter\Model;

class BibliographicWorkModel extends Model
{
    protected $table = 'bibliographic_works';
    protected $allowedFields = ['title', 'kind', 'authors', 'publication_date', 'publication', 'identifier'];

    public const KINDS = [
        'book' => 'Livro', 'chapter' => 'Capítulo', 'article' => 'Artigo',
        'thesis' => 'Tese ou dissertação', 'other' => 'Outro',
    ];

    public function search(string $query)
    {
        if ($query !== '') {
            $this->groupStart()->like('title', $query, 'both', null, true)
                ->orLike('authors', $query, 'both', null, true)
                ->orLike('identifier', $query, 'both', null, true)->groupEnd();
        }

        return $this->orderBy('title')->orderBy('id')->paginate(30);
    }
}

<?php

namespace App\Models;

use CodeIgniter\Model;

class ResearchDiaryModel extends Model
{
    protected $table = 'research_diary';
    protected $allowedFields = ['activity_date', 'account'];
}

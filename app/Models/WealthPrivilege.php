<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WealthPrivilege extends Model
{
    protected $table = 'wealth_privileges';

    protected $fillable = [
        'title',
        'description',
        'icon_identifier',
        'level_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level_required' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

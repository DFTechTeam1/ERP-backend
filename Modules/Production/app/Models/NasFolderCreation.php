<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Production\Database\Factories\NasFolderCreationFactory;

class NasFolderCreation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'project_name',
        'folder_path',
        'project_id',
        'status',
        'type',
        'last_folder_name',
        'current_folder_name'
    ];
}

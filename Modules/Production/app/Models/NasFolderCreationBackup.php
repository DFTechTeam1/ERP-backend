<?php

namespace Modules\Production\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Production\Database\Factories\NasFolderCreationBackupFactory;

class NasFolderCreationBackup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'shared_folder',
        'year',
        'month_name',
        'project_name',
        'prefix_project_name',
        'child_folders',
        'project_id'
    ];

    // protected static function newFactory(): NasFolderCreationBackupFactory
    // {
    //     // return NasFolderCreationBackupFactory::new();
    // }

    public function childFolders(): Attribute
    {
        return Attribute::make(
            set: fn($value) => json_encode($value)
        );
    }
}

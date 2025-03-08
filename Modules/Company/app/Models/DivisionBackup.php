<?php

namespace Modules\Company\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Company\Database\Factories\DivisionBackupFactory;

class DivisionBackup extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'parent_id',
        'uid',
        'created_by',
        'updated_by',
    ];

    // protected static function newFactory(): DivisionBackupFactory
    // {
    //     // return DivisionBackupFactory::new();
    // }
}

<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Database\Factories\BranchFactory;
use Modules\Hrd\Models\Employee;

use function PHPSTORM_META\map;

// use Modules\Company\Database\Factories\BranchFactory;

class Branch extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return BranchFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'short_name'
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'branch_id');
    }

    // protected static function newFactory(): BranchFactory
    // {
    //     // return BranchFactory::new();
    // }
}

<?php

namespace Modules\Hrd\Models;

use App\Traits\ModelObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Models\DivisionBackup;

// use Modules\Hrd\Database\Factories\SignatoriesMappingFactory;

class SignatoriesMapping extends Model
{
    use HasFactory, ModelObserver;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'division_id',
        'main_signer_id',
        'delegate_signer_id',
        'updated_by'
    ];

    // protected static function newFactory(): SignatoriesMappingFactory
    // {
    //     // return SignatoriesMappingFactory::new();
    // }

    public function division(): BelongsTo
    {
        return $this->belongsTo(DivisionBackup::class, 'division_id');
    }

    public function mainSigner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'main_signer_id');
    }

    public function delegateSigner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'delegate_signer_id');
    }
}

<?php

namespace Modules\Company\Models;

use App\Enums\Company\ExportImportAreaType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Database\Factories\ExportImportResultFactory;

// use Modules\Company\Database\Factories\ExportImportResultFactory;

class ExportImportResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'area',
        'description',
        'message',
        'user_id',
        'type',
    ];

    protected function casts()
    {
        return [
            'type' => ExportImportAreaType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function newFactory(): ExportImportResultFactory
    {
        return ExportImportResultFactory::new();
    }
}

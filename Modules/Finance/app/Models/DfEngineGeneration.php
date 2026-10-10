<?php

namespace Modules\Finance\Models;

use App\Enums\Finance\AiGeneration\GenerationKind;
use App\Enums\Finance\AiGeneration\StatusGeneration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Finance\Database\Factories\DfEngineGenerationFactory;

class DfEngineGeneration extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uid',
        'kind',
        'model_id',
        'menu_id',
        'feature_id',
        'sourcable_id',
        'sourceble_type',
        'project_id',
        'task_id',
        'song_id',
        'prompt_text',
        'full_prompt',
        'parameter',
        'status',
        'response',
        'status_code',
        'token_usage',
        'cost',
        'created_by',
        'enhance_id',
        'x_min',
        'x_max',
        'y_min',
        'y_max',
        'exchange_rate'
    ];

    // protected static function newFactory(): DfEngineGenerationFactory
    // {
    //     // return DfEngineGenerationFactory::new();
    // }

    protected $casts = [
        'status' => StatusGeneration::class,
        'kind' => GenerationKind::class
    ];
}

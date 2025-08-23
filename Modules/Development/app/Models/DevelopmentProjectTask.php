<?php

namespace Modules\Development\Models;

use App\Enums\Development\Project\ProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Development\Database\Factories\DevelopmentProjectTaskFactory;

class DevelopmentProjectTask extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'development_project_id',
        'development_project_board_id',
        'name',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
        ];
    }

    // protected static function newFactory(): DevelopmentProjectTaskFactory
    // {
    //     // return DevelopmentProjectTaskFactory::new();
    // }

    public function board()
    {
        return $this->belongsTo(DevelopmentProjectBoard::class, 'development_project_board_id');
    }
}

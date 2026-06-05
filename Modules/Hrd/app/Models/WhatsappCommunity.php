<?php

namespace Modules\Hrd\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Hrd\Database\Factories\WhatsappCommunityFactory;

class WhatsappCommunity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'subject',
        'community_id',
        'description',
    ];

    // protected static function newFactory(): WhatsappCommunityFactory
    // {
    //     // return WhatsappCommunityFactory::new();
    // }

    public function groups(): HasMany
    {
        return $this->hasMany(WhatsappGroup::class, 'community_id', 'community_id');
    }
}

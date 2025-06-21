<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Company\Database\factories\DocumentTemplateFactory;

class DocumentTemplate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [];

    protected static function newFactory(): DocumentTemplateFactory
    {
        // return DocumentTemplateFactory::new();
    }
}

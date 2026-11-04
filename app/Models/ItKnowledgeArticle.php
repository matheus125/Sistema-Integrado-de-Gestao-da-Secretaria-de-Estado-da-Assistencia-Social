<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItKnowledgeArticle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['author_id', 'category_id', 'title', 'summary', 'content', 'tags', 'active', 'public', 'views_count', 'published_at'];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'active' => 'boolean',
            'public' => 'boolean',
            'views_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItTicketCategory::class, 'category_id');
    }
}

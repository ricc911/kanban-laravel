<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = [
        'board_id',
        'board_column_id',
        'category_id',
        'title',
        'description',
        'position',
        'priority',
        'due_at',
        'archived',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'due_at' => 'datetime',
            'archived' => 'boolean',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

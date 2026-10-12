<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BoardColumn extends Model {
    protected $table = 'board_columns';
    protected $primaryKey = 'column_id';
    public $timestamps = false;

    protected $fillable = [
        'board_id',
        'ordinal_position',
        'title',
        'color',
        'wip_limit',
    ];

    protected $casts = [
        'column_id'        => 'int',
        'board_id'         => 'int',
        'ordinal_position' => 'int',
        'wip_limit'        => 'int',
    ];

    public function board(): BelongsTo {
        return $this->belongsTo(Board::class, 'board_id', 'board_id');
    }

    public function cards(): HasMany {
        return $this->hasMany(Card::class, 'column_id', 'column_id')
            ->orderBy('sort_index');
    }
}
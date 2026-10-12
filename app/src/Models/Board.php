<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Board extends Model {
    protected $table = 'boards';
    protected $primaryKey = 'board_id';
    public $timestamps = false;

    protected $fillable = [
        'title',
        'description',
        'owner_user_id',
        'is_public',
        'is_archived',
        'color',
    ];

    protected $casts = [
        'board_id'         => 'int',
        'owner_user_id'    => 'int',
        'is_public'        => 'bool',
        'is_archived'      => 'bool',
        'created_datetime' => 'datetime',
        'updated_datetime' => 'datetime',
    ];

    public function owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_user_id', 'user_id');
    }

    public function columns(): HasMany {
        return $this->hasMany(BoardColumn::class, 'board_id', 'board_id')
            ->orderBy('ordinal_position');
    }

    public function cards(): HasMany {
        return $this->hasMany(Card::class, 'board_id', 'board_id')
            ->orderBy('sort_index');
    }
}
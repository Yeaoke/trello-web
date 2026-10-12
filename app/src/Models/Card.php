<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Card extends Model {
    protected $table = 'cards';
    protected $primaryKey = 'card_id';
    public $timestamps = false;

    protected $fillable = [
        'board_id',
        'column_id',
        'author_user_id',
        'assignee_user_id',
        'sort_index',
        'title',
        'content',
        'color',
        'priority',
        'is_archived',
        'is_completed',
        'due_datetime',
    ];

    protected $casts = [
        'card_id'          => 'int',
        'board_id'         => 'int',
        'column_id'        => 'int',
        'author_user_id'   => 'int',
        'assignee_user_id' => 'int',
        'sort_index'       => 'int',
        'priority'         => 'int',
        'is_archived'      => 'bool',
        'is_completed'     => 'bool',
        'due_datetime'     => 'datetime',
        'created_datetime' => 'datetime',
        'updated_datetime' => 'datetime',
    ];

    public function board(): BelongsTo {
        return $this->belongsTo(Board::class, 'board_id', 'board_id');
    }

    public function column(): BelongsTo {
        return $this->belongsTo(BoardColumn::class, 'column_id', 'column_id');
    }

    public function author(): BelongsTo {
        return $this->belongsTo(User::class, 'author_user_id', 'user_id');
    }

    public function assignee(): BelongsTo {
        return $this->belongsTo(User::class, 'assignee_user_id', 'user_id');
    }
}
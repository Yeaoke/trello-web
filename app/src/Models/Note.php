<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Note extends Model {
    protected $table = 'notes';
    protected $primaryKey = 'note_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'color',
        'is_pinned',
        'is_archived',
    ];

    protected $casts = [
        'note_id'          => 'int',
        'user_id'          => 'int',
        'is_pinned'        => 'bool',
        'is_archived'      => 'bool',
        'created_datetime' => 'datetime',
        'updated_datetime' => 'datetime',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['user_id', 'task_day_id', 'title', 'description', 'is_done'];

    protected $casts = ['is_done' => 'boolean'];

    public function taskDay(): BelongsTo
    {
        return $this->belongsTo(TaskDay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
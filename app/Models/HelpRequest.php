<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class HelpRequest extends Model
{
    protected $fillable = [
        'user_id',
        'subject',
        'message',
        'status',
        'priority',
        'category',
        'admin_notes',
        'resolved_at',
    ];

    protected $casts = [
        'status' => 'string',
        'priority' => 'string',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => match ($value) {
                'new' => 'Новая',
                'in_progress' => 'В работе',
                'resolved' => 'Решена',
                'closed' => 'Закрыта',
                default => $value,
            }
        );
    }

    protected function priorityLabel(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => match ($value) {
                'low' => 'Низкий',
                'normal' => 'Обычный',
                'high' => 'Высокий',
                'urgent' => 'Срочный',
                default => $value,
            }
        );
    }

    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => match ($value) {
                'new' => 'info',
                'in_progress' => 'warning',
                'resolved' => 'success',
                'closed' => 'gray',
                default => 'gray',
            }
        );
    }

    public function markAsResolved(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    public function markAsClosed(): void
    {
        $this->update(['status' => 'closed']);
    }
}

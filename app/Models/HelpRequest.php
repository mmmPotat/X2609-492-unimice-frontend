<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    /**
     * Человекочитаемое название статуса.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'new' => 'Новая',
                'in_progress' => 'В работе',
                'resolved' => 'Решена',
                'closed' => 'Закрыта',
                default => $this->status,
            }
        );
    }

    /**
     * Человекочитаемое название приоритета.
     */
    protected function priorityLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->priority) {
                'low' => 'Низкий',
                'normal' => 'Обычный',
                'high' => 'Высокий',
                'urgent' => 'Срочный',
                default => $this->priority,
            }
        );
    }

    /**
     * Цвет статуса.
     */
    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->status) {
                'new' => 'info',
                'in_progress' => 'warning',
                'resolved' => 'success',
                'closed' => 'gray',
                default => 'gray',
            }
        );
    }

    /**
     * Отметить обращение как решённое.
     */
    public function markAsResolved(): void
    {
        $this->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);
    }

    /**
     * Закрыть обращение.
     */
    public function markAsClosed(): void
    {
        $this->update([
            'status' => 'closed',
        ]);
    }
}

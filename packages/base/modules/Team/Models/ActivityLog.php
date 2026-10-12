<?php

namespace App\Modules\Team\Models;

use App\Modules\Team\Enums\ActivityEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'subject_type',
        'subject_id',
        'event',
        'description',
        'properties',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function eventLabel(): string
    {
        return ActivityEvent::tryFrom($this->event)?->label() ?? $this->event;
    }
}

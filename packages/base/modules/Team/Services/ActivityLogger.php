<?php

namespace App\Modules\Team\Services;

use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tessera\Core\Contracts\Tenancy;

class ActivityLogger
{
    public function __construct(private readonly TeamFeatures $features, private readonly Tenancy $tenancy) {}

    /**
     * Record something an employee did. The tenant comes from the actor, or from the subject. Nothing is recorded
     * when the activity log is off.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        ActivityEvent $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): ?ActivityLog {
        if (! $this->features->activityLog()) {
            return null;
        }

        $actor ??= auth()->user();

        return ActivityLog::create([
            ...$this->tenancy->attributes($actor?->tenant_id ?? $subject?->tenant_id ?? null),
            'user_id' => $actor?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'event' => $event->value,
            'description' => mb_substr($description, 0, 500),
            'properties' => $properties === [] ? null : $properties,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}

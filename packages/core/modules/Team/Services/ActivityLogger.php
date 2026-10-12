<?php

namespace App\Modules\Team\Services;

use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Record something an employee did. The company comes from the actor, or from the subject.
     *
     * @param  array<string, mixed>  $properties
     */
    public function record(
        ActivityEvent $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= auth()->user();

        return ActivityLog::create([
            'company_id' => $actor?->company_id ?? $subject?->company_id ?? null,
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

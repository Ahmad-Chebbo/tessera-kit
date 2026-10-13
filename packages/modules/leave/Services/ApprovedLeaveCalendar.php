<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Tessera\Core\Contracts\LeaveCalendar;

class ApprovedLeaveCalendar implements LeaveCalendar
{
    public function __construct(private readonly LeaveDays $days) {}

    public function available(): bool
    {
        return true;
    }

    public function daysOff(int $tenantId, string $from, string $to, ?array $userIds = null): array
    {
        $away = [];

        $requests = LeaveRequest::query()
            ->with('type:id,name,is_paid')
            ->where('tenant_id', $tenantId)
            ->where('status', LeaveStatus::Approved->value)
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->when($userIds !== null, fn ($query) => $query->whereIn('user_id', $userIds))
            ->get();

        foreach ($requests as $request) {
            $start = CarbonImmutable::parse(max($request->starts_on->toDateString(), $from));
            $end = CarbonImmutable::parse(min($request->ends_on->toDateString(), $to));

            foreach ($this->days->between($tenantId, $start, $end) as $day) {
                $away["{$request->user_id}|{$day}"] = ['label' => (string) $request->type?->name, 'paid' => (bool) $request->type?->is_paid];
            }
        }

        return $away;
    }
}

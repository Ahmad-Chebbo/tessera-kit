<?php

namespace App\Modules\Team\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Http\Requests\ActivityLogRequest;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Tessera\Core\Contracts\BranchScope;
use Tessera\Core\Contracts\Tenancy;

class ActivityLogController extends Controller
{
    public function __construct(private BranchScope $access, private Tenancy $tenancy) {}

    public function index(ActivityLogRequest $request): Response
    {
        $actor = $request->user();

        $logs = $this->tenancy->scope(ActivityLog::query(), $actor->tenant_id)
            ->with('user:id,name')
            ->tap(fn ($query) => $this->access->scopeThroughUser($query, $actor, $request->query('branch_id')))
            ->when($request->integer('user_id'), fn ($query, $id) => $query->where('user_id', $id))
            ->when($request->query('event'), fn ($query, $event) => $query->where('event', (string) $event))
            ->when($request->from(), fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($request->to(), fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->when($request->query('search'), fn ($query, $search) => $query->where('description', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $search).'%'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'event_label' => $log->eventLabel(),
                'description' => $log->description,
                'user_id' => $log->user_id,
                'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
                'ip' => $log->ip,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Activity/Index', [
            'logs' => $logs,
            'employees' => $this->access->scope($this->tenancy->scope(User::query(), $actor->tenant_id), $actor, $request->query('branch_id'))->orderBy('name')->get(['id', 'name']),
            'events' => collect(ActivityEvent::cases())->map(fn (ActivityEvent $event): array => ['value' => $event->value, 'label' => $event->label()]),
            'filters' => [...$request->only(['user_id', 'event', 'date_from', 'date_to', 'search']), 'branch_id' => $this->access->canSeeAll($actor) ? $this->access->effective($actor, $request->query('branch_id')) : null],
        ]);
    }
}

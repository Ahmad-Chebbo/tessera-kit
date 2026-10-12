<?php

namespace App\Modules\Team;

use App\Modules\Team\Console\ModulesSyncCommand;
use App\Modules\Team\Services\TeamFeatures;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Users, roles and permissions, plus employee profiles and the activity log. Works on its own; the Company and Subscription modules add branches and plan limits.
 */
class TeamServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'team',
            name: 'Team',
            requires: [],
            core: true,
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('activity', 'Activity log', ['view activity log'], order: 100),
            new PlanFeature('roles', 'Custom roles', ['manage roles'], order: 110),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'team' => ['view users', 'create users', 'edit users', 'delete users', 'manage roles', 'view activity log'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view users', 'create users', 'edit users', 'manage roles', 'view activity log'],
            'Manager' => ['view activity log'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('team', 'users.title', 'users.index', 'users', 'view users', ['users.*'], 10),
            new MenuItem('team', 'roles.nav', 'roles.index', 'shield', 'manage roles', ['roles.*'], 20),
            ...($this->app->make(TeamFeatures::class)->activityLog()
                ? [new MenuItem('team', 'employees.activity', 'activity.index', 'activity', 'view activity log', ['activity.*'], 60)]
                : []),
        ];
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([ModulesSyncCommand::class]);
        }
    }
}

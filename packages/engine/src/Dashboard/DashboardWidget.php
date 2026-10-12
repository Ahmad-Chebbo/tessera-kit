<?php

namespace Tessera\Engine\Dashboard;

use App\Modules\Team\Models\User;

/**
 * A block a module adds to the dashboard. The module also ships the Vue component named by component() in its
 * Resources/js/Widgets folder; it receives the array from data() as its "data" prop.
 */
interface DashboardWidget
{
    public function key(): string;

    public function component(): string;

    /**
     * Position among the widgets, lowest first.
     */
    public function order(): int;

    /**
     * The permission needed to see the widget, or null when every dashboard viewer may.
     */
    public function permission(): ?string;

    /**
     * @return array<int|string, mixed>
     */
    public function data(User $user, ?int $branchId): array;
}

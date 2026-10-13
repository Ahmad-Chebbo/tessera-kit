<?php

namespace Tessera\Engine;

/**
 * The sections of the sidebar, in the order they appear. "main" items stand alone above the sections.
 */
final class MenuGroups
{
    /**
     * @var array<string, string|null> Group key => section title shown in the sidebar.
     */
    public const TITLES = [
        'main' => null,
        'admin' => 'ADMIN',
        'catalog' => 'CATALOG',
        'inventory' => 'INVENTORY',
        'sales' => 'SALES',
        'finance' => 'FINANCE',
        'reports' => 'REPORTS',
        'team' => 'TEAM',
        'settings' => 'SETTINGS',
    ];

    /**
     * Role editor groups in display order; groups a module adds that are not listed here follow them.
     *
     * @var list<string>
     */
    public const PERMISSION_ORDER = ['dashboard', 'pos', 'catalog', 'sales', 'inventory', 'expenses', 'reports', 'team', 'organization', 'shifts', 'attendance', 'settings'];
}

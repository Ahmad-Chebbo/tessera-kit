<?php

namespace Tessera\Engine;

use Illuminate\Http\Request;

/**
 * Builds the sidebar for one user from the menu entries the enabled modules declare.
 */
class Navigation
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * Standalone entries first, then one section per group with the entries the user may open.
     *
     * @param  list<string>  $permissions  The permissions the user holds and whose plan feature is on.
     * @return list<array<string, mixed>>
     */
    public function build(Request $request, array $permissions): array
    {
        $held = array_flip($permissions);
        $entries = [];
        $sections = [];

        foreach ($this->registry->menuItems() as $item) {
            if ($item->permission !== null && ! isset($held[$item->permission])) {
                continue;
            }

            $entry = [
                'label' => $item->label,
                'href' => route($item->route, absolute: false),
                'icon' => $item->icon,
                'active' => $item->active !== [] && $request->routeIs(...$item->active),
            ];

            if ($item->group === 'main') {
                $entries[] = $entry;

                continue;
            }

            $sections[$item->group][] = $entry;
        }

        foreach ($sections as $group => $items) {
            $entries[] = ['section' => MenuGroups::TITLES[$group] ?? strtoupper($group), 'items' => $items];
        }

        return $entries;
    }
}

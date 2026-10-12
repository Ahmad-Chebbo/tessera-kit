<?php

namespace Tessera\Engine;

/**
 * An entry a module adds to the sidebar. The label is a translation key.
 */
final readonly class MenuItem
{
    /**
     * @param  list<string>  $active  Route name patterns that mark the item as the current one.
     */
    public function __construct(
        public string $group,
        public string $label,
        public string $route,
        public string $icon,
        public ?string $permission = null,
        public array $active = [],
        public int $order = 100,
    ) {}
}

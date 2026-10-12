<?php

namespace Tessera\Core\Database;

/**
 * Polymorphic columns store class names. When a model moves to another namespace, the rows written before the move are
 * rewritten here. A database made after the move has nothing to rewrite.
 */
final class StoredClassNames
{
    /**
     * Every column a Tessera project stores a class name in, by table.
     *
     * @var array<string, list<string>>
     */
    public const COLUMNS = [
        'model_has_roles' => ['model_type'],
        'model_has_permissions' => ['model_type'],
        'media' => ['model_type'],
        'activity_logs' => ['subject_type'],
        'stock_movements' => ['reference_type'],
        'personal_access_tokens' => ['tokenable_type'],
        'notifications' => ['notifiable_type'],
    ];

    /**
     * Rewrites the stored class names that start with $from, so one call also moves related classes such as Order and
     * OrderItem.
     */
    public static function rewrite(string $from, string $to): void
    {
        LegacyTenantRename::classNames($from, $to, self::COLUMNS);
    }
}

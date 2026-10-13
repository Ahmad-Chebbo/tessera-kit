<?php

namespace App\Modules\Leave\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    /**
     * The statuses that hold dates and days: a pending request counts against the allowance until it is decided.
     *
     * @return list<self>
     */
    public static function holding(): array
    {
        return [self::Pending, self::Approved];
    }
}

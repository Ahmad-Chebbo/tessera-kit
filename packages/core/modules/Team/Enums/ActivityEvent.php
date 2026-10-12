<?php

namespace App\Modules\Team\Enums;

enum ActivityEvent: string
{
    case Login = 'auth.login';
    case Logout = 'auth.logout';
    case OrderCreated = 'order.created';
    case OrderVoided = 'order.voided';
    case EmployeeCreated = 'employee.created';
    case EmployeeUpdated = 'employee.updated';
    case EmployeeDeleted = 'employee.deleted';
    case PosShiftOpened = 'shift.opened';
    case PosShiftClosed = 'shift.closed';
    case ShiftAssigned = 'shift.assigned';
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case CheckedIn = 'attendance.check_in';
    case CheckedOut = 'attendance.check_out';
    case AttendanceAdjusted = 'attendance.adjusted';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Signed in',
            self::Logout => 'Signed out',
            self::OrderCreated => 'Sale',
            self::OrderVoided => 'Order voided',
            self::EmployeeCreated => 'Employee added',
            self::EmployeeUpdated => 'Employee updated',
            self::EmployeeDeleted => 'Employee removed',
            self::PosShiftOpened => 'Shift opened',
            self::PosShiftClosed => 'Shift closed',
            self::ShiftAssigned => 'Shift assigned',
            self::RoleCreated => 'Role created',
            self::RoleUpdated => 'Role updated',
            self::RoleDeleted => 'Role deleted',
            self::CheckedIn => 'Checked in',
            self::CheckedOut => 'Checked out',
            self::AttendanceAdjusted => 'Attendance adjusted',
        };
    }
}

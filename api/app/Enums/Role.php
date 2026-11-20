<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Accountant = 'accountant';
    case Agent = 'agent';
    case Viewer = 'viewer';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Roles an owner is allowed to hand out. Ownership itself is transferred, not assigned. */
    public static function assignable(): array
    {
        return [
            self::Manager->value,
            self::Accountant->value,
            self::Agent->value,
            self::Viewer->value,
        ];
    }

    /**
     * Reading is open to every signed-in member; these abilities gate writes.
     * Owner is handled separately and holds everything.
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Owner => ['*'],

            self::Manager => [
                'properties.manage',
                'tenants.manage',
                'leases.manage',
                'invoices.manage',
                'payments.record',
                'payments.reverse',
                'maintenance.manage',
                'reports.view',
                'activity.view',
            ],

            // Money, and only money. An accountant records and corrects
            // payments and reads every report, but does not let units or
            // sign up tenants.
            self::Accountant => [
                'invoices.manage',
                'payments.record',
                'payments.reverse',
                'reports.view',
                'activity.view',
            ],

            // Front-line staff: can let units and take money, but cannot
            // unwind a payment or delete anything.
            self::Agent => [
                'tenants.manage',
                'leases.manage',
                'invoices.manage',
                'payments.record',
                'maintenance.manage',
                'reports.view',
            ],

            self::Viewer => [
                'reports.view',
            ],
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

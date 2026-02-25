<?php

namespace App\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case SUPPLY_CHAIN = 'supply-chain';
    case USER = 'user';
    case LINE_MANAGER = 'line-manager';
    case HOD = 'hod';
    case ACCOUNT_USER = 'account-user';
    case SALES_HEAD = 'sales-head';
    case PRICE_UPLOADS = 'price-uploads';
    case CMD_KHI = 'cmd-khi';
    case CMD_LHR = 'cmd-lhr';
    case SCM_LHR = 'scm-lhr';

    /**
     * Get the description for the role.
     *
     * @return string
     */
    public function description(): string
    {
        return match ($this) {
            self::ADMIN => 'The user has administrative privileges.',
            self::SUPPLY_CHAIN => 'The user is part of the supply chain team.',
            self::USER => 'The user is a regular user.',
            self::ACCOUNT_USER => 'The user is part of the accounting team.',
            self::SALES_HEAD => 'The user manages CRM and Orders.',
            self::PRICE_UPLOADS => 'The user manages price lists and uploads.',
            self::CMD_KHI => 'The user has access to CMD-KHI receipts and dashboard.',
            self::CMD_LHR => 'The user has access to CMD-LHR receipts and dashboard.',
            self::SCM_LHR => 'The user has access to Lahore warehouse orders.',
            default => 'Regular user role.',
        };
    }

    /**
     * Determine if the role has administrative privileges.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Determine if the role has supply chain privileges.
     *
     * @return bool
     */
    public function isSupplyChain(): bool
    {
        return $this === self::SUPPLY_CHAIN;
    }

    /**
     * Determine if the role is account user.
     *
     * @return bool
     */
    public function isAccountUser(): bool
    {
        return $this === self::ACCOUNT_USER;
    }

    /**
     * Get the permissions for the role.
     *
     * @return array
     */
    public function permissions(): array
    {
        return match ($this) {
            self::ADMIN => ['create', 'read', 'update', 'delete', 'manage-users'],
            self::SUPPLY_CHAIN => ['read', 'update', 'manage-inventory'],
            self::USER => ['read'],
            self::ACCOUNT_USER => ['read', 'update', 'manage-accounts'],
            self::SALES_HEAD => ['read', 'update', 'manage-crm', 'manage-orders'],
            self::PRICE_UPLOADS => ['read', 'update', 'manage-price-lists'],
            self::CMD_KHI => ['read', 'manage-receipts', 'dashboard'],
            self::CMD_LHR => ['read', 'manage-receipts', 'dashboard'],
            self::SCM_LHR => ['read', 'update', 'manage-inventory', 'manage-orders'],
            default => ['read'],
        };
    }

    /**
     * Get the name of the role.
     *
     * @return string
     */
    public function name(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin',
            self::SUPPLY_CHAIN => 'Supply Chain',
            self::USER => 'User',
            self::ACCOUNT_USER => 'Account User',
            self::SALES_HEAD => 'Sales Head',
            self::PRICE_UPLOADS => 'Price Uploads',
            self::CMD_KHI => 'CMD-KHI',
            self::CMD_LHR => 'CMD-LHR',
            self::SCM_LHR => 'SCM-LHR',
            default => 'User',
        };
    }

    /**
     * Get all roles as an array of objects.
     *
     * @return array
     */
    public static function getValues(): array
    {
        return [
            (object)['id' => self::ADMIN->value, 'name' => self::ADMIN->name()],
            (object)['id' => self::SUPPLY_CHAIN->value, 'name' => self::SUPPLY_CHAIN->name()],
            (object)['id' => self::USER->value, 'name' => self::USER->name()],
            (object)['id' => self::ACCOUNT_USER->value, 'name' => self::ACCOUNT_USER->name()],
            (object)['id' => self::SALES_HEAD->value, 'name' => self::SALES_HEAD->name()],
            (object)['id' => self::PRICE_UPLOADS->value, 'name' => self::PRICE_UPLOADS->name()],
            (object)['id' => self::CMD_KHI->value, 'name' => self::CMD_KHI->name()],
            (object)['id' => self::CMD_LHR->value, 'name' => self::CMD_LHR->name()],
            (object)['id' => self::SCM_LHR->value, 'name' => self::SCM_LHR->name()],
        ];
    }

    /**
     * Get all roles as an associative array.
     *
     * @return array<string, string>
     */
    public static function asArray(): array
    {
        return [
            self::ADMIN->value => self::ADMIN->name(),
            self::SUPPLY_CHAIN->value => self::SUPPLY_CHAIN->name(),
            self::USER->value => self::USER->name(),
            self::ACCOUNT_USER->value => self::ACCOUNT_USER->name(),
            self::SALES_HEAD->value => self::SALES_HEAD->name(),
            self::PRICE_UPLOADS->value => self::PRICE_UPLOADS->name(),
            self::CMD_KHI->value => self::CMD_KHI->name(),
            self::CMD_LHR->value => self::CMD_LHR->name(),
            self::SCM_LHR->value => self::SCM_LHR->name(),
        ];
    }

    /**
     * Get all role keys.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::getValues(), 'id');
    }

    /**
     * Get all role names.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::getValues(), 'name');
    }
}

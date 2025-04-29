<?php

namespace App\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case SUPPLY_CHAIN = 'supply-chain';
    case USER = 'user';
    case LINE_MANAGER = 'line-manager';
    case HOD = 'hod';

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

<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::User => __('User'),
            self::Admin => __('Administrator'),
            self::SuperAdmin => __('Super Administrator'),
        };
    }

    public function atLeast(UserRole $role): bool
    {
        $order = [self::User->value => 1, self::Admin->value => 2, self::SuperAdmin->value => 3];

        return $order[$this->value] >= $order[$role->value];
    }
}

<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Artisan = 'artisan';
    case Customer = 'customer';
}

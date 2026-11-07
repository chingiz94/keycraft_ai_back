<?php

namespace App\Enums;

enum UserRole: string
{
    case Buyer = 'buyer';
    case Admin = 'admin';
}

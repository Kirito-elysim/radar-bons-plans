<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationChannel: string
{
    case Push = 'push';
    case Email = 'email';
    case Telegram = 'telegram';
}

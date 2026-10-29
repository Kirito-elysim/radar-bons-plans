<?php

declare(strict_types=1);

namespace App\Notification;

/**
 * Le canal n'a pas pu remettre la notification (non configuré, refus du service…).
 */
final class EnvoiImpossibleException extends \RuntimeException
{
}

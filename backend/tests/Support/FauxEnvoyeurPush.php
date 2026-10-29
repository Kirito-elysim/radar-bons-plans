<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\PushSubscription;
use App\Notification\Canal\EnvoyeurPushInterface;

/**
 * Envoyeur Web Push de test : enregistre les envois au lieu de contacter les services push.
 */
final class FauxEnvoyeurPush implements EnvoyeurPushInterface
{
    /** @var list<array{endpoint: string, charge: array<string, mixed>}> */
    public static array $envois = [];
    /** @var list<string> endpoints considérés comme expirés (410) */
    public static array $expires = [];

    public static function reinitialiser(): void
    {
        self::$envois = [];
        self::$expires = [];
    }

    public function estConfigure(): bool
    {
        return true;
    }

    public function envoyer(PushSubscription $abonnement, array $charge): bool
    {
        if (\in_array($abonnement->getEndpoint(), self::$expires, true)) {
            return false;
        }
        self::$envois[] = ['endpoint' => $abonnement->getEndpoint(), 'charge' => $charge];

        return true;
    }
}

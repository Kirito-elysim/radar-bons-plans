<?php

declare(strict_types=1);

namespace App\Notification\Canal;

use App\Entity\PushSubscription;

/**
 * Envoi bas niveau d'une notification Web Push (VAPID), isolé pour les tests.
 */
interface EnvoyeurPushInterface
{
    public function estConfigure(): bool;

    /**
     * @param array<string, mixed> $charge
     *
     * @return bool faux si l'abonnement n'existe plus (404/410) et doit être supprimé
     *
     * @throws \RuntimeException en cas d'échec temporaire
     */
    public function envoyer(PushSubscription $abonnement, array $charge): bool;
}

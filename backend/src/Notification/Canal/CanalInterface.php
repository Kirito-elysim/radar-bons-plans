<?php

declare(strict_types=1);

namespace App\Notification\Canal;

use App\Enum\NotificationChannel;
use App\Notification\Destinataire;
use App\Notification\EnvoiImpossibleException;
use App\Notification\MessageAlerte;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(self::TAG)]
interface CanalInterface
{
    public const TAG = 'app.canal_notification';

    public function canal(): NotificationChannel;

    /**
     * Le canal est-il utilisable pour ce destinataire (configuration serveur + coordonnées) ?
     */
    public function disponiblePour(Destinataire $destinataire): bool;

    /**
     * @throws EnvoiImpossibleException
     */
    public function envoyer(Destinataire $destinataire, MessageAlerte $message): void;
}

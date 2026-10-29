<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\TestNotificationProcessor;

/**
 * Envoie une notification de test sur chaque canal et renvoie le résultat.
 */
#[ApiResource(
    shortName: 'TestNotification',
    operations: [new Post(uriTemplate: '/notifications/test', status: 200, processor: TestNotificationProcessor::class, deserialize: false)],
)]
final class TestNotification
{
    /** @var array<string, string> canal → « envoyé », « non configuré » ou message d'erreur */
    public array $resultats = [];
}

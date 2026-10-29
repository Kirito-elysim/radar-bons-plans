<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\PushConfigurationProvider;

/**
 * Clé publique VAPID nécessaire au navigateur pour s'abonner aux notifications push.
 */
#[ApiResource(shortName: 'PushConfiguration', operations: [new Get(uriTemplate: '/push/configuration', provider: PushConfigurationProvider::class)])]
final class PushConfiguration
{
    public bool $disponible = false;
    public ?string $clePublique = null;
    /** Nombre d'appareils abonnés pour l'utilisateur connecté. */
    public int $appareils = 0;
}

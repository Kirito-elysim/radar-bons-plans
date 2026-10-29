<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\Preferences;

/**
 * @implements ProviderInterface<Preferences>
 */
final class PreferencesProvider implements ProviderInterface
{
    public function __construct(private readonly UtilisateurCourant $utilisateur)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Preferences
    {
        return Preferences::depuis($this->utilisateur->get()->getPreference());
    }
}

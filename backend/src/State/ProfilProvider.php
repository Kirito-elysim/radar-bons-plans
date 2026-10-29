<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\Profil;

/**
 * @implements ProviderInterface<Profil>
 */
final class ProfilProvider implements ProviderInterface
{
    public function __construct(private readonly UtilisateurCourant $utilisateur)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Profil
    {
        return Profil::depuis($this->utilisateur->get());
    }
}

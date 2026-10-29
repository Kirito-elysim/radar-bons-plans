<?php

declare(strict_types=1);

namespace App\Api;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Accès typé à l'utilisateur connecté.
 */
final class UtilisateurCourant
{
    public function __construct(private readonly Security $security)
    {
    }

    public function get(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Connexion requise.');
        }

        return $user;
    }

    public function getOuNull(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}

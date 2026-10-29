<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;

/**
 * Adresse d'envoi : un utilisateur, ou l'administrateur défini dans l'environnement.
 */
final readonly class Destinataire
{
    public function __construct(
        public ?string $email,
        public ?string $telegramChatId,
        public ?User $user = null,
    ) {
    }

    public static function depuisUtilisateur(User $user): self
    {
        return new self($user->getEmail(), $user->getPreference()->getTelegramChatId(), $user);
    }
}

<?php

declare(strict_types=1);

namespace App\Alerte;

use App\Entity\User;
use App\Notification\Notificateur;
use App\Repository\UserRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Envoie le récap à chaque utilisateur à son heure (7h40 par défaut), une seule fois par jour et par canal.
 * Appelé toutes les 5 minutes par le scheduler ; si le worker était arrêté, le récap part au redémarrage
 * (jusqu'à 23h59 le jour même).
 */
final class RecapQuotidienService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly RecapQuotidienBuilder $builder,
        private readonly Notificateur $notificateur,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, list<string>> e-mail → canaux sur lesquels le récap est parti
     */
    public function envoyerRecapsDus(): array
    {
        $maintenant = $this->clock->now();
        $bilan = [];
        /** @var list<User> $users */
        $users = $this->users->findAll();
        foreach ($users as $user) {
            if ($this->estDu($user, $maintenant)) {
                $envoyes = $this->envoyer($user, $maintenant);
                if ([] !== $envoyes) {
                    $bilan[$user->getEmail()] = $envoyes;
                }
            }
        }

        return $bilan;
    }

    /**
     * @return list<string>
     */
    public function envoyer(User $user, ?\DateTimeImmutable $maintenant = null, bool $forcer = false): array
    {
        $maintenant ??= $this->clock->now();
        $discriminant = $maintenant->format('Y-m-d').($forcer ? ':force:'.$maintenant->format('His') : '');

        return $this->notificateur->envoyerUneFois($user, $this->builder->construire($user, $maintenant), $discriminant);
    }

    public function estDu(User $user, \DateTimeImmutable $maintenant): bool
    {
        $pref = $user->getPreference();

        return $pref->isRecapActif() && $maintenant->format('H:i') >= $pref->getHeureRecap()->format('H:i');
    }
}

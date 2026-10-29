<?php

declare(strict_types=1);

namespace App\Notification\Canal;

use App\Entity\PushSubscription;
use App\Enum\NotificationChannel;
use App\Notification\Destinataire;
use App\Notification\EnvoiImpossibleException;
use App\Notification\MessageAlerte;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Notification sur tous les appareils où l'utilisateur a activé le push ; les abonnements expirés sont supprimés.
 */
final class PushCanal implements CanalInterface
{
    public function __construct(
        private readonly EnvoyeurPushInterface $envoyeur,
        private readonly PushSubscriptionRepository $abonnements,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function canal(): NotificationChannel
    {
        return NotificationChannel::Push;
    }

    public function disponiblePour(Destinataire $destinataire): bool
    {
        return $this->envoyeur->estConfigure()
            && null !== $destinataire->user
            && $this->abonnements->count(['user' => $destinataire->user]) > 0;
    }

    public function envoyer(Destinataire $destinataire, MessageAlerte $message): void
    {
        /** @var list<PushSubscription> $liste */
        $liste = $this->abonnements->findBy(['user' => $destinataire->user]);
        $charge = [
            'titre' => $message->sujet,
            'corps' => mb_substr($message->corpsCourt(), 0, 180),
            'url' => $message->lienAppli ?? '/',
            'tag' => $message->type->value,
        ];

        $remis = 0;
        $erreurs = [];
        foreach ($liste as $abonnement) {
            try {
                if ($this->envoyeur->envoyer($abonnement, $charge)) {
                    ++$remis;
                } else {
                    $this->em->remove($abonnement);
                }
            } catch (\RuntimeException $e) {
                $erreurs[] = $e->getMessage();
            }
        }
        $this->em->flush();

        if (0 === $remis) {
            throw new EnvoiImpossibleException([] !== $erreurs ? 'Push : '.implode(' | ', $erreurs) : 'Push : aucun appareil abonné.');
        }
    }
}

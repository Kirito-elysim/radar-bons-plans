<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\PushAbonnement;
use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Enregistre (ou retire) l'abonnement push d'un appareil. Un même endpoint réenregistré change de propriétaire
 * (appareil partagé, changement de compte).
 *
 * @implements ProcessorInterface<PushAbonnement, null>
 */
final class PushAbonnementProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PushSubscriptionRepository $abonnements,
        private readonly UtilisateurCourant $utilisateur,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requetes,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->utilisateur->get();
        $existant = $this->abonnements->findOneBy(['endpoint' => $data->endpoint]);

        if ('push_desabonnement' === $operation->getName()) {
            if (null !== $existant && $existant->getUser() === $user) {
                $this->em->remove($existant);
                $this->em->flush();
            }

            return null;
        }

        if (null !== $existant) {
            $this->em->remove($existant);
            $this->em->flush();
        }
        $this->em->persist(new PushSubscription(
            $user,
            $data->endpoint,
            (string) ($data->keys['p256dh'] ?? ''),
            (string) ($data->keys['auth'] ?? ''),
            $this->requetes->getCurrentRequest()?->headers->get('User-Agent'),
        ));
        $this->em->flush();

        return null;
    }
}

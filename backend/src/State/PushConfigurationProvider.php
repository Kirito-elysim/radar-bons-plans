<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\PushConfiguration;
use App\Notification\Canal\EnvoyeurPushInterface;
use App\Repository\PushSubscriptionRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<PushConfiguration>
 */
final class PushConfigurationProvider implements ProviderInterface
{
    public function __construct(
        private readonly EnvoyeurPushInterface $envoyeur,
        private readonly PushSubscriptionRepository $abonnements,
        private readonly UtilisateurCourant $utilisateur,
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private readonly string $clePublique,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PushConfiguration
    {
        $c = new PushConfiguration();
        $c->disponible = $this->envoyeur->estConfigure();
        $c->clePublique = $c->disponible ? $this->clePublique : null;
        $c->appareils = $this->abonnements->count(['user' => $this->utilisateur->get()]);

        return $c;
    }
}

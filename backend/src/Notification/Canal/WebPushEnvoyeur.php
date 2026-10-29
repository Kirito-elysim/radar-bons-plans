<?php

declare(strict_types=1);

namespace App\Notification\Canal;

use App\Entity\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Web Push via minishlink/web-push et les clés VAPID de l'environnement.
 */
final class WebPushEnvoyeur implements EnvoyeurPushInterface
{
    private ?WebPush $webPush = null;

    public function __construct(
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private readonly string $clePublique,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')]
        private readonly string $clePrivee,
        #[Autowire('%env(VAPID_SUBJECT)%')]
        private readonly string $sujet,
    ) {
    }

    public function estConfigure(): bool
    {
        return '' !== $this->clePublique && '' !== $this->clePrivee;
    }

    public function envoyer(PushSubscription $abonnement, array $charge): bool
    {
        $this->webPush ??= new WebPush(['VAPID' => ['subject' => $this->sujet, 'publicKey' => $this->clePublique, 'privateKey' => $this->clePrivee]], ['TTL' => 86400, 'urgency' => 'normal']);

        $rapport = $this->webPush->sendOneNotification(
            Subscription::create([
                'endpoint' => $abonnement->getEndpoint(),
                'keys' => ['p256dh' => $abonnement->getCleP256dh(), 'auth' => $abonnement->getCleAuth()],
            ]),
            json_encode($charge, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
        );

        if ($rapport->isSuccess()) {
            return true;
        }
        if ($rapport->isSubscriptionExpired()) {
            return false;
        }

        throw new \RuntimeException($rapport->getReason());
    }
}

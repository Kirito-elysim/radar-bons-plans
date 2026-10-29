<?php

declare(strict_types=1);

namespace App\Alerte;

use App\Collecte\Event\ConnecteurVideEvent;
use App\Enum\TypeAlerte;
use App\Notification\Destinataire;
use App\Notification\MessageAlerte;
use App\Notification\Notificateur;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Prévient l'administrateur (une fois par jour et par source) quand un connecteur ne trouve plus rien.
 */
#[AsEventListener]
final class ConnecteurVideListener
{
    public function __construct(
        private readonly Notificateur $notificateur,
        private readonly LiensAppli $liens,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(ADMIN_EMAIL)%')]
        private readonly string $adminEmail,
        #[Autowire('%env(ADMIN_TELEGRAM_CHAT_ID)%')]
        private readonly string $adminTelegram,
    ) {
    }

    public function __invoke(ConnecteurVideEvent $evenement): void
    {
        if ('' === $this->adminEmail && '' === $this->adminTelegram) {
            $this->logger->warning('Connecteur {code} vide mais aucun administrateur configuré (ADMIN_EMAIL / ADMIN_TELEGRAM_CHAT_ID).', ['code' => $evenement->source->getCode()]);

            return;
        }

        $source = $evenement->source;
        $this->notificateur->envoyerAdministrateur(
            new Destinataire('' !== $this->adminEmail ? $this->adminEmail : null, '' !== $this->adminTelegram ? $this->adminTelegram : null),
            new MessageAlerte(
                type: TypeAlerte::ConnecteurVide,
                sujet: \sprintf('⚠️ Connecteur « %s » : 0 résultat', $source->getNom()),
                intro: \sprintf('La collecte de %s n\'a renvoyé aucun bon plan. Le site a probablement changé de structure : vérifiez les sélecteurs et rafraîchissez les fixtures.', $source->getUrl()),
                lienAppli: $this->liens->vers('/admin'),
            ),
            $source->getCode().':'.$this->clock->now()->format('Y-m-d'),
        );
    }
}

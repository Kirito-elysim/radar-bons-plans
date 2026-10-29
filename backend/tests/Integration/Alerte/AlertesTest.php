<?php

declare(strict_types=1);

namespace App\Tests\Integration\Alerte;

use App\Alerte\AlerteExpirationService;
use App\Alerte\NouveauCatalogueAlerteService;
use App\Entity\Favorite;
use App\Entity\PushSubscription;
use App\Enum\DealType;
use App\Enum\NotificationChannel;
use App\Enum\RemboursementType;
use App\Repository\PushSubscriptionRepository;
use App\Tests\Support\FauxEnvoyeurPush;
use App\Tests\Support\ReponsesHttpSimulees;

final class AlertesTest extends AlerteTestCase
{
    public function testAlertesJ3EtJ1SurFavorisEt100PourCent(): void
    {
        $user = $this->utilisateur();
        $favori = $this->deal('Café en favori', 3, valeur: '1.50');
        $this->deal('Lait 100 %', 1, sousType: RemboursementType::Rembourse100);
        $this->deal('Ni favori ni 100 %', 3);
        $this->deal('Favori trop loin', 5);
        $this->em()->persist(new Favorite($user, $favori));
        $this->em()->flush();

        $service = self::getContainer()->get(AlerteExpirationService::class);
        self::assertSame(2, $service->executer());

        $emails = $this->emails();
        self::assertCount(2, $emails);
        $sujets = array_map(static fn ($e): string => (string) $e->getSubject(), $emails);
        self::assertContains('⏰ Se termine dans 3 jours : Café en favori', $sujets);
        self::assertContains('⏰ Se termine demain : Lait 100 %', $sujets);
        self::assertSame('moi@exemple.test', $emails[0]->getTo()[0]->getAddress());
        self::assertStringContainsString('https://exemple.test/Caf%C3%A9%20en%20favori', (string) $emails[0]->getHtmlBody().(string) $emails[1]->getHtmlBody());
        self::assertStringNotContainsString('Ni favori', (string) $emails[0]->getHtmlBody().(string) $emails[1]->getHtmlBody());

        // Relancer ne renvoie rien : chaque alerte n'est envoyée qu'une fois.
        self::assertSame(0, $service->executer());
        self::assertCount(2, $this->emails());
    }

    public function testDesactiverLesAlertes100PourCentEtSeuilPersonnalise(): void
    {
        $user = $this->utilisateur();
        $user->getPreference()->setAlertesRembourse100(false)->setSeuilAlerteJours(5);
        $favori = $this->deal('Favori à J-5', 5);
        $this->deal('Lait 100 %', 1, sousType: RemboursementType::Rembourse100);
        $this->em()->persist(new Favorite($user, $favori));
        $this->em()->flush();

        self::getContainer()->get(AlerteExpirationService::class)->executer();

        self::assertSame(['⏰ Se termine dans 5 jours : Favori à J-5'], array_map(static fn ($e): string => (string) $e->getSubject(), $this->emails()));
    }

    public function testTelegramEtPushSelonLesPreferences(): void
    {
        $user = $this->utilisateur(canaux: [NotificationChannel::Telegram, NotificationChannel::Push], telegram: '123456');
        $this->em()->persist(new PushSubscription($user, 'https://push.exemple.test/appareil-1', 'p256dh', 'auth'));
        $this->em()->persist(new PushSubscription($user, 'https://push.exemple.test/ancien', 'p256dh', 'auth'));
        $this->em()->flush();
        FauxEnvoyeurPush::$expires = ['https://push.exemple.test/ancien'];
        $this->deal('Lait 100 %', 1, sousType: RemboursementType::Rembourse100);

        self::getContainer()->get(AlerteExpirationService::class)->executer();

        self::assertCount(0, $this->emails(), 'E-mail non choisi');
        self::assertCount(1, ReponsesHttpSimulees::$telegram);
        self::assertStringContainsString('api.telegram.org/bot123456:jeton-test/sendMessage', ReponsesHttpSimulees::$telegram[0]['url']);
        self::assertStringContainsString('"chat_id":"123456"', ReponsesHttpSimulees::$telegram[0]['corps']);
        self::assertStringContainsString('Lait 100 %', ReponsesHttpSimulees::$telegram[0]['corps']);

        self::assertCount(1, FauxEnvoyeurPush::$envois);
        self::assertSame('⏰ Se termine demain : Lait 100 %', FauxEnvoyeurPush::$envois[0]['charge']['titre']);
        self::assertSame('https://radar.exemple.test/?urgence=1', FauxEnvoyeurPush::$envois[0]['charge']['url']);
        self::assertSame(1, self::getContainer()->get(PushSubscriptionRepository::class)->count(['user' => $user]), 'Abonnement expiré supprimé');
    }

    public function testCanalSansCoordonneesIgnore(): void
    {
        $this->utilisateur(canaux: [NotificationChannel::Telegram]);
        $this->deal('Lait 100 %', 1, sousType: RemboursementType::Rembourse100);

        self::assertSame(0, self::getContainer()->get(AlerteExpirationService::class)->executer());
        self::assertSame([], ReponsesHttpSimulees::$telegram, 'Pas d\'identifiant Telegram renseigné');
    }

    public function testNouveauCataloguePourLesEnseignesSuivies(): void
    {
        $user = $this->utilisateur();
        $user->getPreference()->setEnseignesSuivies(['Lidl', 'Carrefour']);
        $this->em()->flush();
        $this->deal('Catalogue Lidl', 6, DealType::Catalogue, enseigne: 'Lidl', vuIlYA: '2 hours');
        $this->deal('Catalogue Carrefour', 10, DealType::Catalogue, enseigne: 'Carrefour', vuIlYA: '1 hour');
        $this->deal('Catalogue Aldi', 6, DealType::Catalogue, enseigne: 'Aldi', vuIlYA: '2 hours');
        $this->deal('Ancien Lidl', 3, DealType::Catalogue, enseigne: 'Lidl', vuIlYA: '5 days');

        $service = self::getContainer()->get(NouveauCatalogueAlerteService::class);
        self::assertSame(1, $service->executer());

        $emails = $this->emails();
        self::assertCount(1, $emails);
        self::assertSame('🗞️ 2 nouveaux catalogues : Carrefour, Lidl', $emails[0]->getSubject());
        self::assertStringNotContainsString('Aldi', (string) $emails[0]->getHtmlBody());

        // Un nouveau catalogue plus tard : seul celui-ci est signalé.
        $this->deal('Catalogue Lidl 2', 7, DealType::Catalogue, enseigne: 'Lidl', vuIlYA: '1 minute');
        $service->executer();
        self::assertSame('🗞️ Nouveau catalogue Lidl', $this->emails()[1]->getSubject());
    }

    public function testSansEnseigneSuivieAucuneAlerteCatalogue(): void
    {
        $this->utilisateur();
        $this->deal('Catalogue Lidl', 6, DealType::Catalogue, enseigne: 'Lidl', vuIlYA: '2 hours');

        self::assertSame(0, self::getContainer()->get(NouveauCatalogueAlerteService::class)->executer());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\NotificationChannel;
use App\Repository\PushSubscriptionRepository;
use App\Tests\Support\FauxEnvoyeurPush;
use App\Tests\Support\ReponsesHttpSimulees;

final class NotificationsApiTest extends ApiTestCaseRadar
{
    private const ABONNEMENT = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        'expirationTime' => null,
        'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        FauxEnvoyeurPush::reinitialiser();
    }

    public function testConfigurationPush(): void
    {
        $user = $this->creerUtilisateur();

        $this->client->request('GET', '/api/push/configuration', $this->authentifie($user));

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['disponible' => true, 'clePublique' => 'cle-publique-test', 'appareils' => 0]);
    }

    public function testAbonnementEtDesabonnement(): void
    {
        $user = $this->creerUtilisateur();
        $auth = $this->authentifie($user);
        $repo = static::getContainer()->get(PushSubscriptionRepository::class);

        $this->client->request('POST', '/api/push/abonnements', $auth + ['json' => self::ABONNEMENT]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('POST', '/api/push/abonnements', $auth + ['json' => self::ABONNEMENT]);
        self::assertSame(1, $repo->count(['user' => $user]), 'Réabonnement idempotent');

        $this->client->request('POST', '/api/push/desabonnement', $auth + ['json' => ['endpoint' => self::ABONNEMENT['endpoint']]]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $repo->count(['user' => $user]));
    }

    public function testAbonnementInvalide(): void
    {
        $auth = $this->authentifie($this->creerUtilisateur());

        $this->client->request('POST', '/api/push/abonnements', $auth + ['json' => ['endpoint' => 'http://pas-https.test', 'keys' => []]]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testEnvoiDeTestSurChaqueCanal(): void
    {
        $user = $this->creerUtilisateur();
        $user->getPreference()->setTelegramChatId('987654')->setCanaux([NotificationChannel::Email]);
        $this->em()->flush();
        $auth = $this->authentifie($user);

        $resultats = $this->client->request('POST', '/api/notifications/test', $auth + ['json' => []])->toArray()['resultats'];
        self::assertResponseIsSuccessful();
        self::assertEquals(['email' => 'envoyé', 'telegram' => 'envoyé', 'push' => 'non configuré'], $resultats);
        self::assertCount(1, ReponsesHttpSimulees::$telegram);
        self::assertEmailCount(1);

        $this->client->request('POST', '/api/push/abonnements', $auth + ['json' => self::ABONNEMENT]);
        $resultats = $this->client->request('POST', '/api/notifications/test', $auth + ['json' => []])->toArray()['resultats'];
        self::assertSame('envoyé', $resultats['push']);
        self::assertSame('🔔 Test Radar Bons Plans', FauxEnvoyeurPush::$envois[0]['charge']['titre']);
    }

    public function testApercuDuRecap(): void
    {
        $user = $this->creerUtilisateur();
        $this->creerDeal('Nouvelle offre', 10, valeur: '3.00', vuLe: new \DateTimeImmutable('-1 hour'));

        $apercu = $this->client->request('GET', '/api/recap/apercu', $this->authentifie($user))->toArray();

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('☀️ Vos bons plans du ', $apercu['sujet']);
        self::assertSame('1 nouveau bon plan repéré', $apercu['resume'][0]);
        self::assertSame(['Nouveaux catalogues', 'Nouvelles offres 100 % remboursées', 'Se terminent sous 3 jours', 'Autres nouveautés'], array_column($apercu['sections'], 'titre'));
        self::assertSame('Nouvelle offre', $apercu['sections'][3]['lignes'][0]['titre']);
    }
}

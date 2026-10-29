<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\Fixtures;
use App\Tests\Support\ReponsesHttpSimulees;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TikTokEtCreateursApiTest extends ApiTestCaseRadar
{
    private const VIDEO = 'https://www.tiktok.com/@nelson_mrq/video/7430000000000000001';
    private const OEMBED = 'https://www.tiktok.com/oembed?url=https%3A%2F%2Fwww.tiktok.com%2F%40nelson_mrq%2Fvideo%2F7430000000000000001';

    public function testApercuPuisSaisieDuBonPlan(): void
    {
        ReponsesHttpSimulees::definir(self::OEMBED, new MockResponse(Fixtures::lire('tiktok/oembed-video.json')));
        $auth = $this->authentifie($this->creerUtilisateur());

        $this->client->request('POST', '/api/tiktok/apercu', $auth + ['json' => ['url' => self::VIDEO.'?is_from_webapp=1']]);
        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains(['auteurPseudo' => 'nelson_mrq', 'createurSuivi' => true, 'idVideo' => '7430000000000000001', 'url' => self::VIDEO]);

        $this->client->request('POST', '/api/tiktok/bons-plans', $auth + ['json' => [
            'url' => self::VIDEO, 'produit' => 'Lessive Skip', 'enseigne' => 'Lidl', 'economie' => '8,99', 'dateFin' => '2026-11-15',
        ]]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['type' => 'tiktok', 'titre' => 'Lessive Skip', 'enseigne' => 'Lidl', 'valeurEuros' => '8.99', 'createur' => 'nelson_mrq', 'dateFin' => '2026-11-15', 'url' => self::VIDEO]);
    }

    public function testSaisieConserveeSiTikTokRefuseLOEmbed(): void
    {
        ReponsesHttpSimulees::definir(self::OEMBED, new MockResponse('', ['http_code' => 429]));
        $auth = $this->authentifie($this->creerUtilisateur());

        $this->client->request('POST', '/api/tiktok/apercu', $auth + ['json' => ['url' => self::VIDEO]]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'TikTok limite temporairement les requêtes : réessayez dans quelques minutes ou saisissez le bon plan à la main.']);

        $this->client->request('POST', '/api/tiktok/bons-plans', $auth + ['json' => ['url' => self::VIDEO, 'produit' => 'Lessive Skip']]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['titre' => 'Lessive Skip', 'createur' => 'nelson_mrq']);
    }

    public function testLienInvalide(): void
    {
        $auth = $this->authentifie($this->creerUtilisateur());

        $this->client->request('POST', '/api/tiktok/apercu', $auth + ['json' => ['url' => 'https://www.tiktok.com/@nelson_mrq']]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/api/tiktok/bons-plans', $auth + ['json' => ['url' => 'pas un lien', 'produit' => '']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateursLectureUtilisateurEcritureAdmin(): void
    {
        $user = $this->creerUtilisateur();
        $admin = $this->creerUtilisateur('admin@exemple.test', admin: true);

        $liste = self::membres($this->client->request('GET', '/api/createurs', $this->authentifie($user))->toArray());
        self::assertSame(['lejusticierdbp', 'nelson_mrq'], self::colonne($liste, 'pseudo'));

        $this->client->request('POST', '/api/createurs', $this->authentifie($user) + ['json' => ['pseudo' => 'quelquun']]);
        self::assertResponseStatusCodeSame(403);

        $cree = $this->client->request('POST', '/api/createurs', $this->authentifie($admin) + ['json' => ['pseudo' => '@nouveau_compte']])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('https://www.tiktok.com/@nouveau_compte', $cree['urlProfil']);

        $nelson = array_values(array_filter($liste, static fn (array $c): bool => 'nelson_mrq' === $c['pseudo']))[0];
        $this->client->request('PATCH', '/api/createurs/'.$nelson['id'], $this->authentifie($admin) + [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['lienBio' => 'https://linktr.ee/nelson_mrq'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['pseudo' => 'nelson_mrq', 'lienBio' => 'https://linktr.ee/nelson_mrq']);

        $this->client->request('POST', '/api/createurs', $this->authentifie($admin) + ['json' => ['pseudo' => 'nelson_mrq']]);
        self::assertResponseStatusCodeSame(422);
    }
}

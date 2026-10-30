<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\RunStatus;
use App\Repository\SourceRepository;
use App\Tests\Support\Fixtures;
use App\Tests\Support\ReponsesHttpSimulees;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AdminSourcesApiTest extends ApiTestCaseRadar
{
    private const PATCH = ['Content-Type' => 'application/merge-patch+json'];

    /**
     * @param array<string, mixed> $auth
     *
     * @return array<string, mixed>
     */
    private function sourceAdmin(array $auth, string $code): array
    {
        foreach (self::membres($this->client->request('GET', '/api/admin/sources', $auth)->toArray()) as $s) {
            if ($s['code'] === $code) {
                return $s;
            }
        }
        self::fail('Source absente : '.$code);
    }

    public function testReserveAuxAdministrateurs(): void
    {
        $auth = $this->authentifie($this->creerUtilisateur());

        $this->client->request('GET', '/api/admin/sources', $auth);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/api/admin/sources/1/relancer', $auth + ['json' => []]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testEtatDesSources(): void
    {
        $auth = $this->authentifie($this->creerUtilisateur('admin@exemple.test', admin: true));
        $source = static::getContainer()->get(SourceRepository::class)->findOneByCode('shopmium');
        self::assertNotNull($source);
        $source->enregistrerRun(new \DateTimeImmutable('2026-10-29 06:15'), RunStatus::Vide, 0);
        $this->em()->flush();
        $this->creerDeal('Offre en cours', 5);
        $this->creerDeal('Offre terminée', -2);

        $sources = self::membres($this->client->request('GET', '/api/admin/sources', $auth)->toArray());

        self::assertResponseIsSuccessful();
        self::assertCount(7, $sources);
        $shopmium = $this->sourceAdmin($auth, 'shopmium');
        self::assertSame('vide', $shopmium['dernierStatut']);
        self::assertSame(0, $shopmium['dernierNbDeals']);
        self::assertTrue($shopmium['collectable']);
        self::assertFalse($this->sourceAdmin($auth, 'tiktok')['collectable'], 'Saisie manuelle');
        self::assertSame(1, $this->sourceAdmin($auth, 'anti_crise_remboursement')['dealsEnCours']);
    }

    public function testDesactiverPuisReactiver(): void
    {
        $auth = $this->authentifie($this->creerUtilisateur('admin@exemple.test', admin: true));
        $id = $this->sourceAdmin($auth, 'radins')['id'];

        $this->client->request('PATCH', '/api/admin/sources/'.$id, $auth + ['headers' => self::PATCH, 'json' => ['actif' => false, 'nom' => 'Piraté']]);
        self::assertResponseIsSuccessful();
        // Seul « actif » est modifiable : le nom envoyé est ignoré.
        self::assertJsonContains(['actif' => false, 'nom' => 'Radins.com']);
        self::assertNotContains('radins', array_map(
            static fn ($s): string => $s->getCode(),
            static::getContainer()->get(SourceRepository::class)->findActives(),
        ));

        $this->client->request('PATCH', '/api/admin/sources/'.$id, $auth + ['headers' => self::PATCH, 'json' => ['actif' => true]]);
        self::assertJsonContains(['actif' => true]);
    }

    public function testRelancerUneSource(): void
    {
        ReponsesHttpSimulees::definir('https://anti-crise.fr/catalogue/', new MockResponse(Fixtures::lire('anti-crise/catalogues-synthetique.html')));
        $auth = $this->authentifie($this->creerUtilisateur('admin@exemple.test', admin: true));
        $id = $this->sourceAdmin($auth, 'anti_crise_catalogue')['id'];

        $r = $this->client->request('POST', '/api/admin/sources/'.$id.'/relancer', $auth + ['json' => []])->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame('succes', $r['dernierStatut']);
        self::assertSame(7, $r['dernierNbDeals']);
        self::assertSame(['statut' => 'succes', 'recus' => 7, 'nouveaux' => 7, 'misAJour' => 0, 'pagesIgnorees' => [], 'erreur' => null], $r['rapport']);
        self::assertSame(7, $r['dealsEnCours'] + $this->expiresDansFixture());
    }

    public function testRelancerEnErreurEtSourceManuelle(): void
    {
        ReponsesHttpSimulees::definir('https://offers.shopmium.com/fr', new MockResponse('panne', ['http_code' => 500]));
        $auth = $this->authentifie($this->creerUtilisateur('admin@exemple.test', admin: true));

        $r = $this->client->request('POST', '/api/admin/sources/'.$this->sourceAdmin($auth, 'shopmium')['id'].'/relancer', $auth + ['json' => []])->toArray();
        self::assertSame('erreur', $r['dernierStatut']);
        self::assertStringContainsString('HTTP 500', (string) $r['dernierErreur']);

        $this->client->request('POST', '/api/admin/sources/'.$this->sourceAdmin($auth, 'tiktok')['id'].'/relancer', $auth + ['json' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Cette source n\'a pas de connecteur : elle est alimentée à la main.']);
    }

    /**
     * Catalogues de la fixture déjà terminés à la date du jour (fixture synthétique datée de fin octobre 2026).
     */
    private function expiresDansFixture(): int
    {
        return (int) static::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM deal d JOIN source s ON s.id = d.source_id WHERE s.code = 'anti_crise_catalogue' AND (d.expire OR d.date_fin < CURRENT_DATE)",
        );
    }
}

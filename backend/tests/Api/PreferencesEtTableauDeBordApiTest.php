<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Favorite;
use App\Entity\Saving;
use App\Enum\DealType;
use App\Enum\RemboursementType;

final class PreferencesEtTableauDeBordApiTest extends ApiTestCaseRadar
{
    private const PATCH = ['Content-Type' => 'application/merge-patch+json'];

    public function testPreferencesParDefautPuisModification(): void
    {
        $user = $this->creerUtilisateur();
        $auth = $this->authentifie($user);

        $this->client->request('GET', '/api/preferences', $auth);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['seuilAlerteJours' => 3, 'heureRecap' => '07:40', 'canaux' => ['email'], 'recapActif' => true]);

        $this->client->request('PATCH', '/api/preferences', $auth + ['headers' => self::PATCH, 'json' => [
            'enseignesSuivies' => ['Carrefour', 'Lidl'],
            'canaux' => ['push', 'telegram'],
            'heureRecap' => '06:30',
            'telegramChatId' => '123456789',
        ]]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['enseignesSuivies' => ['Carrefour', 'Lidl'], 'canaux' => ['push', 'telegram'], 'heureRecap' => '06:30', 'seuilAlerteJours' => 3]);

        $this->client->request('GET', '/api/preferences', $auth);
        self::assertJsonContains(['heureRecap' => '06:30', 'telegramChatId' => '123456789']);
    }

    public function testPreferencesInvalides(): void
    {
        $auth = $this->authentifie($this->creerUtilisateur());

        $this->client->request('PATCH', '/api/preferences', $auth + ['headers' => self::PATCH, 'json' => ['canaux' => ['pigeon'], 'heureRecap' => '25:00', 'seuilAlerteJours' => 60]]);

        self::assertResponseStatusCodeSame(422);
        $chemins = array_column($this->client->getResponse()?->toArray(false)['violations'] ?? [], 'propertyPath');
        self::assertEqualsCanonicalizing(['canaux', 'heureRecap', 'seuilAlerteJours'], $chemins);
    }

    public function testCompteursEtBandeauASaisir(): void
    {
        $user = $this->creerUtilisateur();
        $user->getPreference()->setEnseignesSuivies(['Lidl']);
        $critique = $this->creerDeal('Remboursé 100 %', 1, valeur: '4.00', sousType: RemboursementType::Rembourse100);
        $this->creerDeal('Catalogue Lidl', 6, DealType::Catalogue, enseigne: 'Lidl');
        $favori = $this->creerDeal('Mon favori', 5, valeur: '2.50');
        $this->creerDeal('Loin', 25, valeur: '10.00', vuLe: new \DateTimeImmutable());
        $this->em()->persist(new Favorite($user, $favori));
        $this->em()->persist(new Saving($user, 'Ce mois', '3.00', $this->aujourdhui));
        $this->em()->persist(new Saving($user, 'Il y a longtemps', '7.00', new \DateTimeImmutable('2025-01-15')));
        $this->em()->flush();

        $t = $this->client->request('GET', '/api/tableau-de-bord', $this->authentifie($user))->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(1, $t['finissentSous3Jours']);
        self::assertSame(1, $t['rembourses100EnCours']);
        self::assertSame(1, $t['nouveauxDepuisHier']);
        self::assertSame('16.50', $t['economiePotentielle']);
        self::assertSame('3.00', $t['economiesMois']);
        self::assertSame('10.00', $t['economiesTotal']);
        self::assertSame(3, $t['parType']['remboursement']);
        self::assertSame(1, $t['parType']['catalogue']);

        // Favori, puis enseigne suivie, puis date de fin ; « Loin » (J-25) exclu.
        self::assertSame(['Mon favori', 'Catalogue Lidl', 'Remboursé 100 %'], array_column($t['aSaisir'], 'titre'));
        self::assertSame('critique', $t['aSaisir'][2]['urgence'], 'Objets complets, pas des IRI');
        self::assertSame($critique->getId(), $t['aSaisir'][2]['id']);
    }

    public function testCalendrierDuMois(): void
    {
        $user = $this->creerUtilisateur();
        $this->creerDeal('Catalogue', 3, DealType::Catalogue, enseigne: 'Lidl', debutIlYA: 2);
        $this->creerDeal('Remboursement', 4);

        $c = $this->client->request('GET', '/api/calendrier?mois='.$this->aujourdhui->format('Y-m'), $this->authentifie($user))->toArray();
        $dans = fn (int $j): string => $this->aujourdhui->modify("+$j days")->format('Y-m-d');
        $attendus = array_values(array_filter([
            ['date' => $this->aujourdhui->modify('-2 days')->format('Y-m-d'), 'nature' => 'debut', 'titre' => 'Catalogue'],
            ['date' => $dans(3), 'nature' => 'fin', 'titre' => 'Catalogue'],
            ['date' => $dans(4), 'nature' => 'fin', 'titre' => 'Remboursement'],
        ], fn (array $e): bool => str_starts_with($e['date'], $this->aujourdhui->format('Y-m'))));

        self::assertSame($this->aujourdhui->format('Y-m'), $c['mois']);
        self::assertSame($attendus, array_map(static fn (array $e): array => ['date' => $e['date'], 'nature' => $e['nature'], 'titre' => $e['titre']], $c['evenements']));
    }
}

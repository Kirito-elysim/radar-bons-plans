<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\DealType;

final class RegionsApiTest extends ApiTestCaseRadar
{
    private function catalogue(string $titre, ?string $region, int $finDans = 5): void
    {
        $deal = $this->creerDeal($titre, $finDans, DealType::Catalogue, enseigne: 'Carrefour', vuLe: new \DateTimeImmutable());
        $deal->setRegion($region);
        $this->em()->flush();
    }

    /**
     * @param array<string, mixed> $auth
     *
     * @return list<mixed>
     */
    private function titres(array $auth, string $requete = ''): array
    {
        return self::colonne(self::membres($this->client->request('GET', '/api/bons-plans?type=catalogue'.$requete, $auth)->toArray()), 'titre');
    }

    public function testParDefautPacaEtNationaux(): void
    {
        $user = $this->creerUtilisateur();
        $this->catalogue('National', null, 1);
        $this->catalogue('Exclus PACA', 'paca', 2);
        $this->catalogue('Sud foire au gras', 'paca,occitanie,sud-ouest', 3);
        $this->catalogue('Exclus Nord', 'nord', 4);
        $auth = $this->authentifie($user);

        self::assertSame(['National', 'Exclus PACA', 'Sud foire au gras'], $this->titres($auth));
        self::assertSame(['National', 'Exclus PACA', 'Sud foire au gras', 'Exclus Nord'], $this->titres($auth, '&toutesRegions=1'));
        self::assertSame(['Exclus Nord'], $this->titres($auth, '&toutesRegions=1&region=nord'));

        $carte = self::membres($this->client->request('GET', '/api/bons-plans?q=Exclus%20PACA', $auth)->toArray())[0];
        self::assertSame("Provence-Alpes-Côte d'Azur (Provencia)", $carte['regionLibelle']);

        // Tableau de bord, bandeau et calendrier ignorent aussi les autres régions.
        $t = $this->client->request('GET', '/api/tableau-de-bord', $auth)->toArray();
        self::assertSame(3, $t['parType']['catalogue']);
        self::assertNotContains('Exclus Nord', array_column($t['aSaisir'], 'titre'));
        $c = $this->client->request('GET', '/api/calendrier?mois='.$this->aujourdhui->modify('+4 days')->format('Y-m'), $auth)->toArray();
        self::assertNotContains('Exclus Nord', array_column($c['evenements'], 'titre'));
    }

    public function testChangerDeRegionOuToutVoir(): void
    {
        $user = $this->creerUtilisateur();
        $this->catalogue('Exclus PACA', 'paca');
        $this->catalogue('Exclus Nord', 'nord');
        $auth = $this->authentifie($user);
        $patch = ['headers' => ['Content-Type' => 'application/merge-patch+json']];

        $prefs = $this->client->request('GET', '/api/preferences', $auth)->toArray();
        self::assertSame('paca', $prefs['region']);
        self::assertContains(['code' => 'paca', 'libelle' => "Provence-Alpes-Côte d'Azur (Provencia)"], $prefs['regionsDisponibles']);

        $this->client->request('PATCH', '/api/preferences', $auth + $patch + ['json' => ['region' => 'nord']]);
        self::assertResponseIsSuccessful();
        self::assertSame(['Exclus Nord'], $this->titres($auth));

        $this->client->request('PATCH', '/api/preferences', $auth + $patch + ['json' => ['region' => null]]);
        self::assertSame(['Exclus PACA', 'Exclus Nord'], $this->titres($auth));

        $this->client->request('PATCH', '/api/preferences', $auth + $patch + ['json' => ['region' => 'atlantide']]);
        self::assertResponseStatusCodeSame(422);
    }
}

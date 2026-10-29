<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Favorite;
use App\Enum\DealType;
use App\Enum\RemboursementType;

final class BonsPlansApiTest extends ApiTestCaseRadar
{
    public function testListeTrieeParDateDeFinSansLesTerminees(): void
    {
        $user = $this->creerUtilisateur();
        $this->creerDeal('Dans 5 jours', 5);
        $this->creerDeal('Dans 2 jours', 2);
        $this->creerDeal('Sans date', null);
        $this->creerDeal('Terminé hier', -1);

        $reponse = $this->client->request('GET', '/api/bons-plans', $this->authentifie($user))->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(['Dans 2 jours', 'Dans 5 jours', 'Sans date'], self::colonne(self::membres($reponse), 'titre'));
        self::assertSame(3, $reponse['totalItems']);
    }

    public function testChampsCalculesDUneCarte(): void
    {
        $user = $this->creerUtilisateur();
        $this->creerDeal('Lessive 100 %', 2, marque: 'Skip', valeur: '8.99', sousType: RemboursementType::Rembourse100, vuLe: new \DateTimeImmutable());

        $carte = self::membres($this->client->request('GET', '/api/bons-plans', $this->authentifie($user))->toArray())[0];

        self::assertSame('remboursement', $carte['type']);
        self::assertSame('Offre de remboursement', $carte['typeLibelle']);
        self::assertSame('rembourse_100', $carte['sousType']);
        self::assertSame('100 % remboursé', $carte['sousTypeLibelle']);
        self::assertSame('8.99', $carte['valeurEuros']);
        self::assertSame(2, $carte['joursRestants']);
        self::assertSame('critique', $carte['urgence']);
        self::assertTrue($carte['nouveau']);
        self::assertFalse($carte['favori']);
        self::assertSame('anti_crise_remboursement', $carte['sourceCode']);
        self::assertSame('https://exemple.test/Lessive%20100%20%25', $carte['url']);
    }

    public function testFiltres(): void
    {
        $user = $this->creerUtilisateur();
        $this->creerDeal('Catalogue Lidl', 6, DealType::Catalogue, enseigne: 'Lidl');
        $this->creerDeal('Catalogue Carrefour Provencia', 10, DealType::Catalogue, enseigne: 'Carrefour');
        $this->creerDeal('Yaourts Danone', 20, marque: 'Danone', sousType: RemboursementType::Rembourse100);
        $this->creerDeal('Café Carte Noire', 1, marque: 'Carte Noire', sousType: RemboursementType::Partiel);
        $this->creerDeal('Code Shein', 30, DealType::CodePromo, enseigne: 'SHEIN', vuLe: new \DateTimeImmutable());
        $auth = $this->authentifie($user);

        $titres = fn (string $requete): array => self::colonne(self::membres($this->client->request('GET', '/api/bons-plans?'.$requete, $auth)->toArray()), 'titre');

        self::assertSame(['Catalogue Lidl', 'Catalogue Carrefour Provencia'], $titres('type=catalogue'));
        self::assertSame(['Café Carte Noire', 'Catalogue Lidl', 'Code Shein'], $titres('type=catalogue,code_promo,remboursement&enseigne=lidl,shein,Carte%20Noire'));
        self::assertSame(['Yaourts Danone'], $titres('sousType=rembourse_100'));
        self::assertSame(['Café Carte Noire', 'Catalogue Lidl'], $titres('expireSous=7'));
        self::assertSame(['Code Shein'], $titres('nouveau=1'));
        self::assertSame(['Catalogue Carrefour Provencia'], $titres('q=provencia'));
        self::assertSame(['Code Shein', 'Catalogue Lidl', 'Catalogue Carrefour Provencia', 'Yaourts Danone', 'Café Carte Noire'], $titres('tri=recents'), 'Plus récents d\'abord, puis ordre de création');
        self::assertSame([], $titres('type=inconnu&q=zzz'));
    }

    public function testFiltreFavorisEtIndicateur(): void
    {
        $moi = $this->creerUtilisateur();
        $autre = $this->creerUtilisateur('autre@exemple.test');
        $favori = $this->creerDeal('Mon favori', 3);
        $this->creerDeal('Pas favori', 4);
        $this->em()->persist(new Favorite($moi, $favori));
        $this->em()->flush();

        $miens = self::membres($this->client->request('GET', '/api/bons-plans?favoris=1', $this->authentifie($moi))->toArray());
        self::assertSame(['Mon favori'], self::colonne($miens, 'titre'));
        self::assertTrue($miens[0]['favori']);

        self::assertSame([], self::membres($this->client->request('GET', '/api/bons-plans?favoris=1', $this->authentifie($autre))->toArray()));
    }

    public function testPagination(): void
    {
        $user = $this->creerUtilisateur();
        for ($i = 1; $i <= 5; ++$i) {
            $this->creerDeal('Offre '.$i, $i);
        }

        $reponse = $this->client->request('GET', '/api/bons-plans?itemsPerPage=2&page=2', $this->authentifie($user))->toArray();

        self::assertSame(['Offre 3', 'Offre 4'], self::colonne(self::membres($reponse), 'titre'));
        self::assertSame(5, $reponse['totalItems']);
    }

    public function testDetailEt404(): void
    {
        $user = $this->creerUtilisateur();
        $deal = $this->creerDeal('Détail', 3);

        $this->client->request('GET', '/api/bons-plans/'.$deal->getId(), $this->authentifie($user));
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['titre' => 'Détail', 'joursRestants' => 3]);

        $this->client->request('GET', '/api/bons-plans/999999', $this->authentifie($user));
        self::assertResponseStatusCodeSame(404);
    }
}

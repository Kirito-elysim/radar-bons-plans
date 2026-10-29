<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\DealType;

final class FavorisEtEconomiesApiTest extends ApiTestCaseRadar
{
    public function testAjoutIdempotentListeEtSuppressionDeFavori(): void
    {
        $user = $this->creerUtilisateur();
        $deal = $this->creerDeal('Favori', 5);
        $auth = $this->authentifie($user);

        $this->client->request('POST', '/api/favoris', $auth + ['json' => ['bonPlanId' => $deal->getId()]]);
        self::assertResponseStatusCodeSame(201);
        $this->client->request('POST', '/api/favoris', $auth + ['json' => ['bonPlanId' => $deal->getId()]]);
        self::assertResponseStatusCodeSame(201);

        $liste = self::membres($this->client->request('GET', '/api/favoris', $auth)->toArray());
        self::assertSame([$deal->getId()], self::colonne($liste, 'bonPlanId'));

        $this->client->request('DELETE', '/api/favoris/'.$deal->getId(), $auth);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], self::membres($this->client->request('GET', '/api/favoris', $auth)->toArray()));
    }

    public function testFavoriSurBonPlanInexistant(): void
    {
        $user = $this->creerUtilisateur();

        $this->client->request('POST', '/api/favoris', $this->authentifie($user) + ['json' => ['bonPlanId' => 999999]]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testJEnAiProfiteAvecMontantPreRempli(): void
    {
        $user = $this->creerUtilisateur();
        $deal = $this->creerDeal('Lessive Skip', 5, marque: 'Skip', valeur: '8.99');
        $auth = $this->authentifie($user);

        $creee = $this->client->request('POST', '/api/economies', $auth + ['json' => ['bonPlanId' => $deal->getId()]])->toArray();

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Lessive Skip', $creee['libelle']);
        self::assertSame('8.99', $creee['montant']);
        self::assertSame('Skip', $creee['enseigne']);
        self::assertSame($this->aujourdhui->format('Y-m-d'), $creee['date']);
        self::assertSame($deal->getId(), $creee['bonPlanId']);

        // Montant corrigé ensuite
        $this->client->request('PATCH', '/api/economies/'.$creee['id'], $auth + [
            'json' => ['montant' => '7,50'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['montant' => '7.50', 'libelle' => 'Lessive Skip']);
    }

    public function testEconomieLibreValidationEtSuppression(): void
    {
        $user = $this->creerUtilisateur();
        $auth = $this->authentifie($user);

        $this->client->request('POST', '/api/economies', $auth + ['json' => ['libelle' => 'Promo en magasin']]);
        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'montant', 'message' => 'Indiquez le montant économisé.']]]);

        $this->client->request('POST', '/api/economies', $auth + ['json' => ['libelle' => 'Promo', 'montant' => 'beaucoup']]);
        self::assertResponseStatusCodeSame(422);

        $creee = $this->client->request('POST', '/api/economies', $auth + ['json' => ['libelle' => 'Promo en magasin', 'montant' => '3,20', 'date' => '2026-10-01', 'enseigne' => 'Lidl']])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('3.20', $creee['montant']);
        self::assertNull($creee['bonPlanId']);

        $this->client->request('DELETE', '/api/economies/'.$creee['id'], $auth);
        self::assertResponseStatusCodeSame(204);
    }

    public function testLesEconomiesDesAutresSontInvisibles(): void
    {
        $moi = $this->creerUtilisateur();
        $autre = $this->creerUtilisateur('autre@exemple.test');
        $sienne = $this->client->request('POST', '/api/economies', $this->authentifie($autre) + ['json' => ['libelle' => 'À lui', 'montant' => '5']])->toArray();

        $auth = $this->authentifie($moi);
        self::assertSame([], self::membres($this->client->request('GET', '/api/economies', $auth)->toArray()));
        $this->client->request('GET', '/api/economies/'.$sienne['id'], $auth);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('PATCH', '/api/economies/'.$sienne['id'], $auth + ['json' => ['montant' => '0'], 'headers' => ['Content-Type' => 'application/merge-patch+json']]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('DELETE', '/api/economies/'.$sienne['id'], $auth);
        self::assertResponseStatusCodeSame(404);
    }

    public function testListeFiltreeParPeriode(): void
    {
        $user = $this->creerUtilisateur();
        $auth = $this->authentifie($user);
        foreach (['2026-09-30', '2026-10-05', '2026-10-31'] as $date) {
            $this->client->request('POST', '/api/economies', $auth + ['json' => ['libelle' => 'Achat '.$date, 'montant' => '1', 'date' => $date]]);
        }

        $octobre = self::membres($this->client->request('GET', '/api/economies?du=2026-10-01&au=2026-10-31', $auth)->toArray());

        self::assertSame(['2026-10-31', '2026-10-05'], self::colonne($octobre, 'date'), 'Plus récentes en premier');
    }

    public function testBonPlanTikTokSansAbusDeType(): void
    {
        // Garde-fou : une économie peut être liée à n'importe quel type de bon plan.
        $user = $this->creerUtilisateur();
        $deal = $this->creerDeal('Astuce TikTok', null, DealType::TikTok, valeur: '1.00');

        $this->client->request('POST', '/api/economies', $this->authentifie($user) + ['json' => ['bonPlanId' => $deal->getId(), 'montant' => '2']]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['montant' => '2.00']);
    }
}

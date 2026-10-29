<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Saving;
use App\Enum\DealType;

final class SuiviEconomiesApiTest extends ApiTestCaseRadar
{
    public function testStatistiques(): void
    {
        $moi = $this->creerUtilisateur();
        $autre = $this->creerUtilisateur('autre@exemple.test');
        $deal = $this->creerDeal('Lessive', 5, marque: 'Skip', valeur: '8.99');
        $this->em()->persist(Saving::depuisDeal($moi, $deal, $this->aujourdhui));
        $this->em()->persist((new Saving($moi, 'Promo magasin', '1.01', $this->aujourdhui->modify('first day of previous month')))->setEnseigne('Lidl'));
        $this->em()->persist(new Saving($autre, 'Pas à moi', '100', $this->aujourdhui));
        $this->em()->flush();

        $s = $this->client->request('GET', '/api/economies/statistiques', $this->authentifie($moi))->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame('10.00', $s['total']);
        self::assertSame(2, $s['nombre']);
        self::assertSame('8.99', $s['totalMois']);
        self::assertSame('1.01', $s['totalMoisPrecedent']);
        self::assertCount(12, $s['parMois']);
        self::assertSame($this->aujourdhui->format('Y-m'), $s['parMois'][11]['mois']);
        self::assertSame('8.99', $s['parMois'][11]['total']);
        self::assertSame(['Skip', 'Lidl'], array_column($s['parEnseigne'], 'enseigne'));
        self::assertSame(['remboursement', 'saisie_libre'], array_column($s['parType'], 'type'));
    }

    public function testExportCsv(): void
    {
        $moi = $this->creerUtilisateur();
        $autre = $this->creerUtilisateur('autre@exemple.test');
        $deal = $this->creerDeal('Couches', 5, DealType::Shopmium, marque: 'Pampers');
        $this->em()->persist(Saving::depuisDeal($moi, $deal, new \DateTimeImmutable('2026-10-12'), '3.50'));
        $this->em()->persist(new Saving($moi, 'Ancien', '2', new \DateTimeImmutable('2026-01-05')));
        $this->em()->persist(new Saving($autre, 'Secret', '9', new \DateTimeImmutable('2026-10-12')));
        $this->em()->flush();

        $this->client->request('GET', '/api/economies/export.csv');
        self::assertResponseStatusCodeSame(401, 'Jeton obligatoire');

        $reponse = $this->client->request('GET', '/api/economies/export.csv', $this->authentifie($moi));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('attachment; filename=economies-radar-', (string) $reponse->getHeaders()['content-disposition'][0]);
        $csv = $reponse->getContent();
        self::assertSame(3, substr_count($csv, "\n"), 'En-tête + 2 lignes');
        self::assertStringContainsString('12/10/2026;Couches;Pampers;3,50;Shopmium;https://exemple.test/Couches', $csv);
        self::assertStringNotContainsString('Secret', $csv);

        $octobre = $this->client->request('GET', '/api/economies/export.csv?du=2026-10-01&au=2026-10-31', $this->authentifie($moi))->getContent();
        self::assertStringNotContainsString('Ancien', $octobre);
    }

    public function testLaRouteStatistiquesNeMasquePasLesFiches(): void
    {
        $moi = $this->creerUtilisateur();
        $creee = $this->client->request('POST', '/api/economies', $this->authentifie($moi) + ['json' => ['libelle' => 'X', 'montant' => '1']])->toArray();

        $this->client->request('GET', '/api/economies/'.$creee['id'], $this->authentifie($moi));
        self::assertResponseIsSuccessful();
    }
}

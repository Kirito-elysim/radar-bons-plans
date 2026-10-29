<?php

declare(strict_types=1);

namespace App\Tests\Unit\Economie;

use App\Economie\CalculateurEconomies;
use App\Economie\ExportCsv;
use App\Entity\Deal;
use App\Entity\Saving;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\DealType;
use App\Enum\SourceType;
use PHPUnit\Framework\TestCase;

final class CalculateurEconomiesTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User('moi@exemple.test');
    }

    private function economie(string $date, string $montant, ?string $enseigne = null, ?DealType $type = null, string $libelle = 'Achat'): Saving
    {
        $saving = null !== $type
            ? Saving::depuisDeal($this->user, new Deal(new Source('t', 'T', 'https://t.test', SourceType::Scraping), $type, $libelle, 'https://t.test/'.$libelle, str_repeat('a', 64)), new \DateTimeImmutable($date), $montant)
            : new Saving($this->user, $libelle, $montant, new \DateTimeImmutable($date));

        return $saving->setEnseigne($enseigne);
    }

    public function testTotauxParPeriode(): void
    {
        $stats = (new CalculateurEconomies())->calculer([
            $this->economie('2026-10-29', '1.10'),
            $this->economie('2026-10-23', '2.20'),
            $this->economie('2026-10-22', '0.05'),
            $this->economie('2026-09-30', '3.30'),
            $this->economie('2025-12-31', '10.00'),
        ], new \DateTimeImmutable('2026-10-29 07:40'));

        self::assertSame('16.65', $stats->total);
        self::assertSame(5, $stats->nombre);
        self::assertSame('3.35', $stats->totalMois);
        self::assertSame('3.30', $stats->totalMoisPrecedent);
        self::assertSame('6.65', $stats->totalAnnee);
        self::assertSame('3.30', $stats->totalSemaine, '7 derniers jours, aujourd\'hui inclus (du 23 au 29)');
        self::assertSame('5.55', $stats->moyenneMensuelle, '16,65 € sur 3 mois avec économies');
        self::assertSame('2025-12', $stats->meilleurMois);
    }

    public function testDouzeMoisGlissantsAvecMoisVides(): void
    {
        $stats = (new CalculateurEconomies())->calculer([
            $this->economie('2026-03-15', '4.00'),
            $this->economie('2025-10-31', '99.00'),
        ], new \DateTimeImmutable('2026-10-29'));

        self::assertCount(12, $stats->parMois);
        self::assertSame('2025-11', $stats->parMois[0]['mois'], 'Octobre 2025 est hors fenêtre');
        self::assertSame('2026-10', $stats->parMois[11]['mois']);
        self::assertSame(['mois' => '2026-03', 'total' => '4.00', 'nombre' => 1], $stats->parMois[4]);
        self::assertSame('0.00', $stats->parMois[5]['total']);
    }

    public function testFinDeMoisSansDecalage(): void
    {
        // Le 31 mars, « -1 mois » naïf donnerait le 3 mars : la fenêtre doit rester sur des débuts de mois.
        $stats = (new CalculateurEconomies())->calculer([$this->economie('2026-02-28', '1.00')], new \DateTimeImmutable('2026-03-31'));

        self::assertSame('2026-02', $stats->parMois[10]['mois']);
        self::assertSame('1.00', $stats->parMois[10]['total']);
        self::assertSame('1.00', $stats->totalMoisPrecedent);
    }

    public function testRegroupementParEnseigneEtType(): void
    {
        $stats = (new CalculateurEconomies())->calculer([
            $this->economie('2026-10-01', '2.00', 'Lidl', DealType::Bon),
            $this->economie('2026-10-02', '3.00', ' lidl ', DealType::Remboursement),
            $this->economie('2026-10-03', '4.50', 'Carrefour', DealType::Remboursement),
            $this->economie('2026-10-04', '0.50'),
        ], new \DateTimeImmutable('2026-10-29'));

        self::assertSame([
            ['enseigne' => 'Lidl', 'total' => '5.00', 'nombre' => 2],
            ['enseigne' => 'Carrefour', 'total' => '4.50', 'nombre' => 1],
            ['enseigne' => 'Sans enseigne', 'total' => '0.50', 'nombre' => 1],
        ], $stats->parEnseigne);
        self::assertSame([
            ['type' => 'remboursement', 'libelle' => 'Offre de remboursement', 'total' => '7.50', 'nombre' => 2],
            ['type' => 'bon', 'libelle' => 'Bon de réduction', 'total' => '2.00', 'nombre' => 1],
            ['type' => 'saisie_libre', 'libelle' => 'Saisie libre', 'total' => '0.50', 'nombre' => 1],
        ], $stats->parType);
    }

    public function testSansEconomie(): void
    {
        $stats = (new CalculateurEconomies())->calculer([], new \DateTimeImmutable('2026-10-29'));

        self::assertSame('0.00', $stats->total);
        self::assertSame('0.00', $stats->moyenneMensuelle);
        self::assertNull($stats->meilleurMois);
        self::assertSame([], $stats->parEnseigne);
    }

    public function testExportCsvPourExcel(): void
    {
        $csv = (new ExportCsv())->generer([
            $this->economie('2026-10-29', '8.99', 'Lidl', DealType::Remboursement, 'Lessive; "Skip"'),
            $this->economie('2026-10-01', '0.50', null, null, '=HYPERLINK("http://x")'),
        ]);

        self::assertStringStartsWith("\u{FEFF}Date;Libellé;Enseigne;\"Montant (€)\";\"Type de bon plan\";Lien\n", $csv);
        self::assertStringContainsString('29/10/2026;"Lessive; ""Skip""";Lidl;8,99;"Offre de remboursement";"https://t.test/Lessive; ""Skip"""', $csv);
        self::assertStringContainsString("01/10/2026;\"'=HYPERLINK(\"\"http://x\"\")\";;0,50;\"Saisie libre\";", $csv, 'Formule neutralisée');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Connector;

use App\Collecte\Connector\AntiCriseRemboursementConnector;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\FrenchDateParser;
use App\Collecte\Parsing\MontantParser;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class AntiCriseRemboursementConnectorTest extends TestCase
{
    /**
     * @return list<DealDTO>
     */
    private function deals(): array
    {
        $connecteur = new AntiCriseRemboursementConnector($this->createStub(PoliteHttpClient::class), new FrenchDateParser(), new MontantParser());

        return $connecteur->parser(Fixtures::lire('anti-crise/remboursements.html'));
    }

    /**
     * @param list<DealDTO> $deals
     */
    private static function parUrl(array $deals, string $fin): DealDTO
    {
        foreach ($deals as $deal) {
            if (str_ends_with($deal->url, $fin)) {
                return $deal;
            }
        }
        self::fail('Offre absente : '.$fin);
    }

    public function testPageReelleCaptureeLe29Octobre2026(): void
    {
        $deals = $this->deals();

        self::assertCount(144, $deals);
        $sousTypes = array_count_values(array_map(static fn (DealDTO $d): string => (string) $d->sousType?->value, $deals));
        self::assertSame(['rembourse_100' => 16, 'partiel' => 101, 'offre_essai' => 8, 'offre_specifique' => 13, 'bon_achat' => 6], $sousTypes);

        foreach ($deals as $deal) {
            self::assertSame(DealType::Remboursement, $deal->type);
            self::assertNotNull($deal->dateFin, $deal->url);
            self::assertNotNull($deal->marque, $deal->url);
            self::assertNotNull($deal->categorie, $deal->url);
            self::assertStringNotContainsStringIgnoringCase('Offre de remboursement', $deal->titre);
            self::assertSame($deal->url, $deal->idExterne);
        }
    }

    public function testOffre100PourcentRemboursee(): void
    {
        $deal = self::parUrl($this->deals(), '/offre-de-remboursement-candia-pack-de-lait-viva-satisfait-ou-100-rembourse/');

        self::assertSame('Pack de Lait Viva Satisfait ou 100% Remboursé', $deal->titre);
        self::assertSame('Candia', $deal->marque, 'Première lettre en majuscule');
        self::assertSame(RemboursementType::Rembourse100, $deal->sousType);
        self::assertSame('100.00', $deal->valeurPourcent);
        self::assertSame('Alimentation & boissons', $deal->categorie);
        self::assertSame('2027-02-14', $deal->dateFin?->format('Y-m-d'));
        self::assertStringStartsWith('https://media.anti-crise.fr/', (string) $deal->imageUrl);
    }

    public function testMontantMaximumDuRemboursementPartiel(): void
    {
        $deal = self::parUrl($this->deals(), '/offre-de-remboursement-hisense-jusqua-200e-rembourses-sur-electromenager-11460/');

        self::assertSame('Hisense', $deal->marque);
        self::assertSame(RemboursementType::Partiel, $deal->sousType);
        self::assertSame('200.00', $deal->valeurEuros);
        self::assertSame('Gros électroménager', $deal->categorie);
        self::assertSame('2026-11-30', $deal->dateFin?->format('Y-m-d'));
    }

    public function testStructureModifiee(): void
    {
        $html = str_replace('catalogue-item', 'carte-offre', Fixtures::lire('anti-crise/remboursements.html'));
        $connecteur = new AntiCriseRemboursementConnector($this->createStub(PoliteHttpClient::class), new FrenchDateParser(), new MontantParser());

        self::assertSame([], $connecteur->parser($html));
    }
}

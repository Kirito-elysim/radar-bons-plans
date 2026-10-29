<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Connector;

use App\Collecte\Connector\ShopmiumConnector;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\FrenchDateParser;
use App\Collecte\Parsing\MontantParser;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Tests\Support\Fixtures;
use App\Tests\Support\HttpPoliFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ShopmiumConnectorTest extends TestCase
{
    private const MAINTENANT = '2026-10-29 07:40:00';

    /**
     * @return array<string, DealDTO> par identifiant Shopmium
     */
    private function deals(): array
    {
        $connecteur = new ShopmiumConnector($this->createStub(PoliteHttpClient::class), new FrenchDateParser(), new MontantParser(), new MockClock(self::MAINTENANT));
        $deals = $connecteur->parser(Fixtures::lire('shopmium/offres.html'), new \DateTimeImmutable(self::MAINTENANT, new \DateTimeZone('Europe/Paris')));

        $parId = [];
        foreach ($deals as $deal) {
            $parId[(string) $deal->idExterne] = $deal;
        }

        return $parId;
    }

    /**
     * @param array<string, DealDTO> $deals
     */
    private static function offre(array $deals, string $id): DealDTO
    {
        foreach ($deals as $cle => $deal) {
            if ((string) $cle === $id) {
                return $deal;
            }
        }
        self::fail('Offre Shopmium absente : '.$id);
    }

    public function testOffresEnCoursFusionneesParIdentifiant(): void
    {
        $deals = $this->deals();

        self::assertCount(23, $deals, '66 tuiles réparties dans les onglets, 23 offres distinctes');
        foreach (['37107', '37236', '37899'] as $termine) {
            self::assertArrayNotHasKey($termine, $deals, 'Section « Offres terminées » ignorée');
        }
        foreach ($deals as $deal) {
            self::assertSame(DealType::Shopmium, $deal->type);
            self::assertTrue($deal->exclusifAppli);
            self::assertStringStartsWith('https://offers.shopmium.com/fr/n/', $deal->url);
        }
    }

    public function testDateDeFinEstimeeDepuisLeCompteARebours(): void
    {
        $deals = $this->deals();

        self::assertSame('2026-11-01', self::offre($deals, '37007')->dateFin?->format('Y-m-d'), '« 3j »');
        self::assertTrue(self::offre($deals, '37007')->dateFinEstimee);
        self::assertSame('2026-10-29', self::offre($deals, '37106')->dateFin?->format('Y-m-d'), '« 10h » à 7h40 : finit aujourd\'hui');
        self::assertNull(self::offre($deals, '37231')->dateFin);
        self::assertFalse(self::offre($deals, '37231')->dateFinEstimee);
    }

    public function testOngletsInterpretes(): void
    {
        $deals = $this->deals();

        self::assertSame('Lidl', self::offre($deals, '37231')->enseigne, 'Onglet « Uniquement chez Lidl »');
        self::assertSame('En supermarché ou Drive', self::offre($deals, '37007')->condition);
        self::assertSame(RemboursementType::Rembourse100, self::offre($deals, '37867')->sousType, '« entièrement remboursé »');
        self::assertNull(self::offre($deals, '37867')->categorie, 'Onglet de campagne « Défi Halloween » ignoré');
    }

    public function testFetchLitUniquementLaListe(): void
    {
        $factory = new HttpPoliFactory();
        $http = $factory->creer(['https://offers.shopmium.com/fr' => Fixtures::lire('shopmium/offres.html')], "User-Agent: *\nDisallow: /offers/");

        $deals = (new ShopmiumConnector($http, new FrenchDateParser(), new MontantParser(), new MockClock(self::MAINTENANT)))->fetch();

        self::assertCount(23, $deals);
        self::assertSame(['https://offers.shopmium.com/fr'], $factory->requetes);
    }
}

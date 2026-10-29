<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Connector;

use App\Collecte\Connector\AntiCriseCatalogueConnector;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\FrenchDateParser;
use App\Enum\DealType;
use App\Tests\Support\Fixtures;
use PHPUnit\Framework\TestCase;

final class AntiCriseCatalogueConnectorTest extends TestCase
{
    private AntiCriseCatalogueConnector $connecteur;
    private \DateTimeImmutable $reference;

    protected function setUp(): void
    {
        // Le client HTTP n'est pas utilisé : on appelle parser() directement sur les fixtures.
        $this->connecteur = new AntiCriseCatalogueConnector($this->createStub(PoliteHttpClient::class), new FrenchDateParser());
        $this->reference = new \DateTimeImmutable('2026-10-29', new \DateTimeZone('Europe/Paris'));
    }

    /**
     * @return list<DealDTO>
     */
    private function parserFixture(string $fichier): array
    {
        return $this->connecteur->parser(Fixtures::lire('anti-crise/'.$fichier), $this->reference);
    }

    /**
     * @param list<DealDTO> $deals
     */
    private static function parUrl(array $deals, string $url): DealDTO
    {
        foreach ($deals as $deal) {
            if ($deal->url === $url) {
                return $deal;
            }
        }
        self::fail('Catalogue absent : '.$url);
    }

    public function testPageReelleCaptureeLe29Octobre2026(): void
    {
        $deals = $this->parserFixture('catalogues.html');

        self::assertCount(361, $deals);
        $urls = array_map(static fn (DealDTO $d): string => $d->url, $deals);
        self::assertSame($urls, array_values(array_unique($urls)), 'Aucun doublon d\'URL');

        foreach ($deals as $deal) {
            self::assertSame(DealType::Catalogue, $deal->type);
            self::assertMatchesRegularExpression('#^https://anti-crise\.fr/catalogue/catalogue-[a-z0-9-]+/$#', $deal->url);
            self::assertNotNull($deal->enseigne, $deal->url);
            self::assertNotNull($deal->dateDebut, $deal->url);
            self::assertNotNull($deal->dateFin, $deal->url);
            self::assertGreaterThanOrEqual($deal->dateDebut, $deal->dateFin, $deal->url);
            self::assertStringStartsWith('https://media.anti-crise.fr/', (string) $deal->imageUrl);
        }
    }

    public function testToutesLesEnseignesPrioritairesSontPresentes(): void
    {
        $enseignes = array_count_values(array_map(static fn (DealDTO $d): string => (string) $d->enseigne, $this->parserFixture('catalogues.html')));

        foreach (AntiCriseCatalogueConnector::ENSEIGNES_PRIORITAIRES as $prioritaire) {
            self::assertArrayHasKey($prioritaire, $enseignes, $prioritaire.' manquant');
        }
        self::assertSame(26, $enseignes['Intermarché'], 'Nom accentué lu dans le <h2>');
        self::assertSame(2, $enseignes['Action'], 'Action ne figure que dans le carrousel');
    }

    public function testCatalogueProvenciaSudEst(): void
    {
        $deal = self::parUrl($this->parserFixture('catalogues.html'), 'https://anti-crise.fr/catalogue/catalogue-carrefour-du-04-au-15-novembre-2026-provencia/');

        self::assertSame('Catalogue Carrefour — Provencia', $deal->titre);
        self::assertSame('Carrefour', $deal->enseigne);
        self::assertSame('Provencia', $deal->descriptionCourte);
        self::assertSame('Provencia (Sud-Est)', $deal->region);
        self::assertSame('2026-11-04', $deal->dateDebut?->format('Y-m-d'));
        self::assertSame('2026-11-15', $deal->dateFin?->format('Y-m-d'));
    }

    public function testCatalogueIssuDuCarrousel(): void
    {
        $deal = self::parUrl($this->parserFixture('catalogues.html'), 'https://anti-crise.fr/catalogue/catalogue-action-du-28-octobre-au-03-novembre-2026/');

        self::assertSame('Catalogue Action', $deal->titre);
        self::assertSame('2026-10-28', $deal->dateDebut?->format('Y-m-d'));
        self::assertSame('2026-11-03', $deal->dateFin?->format('Y-m-d'));
        self::assertNull($deal->region);
    }

    public function testCasLimites(): void
    {
        $deals = $this->parserFixture('catalogues-cas-limites.html');
        $urls = array_map(static fn (DealDTO $d): string => $d->url, $deals);

        self::assertEqualsCanonicalizing([
            'https://anti-crise.fr/catalogue/catalogue-action-du-28-decembre-au-03-janvier-2027/',
            'https://anti-crise.fr/catalogue/catalogue-carrefour-du-04-au-15-novembre-2026-provencia/',
            'https://anti-crise.fr/catalogue/catalogue-lidl-du-10-au-23-novembre-2026/',
            'https://anti-crise.fr/catalogue/catalogue-carrefour-du-mois/',
        ], $urls);

        // Bloc enseigne prioritaire sur le carrousel (fragment #page3 retiré).
        $provencia = self::parUrl($deals, 'https://anti-crise.fr/catalogue/catalogue-carrefour-du-04-au-15-novembre-2026-provencia/');
        self::assertSame('Provencia', $provencia->descriptionCourte);
        self::assertSame('Carrefour', $provencia->enseigne, 'Le lien « haut de page » du <h2> ne pollue pas le nom');

        // Changement d'année dans le carrousel.
        $action = self::parUrl($deals, 'https://anti-crise.fr/catalogue/catalogue-action-du-28-decembre-au-03-janvier-2027/');
        self::assertSame('2026-12-28', $action->dateDebut?->format('Y-m-d'));
        self::assertSame('2027-01-03', $action->dateFin?->format('Y-m-d'));

        // « Notre sélection » : enseigne déduite de l'URL, image relative ignorée.
        $lidl = self::parUrl($deals, 'https://anti-crise.fr/catalogue/catalogue-lidl-du-10-au-23-novembre-2026/');
        self::assertSame('Lidl', $lidl->enseigne);
        self::assertNull($lidl->imageUrl);
        self::assertSame('2026-11-23', $lidl->dateFin?->format('Y-m-d'));

        // Dates illisibles : catalogue conservé sans dates.
        $sansDate = self::parUrl($deals, 'https://anti-crise.fr/catalogue/catalogue-carrefour-du-mois/');
        self::assertNull($sansDate->dateFin);
        self::assertSame('Catalogue Carrefour', $sansDate->titre);
    }

    public function testStructureModifieeRenvoieZeroResultat(): void
    {
        self::assertSame([], $this->parserFixture('catalogues-structure-modifiee.html'));
    }

    public function testPageIllisible(): void
    {
        $this->expectException(CollecteException::class);

        $this->connecteur->parser('');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Connector;

use App\Collecte\Connector\AntiCriseCatalogueConnector;
use App\Collecte\Connector\AntiCriseRemboursementConnector;
use App\Collecte\Connector\EnvieDeBienMangerConnector;
use App\Collecte\Connector\RadinsConnector;
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

/**
 * Connecteurs sur des pages synthétiques reproduisant la structure HTML des sites (contenus fictifs).
 * Toujours exécutés, y compris en CI ; les pages réelles (non versionnées) sont testées en local.
 */
final class ConnecteursSynthetiquesTest extends TestCase
{
    /**
     * @param list<DealDTO> $deals
     *
     * @return array<string, DealDTO> par titre
     */
    private static function parTitre(array $deals): array
    {
        $resultat = [];
        foreach ($deals as $d) {
            $resultat[$d->titre] = $d;
        }

        return $resultat;
    }

    /**
     * @param array<string, DealDTO> $deals
     */
    private static function deal(array $deals, string $titre): DealDTO
    {
        return $deals[$titre] ?? self::fail('Bon plan absent : '.$titre.' (présents : '.implode(', ', array_keys($deals)).')');
    }

    public function testCatalogues(): void
    {
        $connecteur = new AntiCriseCatalogueConnector($this->createStub(PoliteHttpClient::class), new FrenchDateParser());
        $deals = self::parTitre($connecteur->parser(Fixtures::lire('anti-crise/catalogues-synthetique.html'), new \DateTimeImmutable('2026-10-29')));

        self::assertEqualsCanonicalizing([
            'Catalogue Action',
            'Catalogue Carrefour — Provencia',
            'Catalogue Carrefour — Exclus Nord',
            'Catalogue Carrefour — Promos',
            'Catalogue Intermarché — Version Super',
            'Catalogue Leclerc Local — Estissac',
            'Catalogue Lidl',
        ], array_keys($deals), 'Blocs, sélection et carrousel fusionnés ; doublon du carrousel ignoré');

        $provencia = self::deal($deals, 'Catalogue Carrefour — Provencia');
        self::assertSame('paca', $provencia->region);
        self::assertSame('2026-11-04', $provencia->dateDebut?->format('Y-m-d'));
        self::assertSame('2026-11-15', $provencia->dateFin?->format('Y-m-d'));
        self::assertSame('nord', self::deal($deals, 'Catalogue Carrefour — Exclus Nord')->region);
        self::assertNull(self::deal($deals, 'Catalogue Carrefour — Promos')->region, 'National');
        self::assertNull(self::deal($deals, 'Catalogue Leclerc Local — Estissac')->region, 'Ville, pas « Est »');
        self::assertSame('Intermarché', self::deal($deals, 'Catalogue Intermarché — Version Super')->enseigne);
        self::assertSame('2026-11-03', self::deal($deals, 'Catalogue Action')->dateFin?->format('Y-m-d'));
        self::assertSame('Lidl', self::deal($deals, 'Catalogue Lidl')->enseigne, 'Enseigne déduite de l\'URL');
    }

    public function testRemboursements(): void
    {
        $connecteur = new AntiCriseRemboursementConnector($this->createStub(PoliteHttpClient::class), new FrenchDateParser(), new MontantParser());
        $deals = $connecteur->parser(Fixtures::lire('anti-crise/remboursements-synthetique.html'));

        self::assertSame(
            [RemboursementType::Rembourse100, RemboursementType::Partiel, RemboursementType::BonAchat, RemboursementType::OffreEssai, RemboursementType::OffreSpecifique],
            array_map(static fn (DealDTO $d): ?RemboursementType => $d->sousType, $deals),
        );
        [$lait, $tv, $cafe, , $jardin] = $deals;
        self::assertSame('Lait demi-écrémé Satisfait ou 100% Remboursé', $lait->titre);
        self::assertSame('Laitier', $lait->marque);
        self::assertSame('100.00', $lait->valeurPourcent);
        self::assertSame('Alimentation & boissons', $lait->categorie);
        self::assertSame('2027-02-14', $lait->dateFin?->format('Y-m-d'));
        self::assertSame('200.00', $tv->valeurEuros);
        self::assertSame('Gros électroménager', $tv->categorie);
        self::assertSame("dès 30 € d'achat", $cafe->condition);
        self::assertSame('Nouvelle categorie', $jardin->categorie, 'Icône inconnue : libellé déduit du nom de fichier');
    }

    public function testShopmium(): void
    {
        $connecteur = new ShopmiumConnector($this->createStub(PoliteHttpClient::class), new FrenchDateParser(), new MontantParser(), new MockClock());
        $deals = self::parTitre($connecteur->parser(Fixtures::lire('shopmium/offres-synthetique.html'), new \DateTimeImmutable('2026-10-29 07:40', new \DateTimeZone('Europe/Paris'))));

        self::assertEqualsCanonicalizing(['Biscuits Exemple', 'Pétillant Exemple', 'Fromages Exemple', 'Défi Exemple'], array_keys($deals), 'Offres terminées exclues, doublons fusionnés');
        $biscuits = self::deal($deals, 'Biscuits Exemple');
        self::assertSame('Épicerie sucrée', $biscuits->categorie);
        self::assertSame('2026-11-01', $biscuits->dateFin?->format('Y-m-d'));
        self::assertTrue($biscuits->dateFinEstimee);
        self::assertTrue($biscuits->exclusifAppli);
        self::assertSame('101', $biscuits->idExterne);
        self::assertSame('Lidl', self::deal($deals, 'Pétillant Exemple')->enseigne);
        self::assertSame('En supermarché ou Drive', self::deal($deals, 'Fromages Exemple')->condition);
        $defi = self::deal($deals, 'Défi Exemple');
        self::assertSame(RemboursementType::Rembourse100, $defi->sousType);
        self::assertSame('2026-10-29', $defi->dateFin?->format('Y-m-d'));
        self::assertNull($defi->categorie, 'Onglet de campagne ignoré');
    }

    public function testEnvieDeBienMangerPagination(): void
    {
        $liste = 'https://www.enviedebienmanger.fr/bons-de-reduction';
        $factory = new HttpPoliFactory();
        $http = $factory->creer([
            $liste => Fixtures::lire('envie-de-bien-manger/bons-synthetique-page0.html'),
            $liste.'?page=%2C1' => Fixtures::lire('envie-de-bien-manger/bons-synthetique-page1.html'),
            $liste.'?page=%2C2' => Fixtures::lire('envie-de-bien-manger/bons-synthetique-page2.html'),
        ]);

        $deals = self::parTitre((new EnvieDeBienMangerConnector($http, new MontantParser()))->fetch());

        self::assertCount(3, $factory->requetes, 'Arrêt à la première page vide');
        self::assertEqualsCanonicalizing(['Gamme Yaourt Exemple', 'Fromage Exemple 200g', 'Café moulu Exemple'], array_keys($deals));
        $yaourt = self::deal($deals, 'Gamme Yaourt Exemple');
        self::assertSame(DealType::Bon, $yaourt->type);
        self::assertSame('Yaourt Exemple', $yaourt->marque);
        self::assertSame('0.50', $yaourt->valeurEuros);
        self::assertNull($yaourt->imageUrl, '/sites/ interdit par robots.txt');
        self::assertSame('pour 2 produits achetés', self::deal($deals, 'Fromage Exemple 200g')->condition);
        self::assertSame('20.00', self::deal($deals, 'Café moulu Exemple')->valeurPourcent);
    }

    public function testRadins(): void
    {
        $factory = new HttpPoliFactory();
        $http = $factory->creer([
            'https://www.radins.com/code-promo/' => Fixtures::lire('radins/code-promo-synthetique.html'),
            'https://www.radins.com/' => Fixtures::lire('radins/accueil-synthetique.html'),
        ]);

        $liste = (new RadinsConnector($http, new MontantParser()))->fetch();
        $deals = [];
        foreach ($liste as $d) {
            $deals[(string) $d->enseigne] = $d;
        }

        self::assertEqualsCanonicalizing(['Mode Exemple', 'Fibre Exemple', 'Sport Exemple', 'Voyage Exemple'], array_keys($deals), 'Expirée exclue, doublon fusionné');
        self::assertSame('20.00', $deals['Mode Exemple']->valeurPourcent);
        self::assertSame('dès 69 €', $deals['Mode Exemple']->condition);
        self::assertSame('2026-11-02', $deals['Mode Exemple']->dateFin?->format('Y-m-d'));
        self::assertSame('https://www.radins.com/code-promo/mode-exemple/', $deals['Mode Exemple']->url);
        self::assertSame('Bon plan', $deals['Fibre Exemple']->categorie);
        self::assertSame('49.00', $deals['Fibre Exemple']->valeurEuros);
        self::assertSame('Code promo', $deals['Sport Exemple']->categorie, '« unique » = code personnel');
        self::assertStringNotContainsString('NE-PAS-STOCKER', serialize($liste), 'Le code n\'est jamais stocké');
    }
}

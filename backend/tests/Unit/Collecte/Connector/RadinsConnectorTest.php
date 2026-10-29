<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Connector;

use App\Collecte\Connector\RadinsConnector;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Parsing\MontantParser;
use App\Enum\DealType;
use App\Tests\Support\Fixtures;
use App\Tests\Support\HttpPoliFactory;
use PHPUnit\Framework\TestCase;

final class RadinsConnectorTest extends TestCase
{
    private function connecteur(?HttpPoliFactory $factory = null, string $robots = "User-agent: *\nAllow: /"): RadinsConnector
    {
        $http = ($factory ?? new HttpPoliFactory())->creer([
            'https://www.radins.com/code-promo/' => Fixtures::lire('radins/code-promo.html'),
            'https://www.radins.com/' => Fixtures::lire('radins/accueil.html'),
        ], $robots);

        return new RadinsConnector($http, new MontantParser());
    }

    /**
     * @param list<DealDTO> $deals
     */
    private static function parEnseigne(array $deals, string $enseigne): DealDTO
    {
        foreach ($deals as $deal) {
            if ($deal->enseigne === $enseigne) {
                return $deal;
            }
        }
        self::fail('Marchand absent : '.$enseigne);
    }

    public function testFusionDesDeuxPages(): void
    {
        $deals = $this->connecteur()->fetch();

        self::assertCount(44, $deals, '27 + 24 offres, 7 en commun');
        foreach ($deals as $deal) {
            self::assertSame(DealType::CodePromo, $deal->type);
            self::assertMatchesRegularExpression('#^https://www\.radins\.com/code-promo/[a-z0-9-]+/$#', $deal->url, 'Jamais de lien /clickout/');
            self::assertNotNull($deal->enseigne);
        }
    }

    public function testCodePromoEnPourcentageAvecCondition(): void
    {
        $deals = $this->connecteur()->parser(Fixtures::lire('radins/code-promo.html'));
        $shein = self::parEnseigne($deals, 'SHEIN');

        self::assertSame('Code promo', $shein->categorie);
        self::assertSame('20.00', $shein->valeurPourcent);
        self::assertNull($shein->valeurEuros);
        self::assertSame('dès 69 €', $shein->condition);
        self::assertSame('2026-11-02', $shein->dateFin?->format('Y-m-d'), '2026-11-02T22:59:59Z = 2026-11-02 23:59 à Paris');
    }

    public function testBonPlanEnEurosSansDateDeFin(): void
    {
        $orange = self::parEnseigne($this->connecteur()->parser(Fixtures::lire('radins/code-promo.html')), 'Orange Internet');

        self::assertSame('Bon plan', $orange->categorie);
        self::assertSame('49.00', $orange->valeurEuros);
        self::assertNull($orange->dateFin);
    }

    public function testLeCodeLuiMemeNEstJamaisStocke(): void
    {
        $deals = $this->connecteur()->parser(Fixtures::lire('radins/code-promo.html'));

        self::assertStringNotContainsString('HELLO15', serialize($deals));
    }

    public function testOffresExpireesIgnorees(): void
    {
        $json = json_encode(['props' => ['pageProps' => ['widgets' => [['widget_data' => ['vouchers' => [
            ['idPool' => 'a', 'title' => '10 % chez X', 'type' => 'code', 'isExpired' => true, 'retailer' => ['name' => 'X', 'rlpUrl' => 'code-promo/x/'], 'caption' => ['first' => '10%']],
            ['idPool' => 'b', 'title' => '5 € chez Y', 'type' => 'deal', 'isExpired' => false, 'retailer' => ['name' => 'Y', 'rlpUrl' => 'code-promo/y/'], 'caption' => ['first' => '5€'], 'endTime' => ''],
            ['title' => 'Sans identifiant'],
        ]]]]]]]);
        $deals = $this->connecteur()->parser('<html><body><script id="__NEXT_DATA__" type="application/json">'.$json.'</script></body></html>');

        self::assertCount(1, $deals);
        self::assertSame('Y', $deals[0]->enseigne);
        self::assertSame('5.00', $deals[0]->valeurEuros);
    }

    public function testStructureModifiee(): void
    {
        $this->expectException(CollecteException::class);
        $this->expectExceptionMessage('__NEXT_DATA__ absent');

        $this->connecteur()->parser('<html><body><div class="voucher">10 %</div></body></html>');
    }

    public function testPageInterditeSautee(): void
    {
        $factory = new HttpPoliFactory();
        $deals = $this->connecteur($factory, "User-agent: *\nDisallow: /code-promo/$")->fetch();

        self::assertCount(24, $deals, 'Seule la page d\'accueil est lue');
        self::assertSame(['https://www.radins.com/'], $factory->requetes);
    }
}

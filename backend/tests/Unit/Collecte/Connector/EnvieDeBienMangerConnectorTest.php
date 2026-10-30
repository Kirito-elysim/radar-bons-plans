<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Connector;

use App\Collecte\Connector\EnvieDeBienMangerConnector;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Parsing\MontantParser;
use App\Enum\DealType;
use App\Tests\Support\Fixtures;
use App\Tests\Support\HttpPoliFactory;
use PHPUnit\Framework\TestCase;

final class EnvieDeBienMangerConnectorTest extends TestCase
{
    private const LISTE = 'https://www.enviedebienmanger.fr/bons-de-reduction';

    /**
     * @return array<string, string>
     */
    private static function pages(): array
    {
        $pages = [self::LISTE => Fixtures::reelle('envie-de-bien-manger/bons-page0.html')];
        foreach ([1, 2, 3, 4] as $n) {
            $pages[self::LISTE.'?page=%2C'.$n] = Fixtures::reelle(\sprintf('envie-de-bien-manger/bons-page%d.html', $n));
        }

        return $pages;
    }

    public function testParcourtLesPagesJusquALaPageVide(): void
    {
        $factory = new HttpPoliFactory();
        $deals = (new EnvieDeBienMangerConnector($factory->creer(self::pages()), new MontantParser()))->fetch();

        self::assertCount(36, $deals, '« Quantité 36 » affiché sur le site');
        self::assertCount(5, $factory->requetes, 'Arrêt après la première page vide');
        $ids = array_map(static fn (DealDTO $d): ?string => $d->idExterne, $deals);
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    public function testContenuDUnBon(): void
    {
        $deals = (new EnvieDeBienMangerConnector((new HttpPoliFactory())->creer([]), new MontantParser()))
            ->parser(Fixtures::reelle('envie-de-bien-manger/bons-page0.html'));

        self::assertCount(9, $deals);
        $kefir = $deals[0];
        self::assertSame('240893', $kefir->idExterne);
        self::assertSame(DealType::Bon, $kefir->type);
        self::assertSame('Gamme I Love Kéfir', $kefir->titre);
        self::assertSame('I Love Kéfir', $kefir->marque);
        self::assertSame('0.50', $kefir->valeurEuros);
        self::assertSame(self::LISTE, $kefir->url);
        self::assertNull($kefir->dateFin, 'Valable 30 jours après impression');
        self::assertNull($kefir->imageUrl, 'Images sous /sites/, interdit par robots.txt');
        self::assertStringContainsString('réduction immédiate', (string) $kefir->descriptionCourte);
    }

    public function testPaginationInterditeArreteProprement(): void
    {
        $robots = "User-agent: *\nDisallow: /bons-de-reduction?page=*";
        $factory = new HttpPoliFactory();

        $deals = (new EnvieDeBienMangerConnector($factory->creer(self::pages(), $robots), new MontantParser()))->fetch();

        self::assertCount(9, $deals, 'Seule la première page est lue');
        self::assertSame([self::LISTE], $factory->requetes);
    }
}

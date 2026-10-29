<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte;

use App\Collecte\DealHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DealHasherTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function urlsProvider(): iterable
    {
        yield 'fragment de page retiré' => [
            'https://anti-crise.fr/catalogue/catalogue-intermarche-du-17-au-29-mars-2026-version-super/#page24',
            'https://anti-crise.fr/catalogue/catalogue-intermarche-du-17-au-29-mars-2026-version-super',
        ];
        yield 'hôte en minuscules' => ['HTTPS://WWW.Radins.com/code-promo/', 'https://www.radins.com/code-promo'];
        yield 'paramètres de suivi retirés' => [
            'https://offers.shopmium.com/fr?utm_source=x&utm_medium=y&fbclid=abc',
            'https://offers.shopmium.com/fr',
        ];
        yield 'paramètres utiles conservés et triés' => [
            'https://anti-crise.fr/les-offres-de-remboursement/?remboursement-type=100-rembourse&page=2',
            'https://anti-crise.fr/les-offres-de-remboursement?page=2&remboursement-type=100-rembourse',
        ];
        yield 'URL invalide renvoyée telle quelle' => ['  pas une url ', 'pas une url'];
    }

    #[DataProvider('urlsProvider')]
    public function testNormaliserUrl(string $brute, string $attendue): void
    {
        self::assertSame($attendue, DealHasher::normaliserUrl($brute));
    }

    public function testNormaliserTitreIgnoreCasseAccentsEtPonctuation(): void
    {
        self::assertSame(
            'catalogue intermarche version super',
            DealHasher::normaliserTitre('  Catalogue Intermarché — « Version Super » '),
        );
        self::assertSame('lessive 100 % remboursee', DealHasher::normaliserTitre('Lessive 100 % remboursée !'));
    }

    public function testDeuxVariantesDuMemeDealOntLeMemeHash(): void
    {
        $hasher = new DealHasher();

        self::assertSame(
            $hasher->hash('anti_crise_catalogue', 'https://anti-crise.fr/catalogue/x/#page24', 'Intermarché Version Super'),
            $hasher->hash('anti_crise_catalogue', 'https://ANTI-CRISE.fr/catalogue/x?utm_source=fb', 'intermarche version super'),
        );
    }

    public function testLaSourceEtLeTitreDistinguentLesDeals(): void
    {
        $hasher = new DealHasher();
        $base = $hasher->hash('radins', 'https://www.radins.com/code-promo/', 'Code -10 %');

        self::assertNotSame($base, $hasher->hash('shopmium', 'https://www.radins.com/code-promo/', 'Code -10 %'));
        self::assertNotSame($base, $hasher->hash('radins', 'https://www.radins.com/code-promo/', 'Code -20 %'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $base);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Http;

use App\Collecte\Http\RobotsTxtRegles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cas tirés des robots.txt réels des sources (relevés le 29/10/2026).
 */
final class RobotsTxtReglesTest extends TestCase
{
    private const ANTI_CRISE = "User-agent: *\nDisallow: */feed\nDisallow: */feed/";

    private const SHOPMIUM = "User-Agent: *\nDisallow: /activeadmin/\nDisallow: /mobileapp/\nDisallow: /offers/\nDisallow: /web/signin";

    private const RADINS = "User-agent: *\nAllow: /\nAllow: /api/getCoupons\n\nDisallow: /api/*\nDisallow: /clickout/*\nDisallow: /*?preview\nDisallow: /search\n\nUser-agent: omgilibot\nDisallow: /";

    private const TIKTOK = "User-agent: GPTBot\nUser-agent: ClaudeBot\nDisallow: /\n\nUser-agent: *\nAllow: /foryou\nDisallow: /search?\nDisallow: /embed/@";

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function casProvider(): iterable
    {
        yield 'anti-crise : liste des catalogues' => [self::ANTI_CRISE, '/catalogue/', true];
        yield 'anti-crise : remboursements filtrés' => [self::ANTI_CRISE, '/les-offres-de-remboursement/?remboursement-type=100-rembourse', true];
        yield 'anti-crise : flux RSS interdit' => [self::ANTI_CRISE, '/catalogue/feed/', false];
        yield 'shopmium : liste autorisée' => [self::SHOPMIUM, '/fr', true];
        yield 'shopmium : fiche /offers/ interdite' => [self::SHOPMIUM, '/offers/12345', false];
        yield 'radins : codes promo' => [self::RADINS, '/code-promo/', true];
        yield 'radins : API interdite' => [self::RADINS, '/api/coupons', false];
        yield 'radins : Allow plus précis que Disallow /api/*' => [self::RADINS, '/api/getCoupons', true];
        yield 'radins : clickout interdit' => [self::RADINS, '/clickout/123', false];
        yield 'radins : prévisualisation interdite' => [self::RADINS, '/code-promo/fnac?preview', false];
        yield 'tiktok : recherche interdite' => [self::TIKTOK, '/search?q=bons+plans', false];
        yield 'tiktok : notre agent relève du groupe *' => [self::TIKTOK, '/foryou', true];
    }

    #[DataProvider('casProvider')]
    public function testEstAutorise(string $robots, string $chemin, bool $attendu): void
    {
        self::assertSame($attendu, RobotsTxtRegles::parse($robots)->estAutorise($chemin, 'RadarBonsPlans'));
    }

    public function testGroupeSpecifiquePrioritaireSurEtoile(): void
    {
        $regles = RobotsTxtRegles::parse("User-agent: *\nDisallow:\n\nUser-agent: RadarBonsPlans\nDisallow: /prive/");

        self::assertFalse($regles->estAutorise('/prive/page', 'RadarBonsPlans'));
        self::assertTrue($regles->estAutorise('/prive/page', 'AutreRobot'));
    }

    public function testAncrageDollarEtCommentaires(): void
    {
        $regles = RobotsTxtRegles::parse("# commentaire\nUser-agent: * # tous\nDisallow: /*.pdf$\n");

        self::assertFalse($regles->estAutorise('/catalogue.pdf', 'RadarBonsPlans'));
        self::assertTrue($regles->estAutorise('/catalogue.pdf?v=2', 'RadarBonsPlans'));
    }

    public function testCasLimites(): void
    {
        self::assertTrue(RobotsTxtRegles::parse('')->estAutorise('/', 'RadarBonsPlans'));
        self::assertTrue(RobotsTxtRegles::toutAutoriser()->estAutorise('/x', 'RadarBonsPlans'));
        self::assertFalse(RobotsTxtRegles::toutInterdire()->estAutorise('/', 'RadarBonsPlans'));
    }
}

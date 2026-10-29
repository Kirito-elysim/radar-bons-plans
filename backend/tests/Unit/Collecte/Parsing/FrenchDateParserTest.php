<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Parsing;

use App\Collecte\Parsing\FrenchDateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchDateParserTest extends TestCase
{
    private FrenchDateParser $parser;
    private \DateTimeImmutable $reference;

    protected function setUp(): void
    {
        $this->parser = new FrenchDateParser();
        $this->reference = new \DateTimeImmutable('2026-10-29 07:40', new \DateTimeZone('Europe/Paris'));
    }

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function periodesProvider(): iterable
    {
        yield 'même mois' => ['Du 03 au 15 novembre 2026', '2026-11-03', '2026-11-15'];
        yield 'deux mois' => ['Du 20 octobre au 02 novembre 2026', '2026-10-20', '2026-11-02'];
        yield 'à cheval sur deux années' => ['du 28 décembre au 3 janvier 2027', '2026-12-28', '2027-01-03'];
        yield 'années explicites' => ['du 28 décembre 2026 au 3 janvier 2027', '2026-12-28', '2027-01-03'];
        yield '1er et sans accent' => ['Du 1er au 15 fevrier 2027', '2027-02-01', '2027-02-15'];
        yield 'année absente' => ['du 3 au 9 novembre', '2026-11-03', '2026-11-09'];
        yield 'format numérique' => ["27/10/2026                  -\n   08/11/2026", '2026-10-27', '2026-11-08'];
        yield 'numérique avec au' => ['du 27/10/26 au 08/11/26', '2026-10-27', '2026-11-08'];
        yield 'jusqu\'au en lettres' => ["Valable jusqu'au 15 novembre 2026", null, '2026-11-15'];
        yield 'jusqu’au apostrophe typographique' => ['Jusqu’au 30/11/2026', null, '2026-11-30'];
        yield 'abrégé' => ['Du 3 au 9 nov. 2026', '2026-11-03', '2026-11-09'];
        yield 'espaces insécables' => ["Du\u{00A0}03 au\u{202F}15 novembre 2026", '2026-11-03', '2026-11-15'];
    }

    #[DataProvider('periodesProvider')]
    public function testParsePeriode(string $texte, ?string $debut, ?string $fin): void
    {
        $periode = $this->parser->parsePeriode($texte, $this->reference);

        self::assertNotNull($periode);
        self::assertSame($debut, $periode['debut']?->format('Y-m-d'));
        self::assertSame($fin, $periode['fin']->format('Y-m-d'));
        self::assertSame('00:00:00 Europe/Paris', $periode['fin']->format('H:i:s e'));
    }

    public function testPeriodesIllisibles(): void
    {
        self::assertNull($this->parser->parsePeriode('Nouveau catalogue'));
        self::assertNull($this->parser->parsePeriode('du 31 au 32 novembre 2026'), 'Date impossible');
        self::assertNull($this->parser->parsePeriode(''));
    }

    public function testParseDateSansAnneeBasculeSurLAnneeSuivante(): void
    {
        self::assertSame('2027-01-05', $this->parser->parseDate('5 janvier', $this->reference)?->format('Y-m-d'));
        self::assertSame('2026-10-01', $this->parser->parseDate('1er octobre', $this->reference)?->format('Y-m-d'), 'Date récente : même année');
        self::assertSame('2026-11-15', $this->parser->parseDate('2026-11-15')?->format('Y-m-d'));
        self::assertNull($this->parser->parseDate('bientôt'));
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function dureesProvider(): iterable
    {
        yield 'jours compacts (Shopmium)' => ['3j', '2026-10-29 07:40', '2026-11-01'];
        yield 'jours en majuscules' => ['7J', '2026-10-29 07:40', '2026-11-05'];
        yield 'jours en toutes lettres' => ['Plus que 2 jours', '2026-10-29 07:40', '2026-10-31'];
        yield 'heures le jour même' => ['5h', '2026-10-29 07:40', '2026-10-29'];
        yield 'heures passant minuit' => ['5h restantes', '2026-10-29 22:00', '2026-10-30'];
        yield 'minutes' => ['45 min', '2026-10-29 23:30', '2026-10-30'];
        yield 'illisible' => ['bientôt', '2026-10-29 07:40', null];
    }

    #[DataProvider('dureesProvider')]
    public function testParseDureeRestante(string $texte, string $maintenant, ?string $attendu): void
    {
        $date = $this->parser->parseDureeRestante($texte, new \DateTimeImmutable($maintenant, new \DateTimeZone('Europe/Paris')));

        self::assertSame($attendu, $date?->format('Y-m-d'));
    }
}

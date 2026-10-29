<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Parsing;

use App\Collecte\Parsing\MontantParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MontantParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function eurosProvider(): iterable
    {
        yield 'bon Envie de Bien Manger' => ['-0,50 €', '0.50'];
        yield 'collé' => ['Jusqu’à 200€ Remboursés', '200.00'];
        yield 'centimes' => ['19,99€', '19.99'];
        yield 'milliers avec espace' => ['jusqu\'à 1 500 € remboursés', '1500.00'];
        yield 'milliers avec point' => ['1.234,50 €', '1234.50'];
        yield 'notation 2€50' => ['2€50 de réduction', '2.50'];
        yield 'en toutes lettres' => ['10 euros offerts', '10.00'];
        yield 'symbole devant' => ['€5 offerts', '5.00'];
        yield 'entité HTML' => ['Jusqu&rsquo;à 30&nbsp;€', '30.00'];
        yield 'sans montant' => ['100% remboursé', null];
    }

    #[DataProvider('eurosProvider')]
    public function testEuros(string $texte, ?string $attendu): void
    {
        self::assertSame($attendu, (new MontantParser())->euros($texte));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function pourcentProvider(): iterable
    {
        yield '100 % remboursé' => ['Pack de Lait Viva Satisfait ou 100% Remboursé', '100.00'];
        yield 'espace' => ['-20 % sur tout', '20.00'];
        yield 'décimal' => ['cashback 2,5 %', '2.50'];
        yield 'aberrant' => ['150 % de bonheur', null];
        yield 'absent' => ['5 € offerts', null];
    }

    #[DataProvider('pourcentProvider')]
    public function testPourcent(string $texte, ?string $attendu): void
    {
        self::assertSame($attendu, (new MontantParser())->pourcent($texte));
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function conditionsProvider(): iterable
    {
        yield 'dès' => ['Profitez d\'une remise de 20% dès 69€ de commande', 'dès 69 €'];
        yield 'à partir de, avec achat' => ['10 € offerts à partir de 50 € d\'achat', 'dès 50 € d\'achat'];
        yield 'centimes' => ['dès 29,90 €', 'dès 29,90 €'];
        yield 'nombre de produits' => ['3ème offert pour 3 produits achetés', 'pour 3 produits achetés'];
        yield 'aucune' => ['Jusqu’à 200€ Remboursés', null];
    }

    #[DataProvider('conditionsProvider')]
    public function testCondition(string $texte, ?string $attendu): void
    {
        self::assertSame($attendu, (new MontantParser())->condition($texte));
    }
}

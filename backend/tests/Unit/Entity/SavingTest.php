<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Deal;
use App\Entity\Saving;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\DealType;
use App\Enum\SourceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SavingTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function montantsValidesProvider(): iterable
    {
        yield 'virgule française' => ['2,50', '2.50'];
        yield 'point' => ['0.5', '0.50'];
        yield 'symbole et espaces' => ['1 234,5 €', '1234.50'];
        yield 'espace insécable' => ["3\u{00A0}€", '3.00'];
        yield 'entier' => ['12', '12.00'];
        yield 'zéro' => ['0', '0.00'];
    }

    #[DataProvider('montantsValidesProvider')]
    public function testMontantNormalise(string $saisi, string $attendu): void
    {
        $saving = new Saving(new User('moi@exemple.test'), 'Test', $saisi, new \DateTimeImmutable('2026-10-29'));

        self::assertSame($attendu, $saving->getMontant());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function montantsInvalidesProvider(): iterable
    {
        yield 'négatif' => ['-1'];
        yield 'texte' => ['gratuit'];
        yield 'vide' => [''];
    }

    #[DataProvider('montantsInvalidesProvider')]
    public function testMontantInvalideRefuse(string $saisi): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Saving(new User('moi@exemple.test'), 'Test', $saisi, new \DateTimeImmutable());
    }

    public function testDepuisDealPreRemplitLeMontantEtLEnseigne(): void
    {
        $source = new Source('anti_crise_remboursement', 'AC', 'https://anti-crise.fr', SourceType::Scraping);
        $deal = (new Deal($source, DealType::Remboursement, 'Lessive 100 % remboursée', 'https://anti-crise.fr/x', str_repeat('b', 64)))
            ->setMarque('Skip')
            ->setValeurEuros('8.99');
        $user = new User('moi@exemple.test');

        $saving = Saving::depuisDeal($user, $deal, new \DateTimeImmutable('2026-10-29 18:30'));
        self::assertSame('8.99', $saving->getMontant());
        self::assertSame('Skip', $saving->getEnseigne());
        self::assertSame('Lessive 100 % remboursée', $saving->getLibelle());
        self::assertSame('2026-10-29 00:00', $saving->getDate()->format('Y-m-d H:i'));
        self::assertSame($deal, $saving->getDeal());

        self::assertSame('5.00', Saving::depuisDeal($user, $deal, new \DateTimeImmutable(), '5')->getMontant());

        $sansMontant = new Deal($source, DealType::Bon, 'Bon', 'https://anti-crise.fr/y', str_repeat('c', 64));
        self::assertSame('0.00', Saving::depuisDeal($user, $sansMontant, new \DateTimeImmutable())->getMontant());
    }
}

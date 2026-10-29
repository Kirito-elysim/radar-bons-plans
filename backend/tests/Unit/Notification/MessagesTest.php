<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\Deal;
use App\Entity\Source;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Enum\SourceType;
use App\Enum\TypeAlerte;
use App\Notification\Canal\TelegramCanal;
use App\Notification\LigneAlerte;
use App\Notification\MessageAlerte;
use PHPUnit\Framework\TestCase;

final class MessagesTest extends TestCase
{
    private function deal(string $titre, ?string $fin, ?string $euros = null, ?string $pourcent = null): Deal
    {
        $source = new Source('test', 'Test', 'https://exemple.test', SourceType::Scraping);

        return (new Deal($source, DealType::Remboursement, $titre, 'https://exemple.test/'.rawurlencode($titre), str_repeat('a', 64)))
            ->setMarque('Candia')
            ->setValeurEuros($euros)
            ->setValeurPourcent($pourcent)
            ->setSousType(null !== $pourcent ? RemboursementType::Rembourse100 : null)
            ->setDateFin(null !== $fin ? new \DateTimeImmutable($fin) : null);
    }

    public function testLigneDepuisUnBonPlan(): void
    {
        $aujourdhui = new \DateTimeImmutable('2026-10-29');

        $cent = LigneAlerte::depuisDeal($this->deal('Lait Viva', '2026-10-30', pourcent: '100.00'), $aujourdhui);
        self::assertSame('Candia · Lait Viva · 100 % remboursé · J-1 ⚠️', $cent->enTexte());

        $euros = LigneAlerte::depuisDeal($this->deal('Café', '2026-10-29', euros: '2.50'), $aujourdhui);
        self::assertSame('2,50 €', $euros->valeur);
        self::assertSame('dernier jour', $euros->echeance);

        self::assertNull(LigneAlerte::depuisDeal($this->deal('Sans date', null), $aujourdhui)->echeance);
    }

    public function testCorpsCourtPourLePush(): void
    {
        $lignes = [new LigneAlerte('Lait', 'https://a.test', 'Candia'), new LigneAlerte('Café', 'https://b.test'), new LigneAlerte('Thé', 'https://c.test')];

        self::assertSame('Candia · Lait (+2 autres)', (new MessageAlerte(TypeAlerte::ExpirationJ3, 'S', 'I', ['X' => $lignes]))->corpsCourt());
        self::assertSame('a · b', (new MessageAlerte(TypeAlerte::RecapQuotidien, 'S', 'I', ['X' => $lignes], ['a', 'b']))->corpsCourt());
        self::assertSame('Intro', (new MessageAlerte(TypeAlerte::RecapQuotidien, 'S', 'Intro'))->corpsCourt());
    }

    public function testFormatTelegramEchappeEtLimite(): void
    {
        $message = new MessageAlerte(
            TypeAlerte::ExpirationJ1,
            'Offres <demain>',
            'Intro & suite',
            ['À saisir' => [new LigneAlerte('Lait "Viva"', 'https://exemple.test/?a=1&b=2', 'Candia')], 'Vide' => []],
            lienAppli: 'https://radar.exemple.test/',
        );

        $texte = TelegramCanal::formater($message);

        self::assertStringStartsWith('<b>Offres &lt;demain&gt;</b>', $texte);
        self::assertStringContainsString('Intro &amp; suite', $texte);
        self::assertStringContainsString('• <a href="https://exemple.test/?a=1&amp;b=2">Candia · Lait &quot;Viva&quot;</a>', $texte);
        self::assertStringNotContainsString('Vide', $texte, 'Section vide omise');
        self::assertStringEndsWith('<a href="https://radar.exemple.test/">Ouvrir Radar</a>', $texte);

        $long = new MessageAlerte(TypeAlerte::RecapQuotidien, 'Long', 'I', ['X' => array_fill(0, 300, new LigneAlerte(str_repeat('é', 40), 'https://x.test'))]);
        $tronque = TelegramCanal::formater($long);
        self::assertLessThanOrEqual(4096, mb_strlen($tronque));
        self::assertStringEndsWith("\n…", $tronque);
    }
}

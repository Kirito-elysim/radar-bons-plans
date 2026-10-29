<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Deal;
use App\Entity\Source;
use App\Enum\DealType;
use App\Enum\SourceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DealTest extends TestCase
{
    private function deal(?string $dateFin): Deal
    {
        $source = new Source('test', 'Test', 'https://exemple.test', SourceType::Scraping);
        $deal = new Deal($source, DealType::Catalogue, 'Catalogue test', 'https://exemple.test/c', str_repeat('a', 64));

        return $deal->setDateFin(null !== $dateFin ? new \DateTimeImmutable($dateFin) : null);
    }

    /**
     * @return iterable<string, array{?string, string, ?int}>
     */
    public static function joursRestantsProvider(): iterable
    {
        yield 'date de fin inconnue' => [null, '2026-10-29 10:00', null];
        yield 'dernier jour' => ['2026-10-29', '2026-10-29 23:59', 0];
        yield 'J-1' => ['2026-10-30', '2026-10-29 07:40', 1];
        yield 'J-3' => ['2026-11-01', '2026-10-29 00:00', 3];
        yield 'terminée hier' => ['2026-10-28', '2026-10-29 08:00', -1];
        // Passage à l'heure d'hiver le 25/10/2026 : la journée fait 25 h, le calcul reste en jours calendaires.
        yield 'changement d\'heure' => ['2026-10-26', '2026-10-24 12:00', 2];
        yield 'changement d\'année' => ['2027-01-02', '2026-12-31 18:00', 2];
    }

    #[DataProvider('joursRestantsProvider')]
    public function testJoursRestants(?string $dateFin, string $maintenant, ?int $attendu): void
    {
        $paris = new \DateTimeZone('Europe/Paris');
        self::assertSame($attendu, $this->deal($dateFin)->joursRestants(new \DateTimeImmutable($maintenant, $paris)));
    }

    public function testEstTermineeSeulementLeLendemainDeLaDateDeFin(): void
    {
        $deal = $this->deal('2026-10-29');

        self::assertFalse($deal->estTermineeAu(new \DateTimeImmutable('2026-10-29 23:59')));
        self::assertTrue($deal->estTermineeAu(new \DateTimeImmutable('2026-10-30 00:01')));
        self::assertFalse($this->deal(null)->estTermineeAu(new \DateTimeImmutable('2030-01-01')));
    }

    public function testDescriptionCourteEstTronquee(): void
    {
        $deal = $this->deal(null)->setDescriptionCourte(str_repeat('é', 500));

        self::assertSame(Deal::DESCRIPTION_MAX, mb_strlen((string) $deal->getDescriptionCourte()));
        self::assertStringEndsWith('…', (string) $deal->getDescriptionCourte());
        self::assertSame('Court', $deal->setDescriptionCourte('Court')->getDescriptionCourte());
    }

    public function testDateFinEstimeeRemiseAZeroSansDate(): void
    {
        $deal = $this->deal(null)->setDateFin(new \DateTimeImmutable('2026-11-01'), estimee: true);
        self::assertTrue($deal->isDateFinEstimee());

        $deal->setDateFin(null, estimee: true);
        self::assertFalse($deal->isDateFinEstimee());
    }
}

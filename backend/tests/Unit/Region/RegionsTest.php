<?php

declare(strict_types=1);

namespace App\Tests\Unit\Region;

use App\Region\Regions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RegionsTest extends TestCase
{
    /**
     * Intitulés relevés sur Anti-crise.fr le 29/10/2026.
     *
     * @return iterable<string, array{string, ?string}>
     */
    public static function intitulesProvider(): iterable
    {
        yield 'Exclus PACA' => ['Exclus PACA', 'paca'];
        yield 'Provencia' => ['Provencia', 'paca'];
        yield 'Sud-Est viande' => ['Sud-Est viande', 'paca'];
        yield 'Exclus Nord-Est (pas Nord ni Est)' => ['Exclus Nord-Est', 'nord-est'];
        yield 'Nord Toussaint' => ['Nord Toussaint', 'nord'];
        yield 'Foire au gras Est' => ['Foire au gras Est', 'nord-est'];
        yield 'Alsace bredele' => ['Alsace bredele', 'nord-est'];
        yield 'Exclus Sud-Ouest' => ['Exclus Sud-Ouest', 'sud-ouest'];
        yield 'Toussaint Sud-Ouest' => ['Toussaint Sud-Ouest', 'sud-ouest'];
        yield 'Centre-Ouest' => ['Traiteur Centre-Ouest', 'ouest'];
        yield 'Exclus Centre' => ['Exclus Centre', 'centre'];
        yield 'Exclus Rhône-Alpes' => ['Exclus Rhône-Alpes', 'rhone-alpes'];
        yield 'Exclus Languedoc' => ['Exclus Languedoc', 'occitanie'];
        yield 'Occitanie foire au porc' => ['Occitanie foire au porc', 'occitanie'];
        yield 'Exclus IDF' => ['Exclus IDF', 'idf'];
        yield 'Exclus Bretagne Normandie' => ['Exclus Bretagne Normandie', 'bretagne-normandie'];
        yield 'Normandie boucherie' => ['Normandie boucherie', 'bretagne-normandie'];
        yield '« Sud » couvre plusieurs zones' => ['Sud foire au gras', 'paca,occitanie,sud-ouest'];
        yield 'ville Estissac' => ['Estissac', null];
        yield 'ville La Pacaudière' => ['La Pacaudière', null];
        yield 'Destockage' => ['Destockage', null];
        yield 'Exclu web' => ['Exclu web', null];
        yield 'national' => ['Promos', null];
        yield 'suffixe d\'URL' => [' exclus-paca', 'paca'];
    }

    #[DataProvider('intitulesProvider')]
    public function testDetecter(string $intitule, ?string $attendu): void
    {
        self::assertSame($attendu, Regions::detecter($intitule));
    }

    public function testCouvre(): void
    {
        self::assertTrue(Regions::couvre(null, 'paca'), 'National');
        self::assertTrue(Regions::couvre('paca', 'paca'));
        self::assertTrue(Regions::couvre('paca,occitanie,sud-ouest', 'paca'));
        self::assertFalse(Regions::couvre('nord', 'paca'));
        self::assertTrue(Regions::couvre('nord', null), 'Sans région : tout');
    }

    public function testLibelle(): void
    {
        self::assertSame("Provence-Alpes-Côte d'Azur (Provencia)", Regions::libelle('paca'));
        self::assertSame('Nord / Hauts-de-France · Grand Est', Regions::libelle('nord,nord-est'));
        self::assertNull(Regions::libelle(null));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Connector;

use App\Collecte\Connector\LienBioConnector;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\MontantParser;
use App\Enum\DealType;
use App\Repository\WatchedCreatorRepository;
use App\Tests\Support\Fixtures;
use App\Tests\Support\JournalLogger;
use PHPUnit\Framework\TestCase;

final class LienBioConnectorTest extends TestCase
{
    private function connecteur(): LienBioConnector
    {
        return new LienBioConnector(
            $this->createStub(PoliteHttpClient::class),
            $this->createStub(WatchedCreatorRepository::class),
            new MontantParser(),
            new JournalLogger(),
        );
    }

    /**
     * @param list<DealDTO> $deals
     *
     * @return list<string>
     */
    private static function urls(array $deals): array
    {
        return array_map(static fn (DealDTO $d): string => $d->url, $deals);
    }

    public function testPageLinktree(): void
    {
        $deals = $this->connecteur()->parser(Fixtures::lire('lien-bio/linktree.html'), 'https://linktr.ee/lejusticierdbp', 'lejusticierdbp');

        self::assertSame([
            'https://bons-plans.exemple.fr/semaine',
            'https://www.exemple-drive.fr/parrainage?ref=justicier',
            'https://cashback.exemple.fr/',
        ], self::urls($deals), 'Réseaux sociaux et mailto exclus');

        $code = $deals[1];
        self::assertSame(DealType::TikTok, $code->type);
        self::assertSame('10.00', $code->valeurEuros);
        self::assertSame("dès 50 € d'achat", $code->condition);
        self::assertSame('Lien en bio de @lejusticierdbp', $code->descriptionCourte);
        self::assertSame('lejusticierdbp', $code->createurPseudo);
        self::assertSame('5.00', $deals[2]->valeurPourcent);
    }

    public function testBlogGenerique(): void
    {
        $deals = $this->connecteur()->parser(Fixtures::lire('lien-bio/blog.html'), 'https://blog.exemple.fr/', 'nelson_mrq');

        self::assertSame([
            'https://blog.exemple.fr/2026/10/lessive-gratuite',
            'https://www.exemple-supermarche.fr/vente',
        ], self::urls($deals), 'Liens relatifs résolus, liens sans mot-clé ni texte ignorés');
        self::assertSame('Vente privée -40 %', $deals[1]->titre, 'Titre lu dans l\'attribut title');
    }
}

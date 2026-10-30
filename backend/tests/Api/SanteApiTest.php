<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Enum\RunStatus;
use App\Repository\SourceRepository;

final class SanteApiTest extends ApiTestCaseRadar
{
    public function testSondePubliqueSansDonneePersonnelle(): void
    {
        $reponse = $this->client->request('GET', '/api/sante')->toArray();

        self::assertResponseIsSuccessful();
        self::assertSame(['statut' => 'ok', 'base' => 'ok', 'derniereCollecte' => null, 'collecteEnRetard' => false], $reponse);
    }

    public function testCollecteEnRetard(): void
    {
        $source = static::getContainer()->get(SourceRepository::class)->findOneByCode('radins');
        self::assertNotNull($source);
        $source->enregistrerRun(new \DateTimeImmutable('-8 hours'), RunStatus::Succes, 10);
        $this->em()->flush();

        $reponse = $this->client->request('GET', '/api/sante')->toArray();

        self::assertTrue($reponse['collecteEnRetard'], 'Plus de 7 h sans collecte : worker probablement arrêté');
    }
}

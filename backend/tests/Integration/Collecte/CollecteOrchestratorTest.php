<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collecte;

use App\Collecte\CollecteOrchestrator;
use App\Collecte\Connector\AntiCriseCatalogueConnector;
use App\Collecte\DealHasher;
use App\Collecte\Event\ConnecteurVideEvent;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Http\RobotsTxtChecker;
use App\Collecte\Parsing\FrenchDateParser;
use App\Collecte\SourceConnectorInterface;
use App\Enum\RunStatus;
use App\Repository\DealRepository;
use App\Repository\SourceRepository;
use App\Repository\WatchedCreatorRepository;
use App\Tests\Support\Fixtures;
use App\Tests\Support\JournalLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Collecte de bout en bout : connecteur réel + HTTP simulé (fixtures) + PostgreSQL.
 */
final class CollecteOrchestratorTest extends KernelTestCase
{
    private MockClock $clock;
    private JournalLogger $logger;
    private EventDispatcher $dispatcher;
    /** @var list<object> */
    private array $evenements = [];
    private string $pageCatalogues;
    private string $robots;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->clock = new MockClock('2026-10-29 07:40:00', 'Europe/Paris');
        $this->logger = new JournalLogger();
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addListener(ConnecteurVideEvent::class, function (ConnecteurVideEvent $e): void {
            $this->evenements[] = $e;
        });
        $this->pageCatalogues = Fixtures::lire('anti-crise/catalogues.html');
        $this->robots = "User-agent: *\nDisallow: */feed\nDisallow: */feed/";
    }

    /**
     * @param list<SourceConnectorInterface>|null $connecteurs null = connecteur Anti-crise réel
     */
    private function orchestrateur(?array $connecteurs = null): CollecteOrchestrator
    {
        $mock = new MockHttpClient(fn (string $methode, string $url): MockResponse => str_ends_with($url, '/robots.txt')
            ? new MockResponse($this->robots)
            : new MockResponse($this->pageCatalogues));
        $cache = new ArrayAdapter();
        $http = new PoliteHttpClient($mock, new RobotsTxtChecker($mock, $cache, 'RadarBonsPlans/1.0', $this->logger), $cache, $this->logger, 2.0, $this->clock);
        $c = self::getContainer();

        return new CollecteOrchestrator(
            $connecteurs ?? [new AntiCriseCatalogueConnector($http, new FrenchDateParser())],
            $c->get(SourceRepository::class),
            $c->get(DealRepository::class),
            $c->get(EntityManagerInterface::class),
            $http,
            new DealHasher(),
            $this->dispatcher,
            $this->clock,
            $this->logger,
            $c->get(WatchedCreatorRepository::class),
        );
    }

    public function testPremiereCollectePuisRelanceSansDoublon(): void
    {
        $orchestrateur = $this->orchestrateur();

        $premier = $orchestrateur->executer(AntiCriseCatalogueConnector::CODE);
        self::assertSame(RunStatus::Succes, $premier->statut);
        self::assertSame(361, $premier->nbRecus);
        self::assertSame(361, $premier->nbNouveaux);

        $this->clock->sleep(6 * 3600);
        $second = $orchestrateur->executer(AntiCriseCatalogueConnector::CODE);
        self::assertSame(0, $second->nbNouveaux);
        self::assertSame(361, $second->nbMisAJour);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $source = self::getContainer()->get(SourceRepository::class)->findOneByCode(AntiCriseCatalogueConnector::CODE);
        self::assertNotNull($source);
        self::assertSame(RunStatus::Succes, $source->getDernierStatut());
        self::assertSame(361, $source->getDernierNbDeals());
        self::assertSame('2026-10-29 13:40', $source->getDernierRun()?->format('Y-m-d H:i'));

        $deals = self::getContainer()->get(DealRepository::class)->findBy(['source' => $source]);
        self::assertCount(361, $deals);
        $provencia = self::getContainer()->get(DealRepository::class)->findOneBy(['region' => 'Provencia (Sud-Est)', 'enseigne' => 'Carrefour']);
        self::assertNotNull($provencia);
        self::assertSame('2026-11-15', $provencia->getDateFin()?->format('Y-m-d'));
        self::assertSame('2026-10-29 07:40', $provencia->getPremiereVueLe()->format('Y-m-d H:i'), 'La date de première vue ne change pas');
        self::assertFalse($provencia->isExpire());
    }

    public function testCatalogueDejaTermineEnregistreCommeExpire(): void
    {
        $this->clock = new MockClock('2026-11-05 07:40:00', 'Europe/Paris');
        $this->orchestrateur()->executer(AntiCriseCatalogueConnector::CODE);
        $deals = self::getContainer()->get(DealRepository::class);

        $termine = $deals->findOneBy(['url' => 'https://anti-crise.fr/catalogue/catalogue-aldi-du-27-octobre-au-02-novembre-2026/']);
        self::assertTrue($termine?->isExpire(), 'Fini le 02/11, collecté le 05/11');

        $enCours = $deals->findOneBy(['url' => 'https://anti-crise.fr/catalogue/catalogue-carrefour-du-04-au-15-novembre-2026-provencia/']);
        self::assertFalse($enCours?->isExpire());
    }

    public function testZeroResultatStatutVideAlerteEtJournal(): void
    {
        $this->pageCatalogues = Fixtures::lire('anti-crise/catalogues-structure-modifiee.html');

        $rapport = $this->orchestrateur()->executer(AntiCriseCatalogueConnector::CODE);

        self::assertSame(RunStatus::Vide, $rapport->statut);
        self::assertCount(1, $this->evenements, 'Alerte administrateur déclenchée');
        self::assertTrue($this->logger->contient('critical', '0 résultat'));
        self::assertSame(RunStatus::Vide, self::getContainer()->get(SourceRepository::class)->findOneByCode(AntiCriseCatalogueConnector::CODE)?->getDernierStatut());
    }

    public function testPageInterditeParRobotsTxt(): void
    {
        $this->robots = "User-agent: *\nDisallow: /catalogue/";

        $rapport = $this->orchestrateur()->executer(AntiCriseCatalogueConnector::CODE);

        self::assertSame(RunStatus::Erreur, $rapport->statut);
        self::assertSame(['https://anti-crise.fr/catalogue/'], $rapport->pagesIgnorees);
        self::assertTrue($this->logger->contient('info', 'Page ignorée'));
    }

    public function testErreurDuConnecteur(): void
    {
        $enPanne = new class implements SourceConnectorInterface {
            public function getCode(): string
            {
                return 'shopmium';
            }

            public function fetch(): array
            {
                throw new CollecteException('HTTP 503 sur https://offers.shopmium.com/fr');
            }
        };

        $rapport = $this->orchestrateur([$enPanne])->executer('shopmium');

        self::assertSame(RunStatus::Erreur, $rapport->statut);
        $source = self::getContainer()->get(SourceRepository::class)->findOneByCode('shopmium');
        self::assertNotNull($source);
        self::assertSame(RunStatus::Erreur, $source->getDernierStatut());
        self::assertSame('HTTP 503 sur https://offers.shopmium.com/fr', $source->getDernierErreur());
    }

    public function testModeSimulationNEcritRien(): void
    {
        $rapport = $this->orchestrateur()->executer(AntiCriseCatalogueConnector::CODE, simulation: true);

        self::assertSame(361, $rapport->nbRecus);
        self::assertSame(0, self::getContainer()->get(DealRepository::class)->count([]));
    }

    public function testSourceSansConnecteur(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Aucun connecteur');

        $this->orchestrateur()->executer('radins');
    }

    public function testTousLesConnecteursSontEnregistresDansLeConteneur(): void
    {
        self::assertEqualsCanonicalizing(
            ['anti_crise_catalogue', 'anti_crise_remboursement', 'shopmium', 'envie_de_bien_manger', 'radins', 'lien_bio'],
            self::getContainer()->get(CollecteOrchestrator::class)->codesDisponibles(),
        );
    }

    public function testLienBioSansPageConfigureeNEstPasUneAnomalie(): void
    {
        $rapport = self::getContainer()->get(CollecteOrchestrator::class)->executer('lien_bio');

        self::assertSame(RunStatus::Succes, $rapport->statut);
        self::assertSame(0, $rapport->nbRecus);
    }
}

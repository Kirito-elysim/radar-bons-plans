<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collecte\Http;

use App\Collecte\Exception\CollecteException;
use App\Collecte\Exception\PageInterditeException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Http\RobotsTxtChecker;
use App\Tests\Support\JournalLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PoliteHttpClientTest extends TestCase
{
    private MockClock $clock;
    private JournalLogger $logger;
    /** @var list<array{url: string, quand: string, entetes: list<string>}> */
    private array $requetes = [];

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-10-29 07:40:00');
        $this->logger = new JournalLogger();
        $this->requetes = [];
    }

    /**
     * @param callable(string): MockResponse $repondre réponse pour les URL hors robots.txt
     */
    private function client(callable $repondre, string $robots = "User-agent: *\nDisallow: /prive/"): PoliteHttpClient
    {
        $mock = new MockHttpClient(function (string $methode, string $url, array $options) use ($repondre, $robots): MockResponse {
            if (str_ends_with($url, '/robots.txt')) {
                return new MockResponse($robots);
            }
            /** @var list<string> $entetes */
            $entetes = $options['headers'] ?? [];
            $this->requetes[] = ['url' => $url, 'quand' => $this->clock->now()->format('H:i:s.u'), 'entetes' => $entetes];

            return $repondre($url);
        });
        $cache = new ArrayAdapter();

        return new PoliteHttpClient($mock, new RobotsTxtChecker($mock, $cache, 'RadarBonsPlans/1.0', $this->logger), $cache, $this->logger, 2.0, $this->clock);
    }

    public function testAuPlusUneRequeteToutesLesDeuxSecondesParDomaine(): void
    {
        $client = $this->client(static fn (): MockResponse => new MockResponse('ok'));

        $client->get('https://anti-crise.fr/catalogue/');
        $client->get('https://www.radins.com/code-promo/');
        $this->clock->sleep(0.5);
        $client->get('https://anti-crise.fr/les-offres-de-remboursement/');

        self::assertSame(['07:40:00.000000', '07:40:00.000000', '07:40:02.000000'], array_column($this->requetes, 'quand'));
    }

    public function testPageInterditeParRobotsNonTelechargeeEtJournalisee(): void
    {
        $client = $this->client(static fn (): MockResponse => new MockResponse('ok'));

        try {
            $client->get('https://anti-crise.fr/prive/page');
            self::fail('Une PageInterditeException était attendue.');
        } catch (PageInterditeException $e) {
            self::assertSame('https://anti-crise.fr/prive/page', $e->url);
        }

        self::assertSame([], $this->requetes);
        self::assertSame(['https://anti-crise.fr/prive/page'], $client->getPagesIgnorees());
        self::assertTrue($this->logger->contient('info', 'Page ignorée'));

        $client->reinitialiserJournal();
        self::assertSame([], $client->getPagesIgnorees());
    }

    public function testCacheConditionnelEtag(): void
    {
        $client = $this->client(function (): MockResponse {
            return 1 === \count($this->requetes)
                ? new MockResponse('<html>v1</html>', ['response_headers' => ['ETag' => '"abc"']])
                : new MockResponse('', ['http_code' => 304]);
        });

        $contenus = [$client->get('https://anti-crise.fr/catalogue/'), $client->get('https://anti-crise.fr/catalogue/')];

        self::assertSame(['<html>v1</html>', '<html>v1</html>'], $contenus, 'La 2e lecture (304) renvoie le contenu en cache');
        self::assertContains('If-None-Match: "abc"', $this->requetes[1]['entetes']);
    }

    public function testErreurHttp(): void
    {
        $client = $this->client(static fn (): MockResponse => new MockResponse('panne', ['http_code' => 503]));

        $this->expectException(CollecteException::class);
        $this->expectExceptionMessage('HTTP 503');
        $client->get('https://anti-crise.fr/catalogue/');
    }

    public function testRobotsTxtAbsentAutoriseToutEtServeurEnPanneInterditTout(): void
    {
        $cache = new ArrayAdapter();
        $absent = new MockHttpClient(new MockResponse('', ['http_code' => 404]));
        self::assertTrue((new RobotsTxtChecker($absent, $cache, 'RadarBonsPlans/1.0', $this->logger))->estAutorise('https://exemple.test/x'));

        $panne = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        self::assertFalse((new RobotsTxtChecker($panne, new ArrayAdapter(), 'RadarBonsPlans/1.0', $this->logger))->estAutorise('https://exemple.test/x'));
        self::assertTrue($this->logger->contient('warning', 'robots.txt indisponible'));
    }

    public function testRobotsTxtTelechargeUneSeuleFoisParSite(): void
    {
        $appels = 0;
        $mock = new MockHttpClient(static function () use (&$appels): MockResponse {
            ++$appels;

            return new MockResponse("User-agent: *\nDisallow: /feed");
        });
        $cache = new ArrayAdapter();

        (new RobotsTxtChecker($mock, $cache, 'RadarBonsPlans/1.0', $this->logger))->estAutorise('https://anti-crise.fr/a');
        $checker = new RobotsTxtChecker($mock, $cache, 'RadarBonsPlans/1.0', $this->logger);
        self::assertTrue($checker->estAutorise('https://anti-crise.fr/b'));
        self::assertFalse($checker->estAutorise('https://anti-crise.fr/feed'));

        self::assertSame(1, $appels, 'Le robots.txt doit être relu depuis le cache.');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Http\RobotsTxtChecker;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Construit un PoliteHttpClient servant des réponses simulées (robots.txt permissif par défaut).
 */
final class HttpPoliFactory
{
    /** @var list<string> URL demandées, hors robots.txt */
    public array $requetes = [];

    /**
     * @param array<string, string|MockResponse> $pages URL exacte → contenu
     */
    public function creer(array $pages, string $robots = "User-agent: *\nDisallow:", ?JournalLogger $logger = null): PoliteHttpClient
    {
        $mock = new MockHttpClient(function (string $methode, string $url) use ($pages, $robots): MockResponse {
            if (str_ends_with($url, '/robots.txt')) {
                return new MockResponse($robots);
            }
            $this->requetes[] = $url;
            $page = $pages[$url] ?? new MockResponse('introuvable', ['http_code' => 404]);

            return $page instanceof MockResponse ? $page : new MockResponse($page);
        });
        $cache = new ArrayAdapter();
        $logger ??= new JournalLogger();

        return new PoliteHttpClient($mock, new RobotsTxtChecker($mock, $cache, 'RadarBonsPlans/1.0', $logger), $cache, $logger, 2.0, new MockClock());
    }
}

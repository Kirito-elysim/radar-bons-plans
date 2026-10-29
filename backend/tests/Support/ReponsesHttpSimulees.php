<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Remplace tous les clients HTTP en environnement de test (framework.http_client.mock_response_factory) :
 * aucun appel réseau réel pendant les tests. Les tests enregistrent leurs réponses par URL.
 */
final class ReponsesHttpSimulees
{
    /** @var array<string, MockResponse> */
    private static array $reponses = [];

    public static function definir(string $url, MockResponse $reponse): void
    {
        self::$reponses[$url] = $reponse;
    }

    public static function reinitialiser(): void
    {
        self::$reponses = [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $methode, string $url, array $options = []): ResponseInterface
    {
        if (str_ends_with($url, '/robots.txt')) {
            return new MockResponse("User-agent: *\nDisallow:");
        }

        return self::$reponses[$url] ?? new MockResponse('Aucune réponse simulée pour '.$url, ['http_code' => 404]);
    }
}

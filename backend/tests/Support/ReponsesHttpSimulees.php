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
    /** @var list<array{url: string, corps: string}> messages envoyés au bot Telegram */
    public static array $telegram = [];

    public static function definir(string $url, MockResponse $reponse): void
    {
        self::$reponses[$url] = $reponse;
    }

    public static function reinitialiser(): void
    {
        self::$reponses = [];
        self::$telegram = [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $methode, string $url, array $options = []): ResponseInterface
    {
        if (str_ends_with($url, '/robots.txt')) {
            return new MockResponse("User-agent: *\nDisallow:");
        }

        if (str_starts_with($url, 'https://api.telegram.org/')) {
            $corps = $options['body'] ?? '';
            self::$telegram[] = ['url' => $url, 'corps' => \is_string($corps) ? $corps : ''];

            return new MockResponse('{"ok":true,"result":{"message_id":1}}');
        }

        return self::$reponses[$url] ?? new MockResponse('Aucune réponse simulée pour '.$url, ['http_code' => 404]);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Social;

use App\Enum\Plateforme;
use App\Social\TikTokOEmbedProvider;
use App\Social\VideoIntrouvableException;
use App\Tests\Support\Fixtures;
use App\Tests\Support\HttpPoliFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TikTokOEmbedProviderTest extends TestCase
{
    private const VIDEO = 'https://www.tiktok.com/@nelson_mrq/video/7430000000000000001';
    private const ENDPOINT = 'https://www.tiktok.com/oembed?url=https%3A%2F%2Fwww.tiktok.com%2F%40nelson_mrq%2Fvideo%2F7430000000000000001';

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function urlsProvider(): iterable
    {
        yield 'vidéo' => [self::VIDEO, true];
        yield 'vidéo avec paramètres de partage' => [self::VIDEO.'?is_from_webapp=1&sender_device=pc', true];
        yield 'lien court vm' => ['https://vm.tiktok.com/ZMabc123/', true];
        yield 'lien court /t/' => ['https://www.tiktok.com/t/ZTRabc123/', true];
        yield 'profil (pas une vidéo)' => ['https://www.tiktok.com/@nelson_mrq', false];
        yield 'autre site' => ['https://www.youtube.com/watch?v=abc', false];
        yield 'http non sécurisé' => ['http://www.tiktok.com/@a/video/1', false];
    }

    #[DataProvider('urlsProvider')]
    public function testSupports(string $url, bool $attendu): void
    {
        self::assertSame($attendu, (new TikTokOEmbedProvider((new HttpPoliFactory())->creer([])))->supports($url));
    }

    public function testRecupereLesMetadonneesViaOEmbed(): void
    {
        $factory = new HttpPoliFactory();
        $provider = new TikTokOEmbedProvider($factory->creer([self::ENDPOINT => Fixtures::lire('tiktok/oembed-video.json')]));

        $video = $provider->recuperer(self::VIDEO.'?is_from_webapp=1');

        self::assertSame(Plateforme::TikTok, $video->plateforme);
        self::assertSame(self::VIDEO, $video->url, 'Paramètres de partage retirés');
        self::assertSame('7430000000000000001', $video->idVideo);
        self::assertSame('nelson_mrq', $video->auteurPseudo);
        self::assertStringStartsWith('Lessive à 0,30 €', $video->titre);
        self::assertSame('https://p16-sign-va.tiktokcdn.com/obj/exemple-miniature.jpeg', $video->miniatureUrl);
        self::assertSame([self::ENDPOINT], $factory->requetes, 'Un seul appel, à l\'oEmbed uniquement');
    }

    public function testLimiteDeRequetesTikTok(): void
    {
        $provider = new TikTokOEmbedProvider((new HttpPoliFactory())->creer([self::ENDPOINT => new MockResponse('', ['http_code' => 429])]));

        $this->expectException(VideoIntrouvableException::class);
        $this->expectExceptionMessage('limite temporairement');
        $provider->recuperer(self::VIDEO);
    }

    public function testVideoPrivee(): void
    {
        $provider = new TikTokOEmbedProvider((new HttpPoliFactory())->creer([self::ENDPOINT => new MockResponse('{"code":400}', ['http_code' => 400])]));

        $this->expectException(VideoIntrouvableException::class);
        $this->expectExceptionMessage('introuvable ou privée');
        $provider->recuperer(self::VIDEO);
    }

    public function testLienDeProfilRefuse(): void
    {
        $this->expectException(VideoIntrouvableException::class);

        (new TikTokOEmbedProvider((new HttpPoliFactory())->creer([])))->recuperer('https://www.tiktok.com/@nelson_mrq');
    }
}

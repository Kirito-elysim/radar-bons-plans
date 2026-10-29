<?php

declare(strict_types=1);

namespace App\Social;

use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Enum\Plateforme;

/**
 * Métadonnées d'une vidéo TikTok via l'oEmbed public (https://www.tiktok.com/oembed).
 * Aucun scraping de TikTok : un seul appel documenté par vidéo, déclenché par l'utilisateur.
 */
final class TikTokOEmbedProvider implements SocialVideoProviderInterface
{
    public const ENDPOINT = 'https://www.tiktok.com/oembed?url=';

    /** Vidéo complète (…/@pseudo/video/123) ou lien court (vm.tiktok.com/…, tiktok.com/t/…). */
    private const MOTIF_URL = '#^https://(?:(?:www|m)\.tiktok\.com/@[\w.-]+/video/\d+|vm\.tiktok\.com/[\w-]+|(?:www\.)?tiktok\.com/t/[\w-]+)/?(?:\?.*)?$#i';

    public function __construct(private readonly PoliteHttpClient $http)
    {
    }

    public function supports(string $url): bool
    {
        return 1 === preg_match(self::MOTIF_URL, trim($url));
    }

    public function recuperer(string $url): VideoMetadata
    {
        $url = trim($url);
        if (!$this->supports($url)) {
            throw new VideoIntrouvableException('Ce lien ne ressemble pas à une vidéo TikTok (attendu : https://www.tiktok.com/@pseudo/video/123…).');
        }
        $url = (string) strtok($url, '?');

        try {
            $reponse = $this->http->get(self::ENDPOINT.rawurlencode($url));
        } catch (CollecteException $e) {
            throw new VideoIntrouvableException(str_contains($e->getMessage(), 'HTTP 429')
                ? 'TikTok limite temporairement les requêtes : réessayez dans quelques minutes ou saisissez le bon plan à la main.'
                : 'Vidéo TikTok introuvable ou privée.', 0, $e);
        }

        try {
            $json = json_decode($reponse, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new VideoIntrouvableException('Réponse oEmbed TikTok illisible.', 0, $e);
        }
        if (!\is_array($json) || !\is_string($json['title'] ?? null)) {
            throw new VideoIntrouvableException('Réponse oEmbed TikTok incomplète.');
        }

        $auteurUrl = \is_string($json['author_url'] ?? null) ? $json['author_url'] : null;
        $pseudo = null !== $auteurUrl && preg_match('#/@([\w.-]+)#', $auteurUrl, $m) ? $m[1] : (\is_string($json['author_unique_id'] ?? null) ? $json['author_unique_id'] : null);
        $idVideo = preg_match('#/video/(\d+)#', $url, $m) ? $m[1] : (\is_string($json['embed_product_id'] ?? null) ? $json['embed_product_id'] : null);

        return new VideoMetadata(
            plateforme: Plateforme::TikTok,
            url: $url,
            idVideo: $idVideo,
            titre: trim($json['title']),
            auteurPseudo: $pseudo,
            auteurUrl: $auteurUrl,
            miniatureUrl: \is_string($json['thumbnail_url'] ?? null) ? $json['thumbnail_url'] : null,
        );
    }
}

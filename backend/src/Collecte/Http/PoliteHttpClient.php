<?php

declare(strict_types=1);

namespace App\Collecte\Http;

use App\Collecte\Exception\CollecteException;
use App\Collecte\Exception\PageInterditeException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client HTTP « poli » utilisé par tous les connecteurs :
 * respect du robots.txt, au plus une requête toutes les N secondes par domaine,
 * cache conditionnel (ETag / Last-Modified). Les retries avec backoff sont
 * assurés par le client « collecte.client » (voir framework.yaml).
 */
class PoliteHttpClient
{
    /** @var list<string> */
    private array $pagesIgnorees = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly RobotsTxtChecker $robots,
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $collecteLogger,
        private readonly float $delaiParDomaine = 2.0,
        private readonly ClockInterface $clock = new Clock(),
    ) {
    }

    /**
     * Télécharge une page et renvoie son contenu.
     *
     * @throws PageInterditeException si le robots.txt l'interdit
     * @throws CollecteException      en cas d'erreur HTTP ou réseau
     */
    public function get(string $url): string
    {
        if (!$this->robots->estAutorise($url)) {
            $this->pagesIgnorees[] = $url;
            $this->collecteLogger->info('Page ignorée (interdite par robots.txt) : {url}', ['url' => $url]);

            throw new PageInterditeException($url);
        }

        $hote = strtolower((string) parse_url($url, \PHP_URL_HOST));
        $this->patienter($hote);

        $itemCache = $this->cache->getItem('http.'.sha1($url));
        /** @var array{etag: ?string, lastModified: ?string, contenu: string}|null $enCache */
        $enCache = $itemCache->isHit() ? $itemCache->get() : null;

        $entetes = [];
        if (null !== $enCache) {
            if (null !== $enCache['etag']) {
                $entetes['If-None-Match'] = $enCache['etag'];
            }
            if (null !== $enCache['lastModified']) {
                $entetes['If-Modified-Since'] = $enCache['lastModified'];
            }
        }

        try {
            $reponse = $this->client->request('GET', $url, ['headers' => $entetes]);
            $statut = $reponse->getStatusCode();

            if (304 === $statut && null !== $enCache) {
                $this->collecteLogger->debug('Page inchangée (304), cache utilisé : {url}', ['url' => $url]);

                return $enCache['contenu'];
            }
            if ($statut < 200 || $statut >= 300) {
                throw new CollecteException(\sprintf('HTTP %d sur %s', $statut, $url));
            }

            $contenu = $reponse->getContent();
            $h = $reponse->getHeaders(false);
            $etag = $h['etag'][0] ?? null;
            $lastModified = $h['last-modified'][0] ?? null;
            if (null !== $etag || null !== $lastModified) {
                $itemCache->set(['etag' => $etag, 'lastModified' => $lastModified, 'contenu' => $contenu]);
                $this->cache->save($itemCache);
            }

            return $contenu;
        } catch (HttpException $e) {
            throw new CollecteException(\sprintf('Erreur réseau sur %s : %s', $url, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Pages sautées depuis le dernier appel à reinitialiserJournal().
     *
     * @return list<string>
     */
    public function getPagesIgnorees(): array
    {
        return $this->pagesIgnorees;
    }

    public function reinitialiserJournal(): void
    {
        $this->pagesIgnorees = [];
    }

    /**
     * Attend si la dernière requête vers ce domaine date de moins de $delaiParDomaine secondes.
     * L'horodatage est partagé via le cache pour couvrir plusieurs workers.
     */
    private function patienter(string $hote): void
    {
        $item = $this->cache->getItem('throttle.'.sha1($hote));
        $maintenant = (float) $this->clock->now()->format('U.u');

        if ($item->isHit() && is_numeric($derniere = $item->get())) {
            $attente = $this->delaiParDomaine - ($maintenant - (float) $derniere);
            if ($attente > 0) {
                $this->clock->sleep($attente);
                $maintenant = (float) $this->clock->now()->format('U.u');
            }
        }

        $item->set($maintenant)->expiresAfter((int) ceil($this->delaiParDomaine) + 60);
        $this->cache->save($item);
    }
}

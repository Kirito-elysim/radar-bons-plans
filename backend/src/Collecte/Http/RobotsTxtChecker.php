<?php

declare(strict_types=1);

namespace App\Collecte\Http;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Vérifie qu'une URL est autorisée par le robots.txt de son site (mis en cache 24 h).
 */
class RobotsTxtChecker
{
    private const DUREE_CACHE = 86400;
    /** Erreur serveur : on considère tout interdit mais on réessaie plus tôt. */
    private const DUREE_CACHE_ERREUR = 3600;

    /** @var array<string, RobotsTxtRegles> */
    private array $memoire = [];

    private readonly string $produit;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CacheItemPoolInterface $cache,
        string $userAgent,
        private readonly LoggerInterface $collecteLogger,
    ) {
        // Jeton produit = partie du User-Agent avant le premier « / » ou espace.
        $this->produit = (string) preg_replace('#[/\s].*$#', '', trim($userAgent));
    }

    public function estAutorise(string $url): bool
    {
        $parties = parse_url($url);
        if (false === $parties || !isset($parties['host'])) {
            return false;
        }

        $origine = ($parties['scheme'] ?? 'https').'://'.strtolower($parties['host']).(isset($parties['port']) ? ':'.$parties['port'] : '');
        $chemin = ($parties['path'] ?? '/').(isset($parties['query']) ? '?'.$parties['query'] : '');

        return $this->regles($origine)->estAutorise($chemin, $this->produit);
    }

    private function regles(string $origine): RobotsTxtRegles
    {
        if (isset($this->memoire[$origine])) {
            return $this->memoire[$origine];
        }

        $item = $this->cache->getItem('robots.'.sha1($origine));
        if ($item->isHit() && \is_string($contenu = $item->get())) {
            return $this->memoire[$origine] = self::depuisCache($contenu);
        }

        [$regles, $brut, $duree] = $this->telecharger($origine);
        $item->set($brut)->expiresAfter($duree);
        $this->cache->save($item);

        return $this->memoire[$origine] = $regles;
    }

    /**
     * @return array{RobotsTxtRegles, string, int}
     */
    private function telecharger(string $origine): array
    {
        try {
            $reponse = $this->client->request('GET', $origine.'/robots.txt');
            $statut = $reponse->getStatusCode();

            if ($statut >= 200 && $statut < 300) {
                $contenu = $reponse->getContent();

                return [RobotsTxtRegles::parse($contenu), 'ok:'.$contenu, self::DUREE_CACHE];
            }
            if ($statut >= 400 && $statut < 500) {
                // RFC 9309 : robots.txt absent = aucune restriction.
                return [RobotsTxtRegles::toutAutoriser(), 'absent:', self::DUREE_CACHE];
            }
            $this->collecteLogger->warning('robots.txt indisponible (HTTP {statut}) pour {origine} : collecte suspendue', ['statut' => $statut, 'origine' => $origine]);
        } catch (HttpException $e) {
            $this->collecteLogger->warning('robots.txt injoignable pour {origine} : {erreur}', ['origine' => $origine, 'erreur' => $e->getMessage()]);
        }

        return [RobotsTxtRegles::toutInterdire(), 'erreur:', self::DUREE_CACHE_ERREUR];
    }

    private static function depuisCache(string $brut): RobotsTxtRegles
    {
        [$etat, $contenu] = explode(':', $brut, 2) + [1 => ''];

        return match ($etat) {
            'ok' => RobotsTxtRegles::parse($contenu),
            'absent' => RobotsTxtRegles::toutAutoriser(),
            default => RobotsTxtRegles::toutInterdire(),
        };
    }
}

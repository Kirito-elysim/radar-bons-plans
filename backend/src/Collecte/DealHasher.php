<?php

declare(strict_types=1);

namespace App\Collecte;

/**
 * Calcule l'empreinte de dédoublonnage d'un bon plan : source + URL normalisée + titre normalisé.
 * Quand la source fournit un identifiant stable (ex. id Shopmium, id Radins), il remplace l'URL et le titre :
 * le bon plan reste le même même si son libellé change.
 */
final class DealHasher
{
    /** Paramètres de suivi retirés des URL avant comparaison. */
    private const PARAMS_SUIVI = ['fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid', 'ref', 'xtor'];

    public function hash(string $codeSource, string $url, string $titre, ?string $idExterne = null): string
    {
        if (null !== $idExterne && '' !== $idExterne) {
            return hash('sha256', $codeSource."\nid:".$idExterne);
        }

        return hash('sha256', implode("\n", [
            $codeSource,
            self::normaliserUrl($url),
            self::normaliserTitre($titre),
        ]));
    }

    /**
     * Schéma et hôte en minuscules, sans fragment, sans paramètres de suivi (utm_*, fbclid…),
     * paramètres restants triés, sans slash final.
     */
    public static function normaliserUrl(string $url): string
    {
        $parties = parse_url(trim($url));
        if (false === $parties || !isset($parties['host'])) {
            return trim($url);
        }

        $schema = strtolower($parties['scheme'] ?? 'https');
        $hote = strtolower($parties['host']);
        $port = isset($parties['port']) ? ':'.$parties['port'] : '';
        $chemin = rtrim($parties['path'] ?? '', '/');

        $requete = '';
        if (isset($parties['query'])) {
            parse_str($parties['query'], $params);
            $params = array_filter(
                $params,
                static fn (int|string $cle): bool => !str_starts_with((string) $cle, 'utm_') && !\in_array($cle, self::PARAMS_SUIVI, true),
                \ARRAY_FILTER_USE_KEY,
            );
            ksort($params);
            if ([] !== $params) {
                $requete = '?'.http_build_query($params);
            }
        }

        return $schema.'://'.$hote.$port.$chemin.$requete;
    }

    /**
     * Minuscules, sans accents, espaces et ponctuation fusionnés.
     */
    public static function normaliserTitre(string $titre): string
    {
        $titre = mb_strtolower(trim($titre));
        $translitere = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if (null !== $translitere) {
            $titre = (string) $translitere->transliterate($titre);
        }

        return trim((string) preg_replace('/[^\p{L}\p{N}%€]+/u', ' ', $titre));
    }
}

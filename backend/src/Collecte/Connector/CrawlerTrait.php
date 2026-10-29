<?php

declare(strict_types=1);

namespace App\Collecte\Connector;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Petits utilitaires de lecture DOM partagés par les connecteurs HTML.
 */
trait CrawlerTrait
{
    /**
     * Texte du premier nœud, espaces fusionnés, ou null s'il est vide/absent.
     */
    private static function texte(Crawler $noeud): ?string
    {
        if (0 === $noeud->count()) {
            return null;
        }
        $texte = trim((string) preg_replace('/\s+/u', ' ', $noeud->first()->text('')));

        return '' !== $texte ? $texte : null;
    }

    private static function attribut(Crawler $noeud, string $nom): ?string
    {
        if (0 === $noeud->count()) {
            return null;
        }
        $valeur = $noeud->first()->attr($nom);
        $valeur = null !== $valeur ? trim(html_entity_decode($valeur, \ENT_QUOTES | \ENT_HTML5, 'UTF-8')) : '';

        return '' !== $valeur ? $valeur : null;
    }

    /**
     * URL absolue HTTPS d'une image (data-src prioritaire pour le lazy-loading), sinon null.
     */
    private static function urlImage(Crawler $img): ?string
    {
        $src = self::attribut($img, 'data-src') ?? self::attribut($img, 'src');

        return null !== $src && str_starts_with($src, 'https://') ? $src : null;
    }

    /**
     * URL sans fragment ni requête.
     */
    private static function urlPropre(?string $href): ?string
    {
        if (null === $href) {
            return null;
        }
        $url = strtok(trim($href), '#?');

        return false !== $url ? $url : null;
    }

    private static function majuscule(string $texte): string
    {
        return mb_strtoupper(mb_substr($texte, 0, 1)).mb_substr($texte, 1);
    }
}

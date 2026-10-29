<?php

declare(strict_types=1);

namespace App\Collecte\Connector\Selectors;

/**
 * Règles génériques de lecture des pages « lien en bio » (Linktree, Beacons, blogs…).
 */
final class LienBioSelectors
{
    /** Linktree (Next.js) : liens dans props.pageProps.links[] {title, url}. */
    public const CSS_NEXT_DATA = 'script#__NEXT_DATA__';
    public const CSS_LIENS = 'a[href]';

    /** Un lien est retenu s'il évoque un bon plan. */
    public const MOTIF_BON_PLAN = '/(promo|code|r[ée]duc|rembours|offre|bon[s]? plan|gratuit|cashback|vente priv|soldes|\d+\s*%|\d+\s*€|-\s*\d+)/iu';

    /** Réseaux sociaux et services techniques : jamais des bons plans. */
    public const DOMAINES_EXCLUS = [
        'tiktok.com', 'instagram.com', 'facebook.com', 'youtube.com', 'youtu.be', 'x.com', 'twitter.com',
        'snapchat.com', 'threads.net', 'pinterest.com', 'linktr.ee', 'beacons.ai', 'spotify.com', 'whatsapp.com', 'telegram.me', 't.me',
    ];
}

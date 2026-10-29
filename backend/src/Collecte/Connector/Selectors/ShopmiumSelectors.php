<?php

declare(strict_types=1);

namespace App\Collecte\Connector\Selectors;

/**
 * Sélecteurs de https://offers.shopmium.com/fr (relevés le 29/10/2026).
 * Les fiches /offers/ sont interdites par le robots.txt : seule la liste /fr est lue.
 */
final class ShopmiumSelectors
{
    public const URL_LISTE = 'https://offers.shopmium.com/fr';

    /** Tuiles d'offres, hors section « Offres terminées ». */
    public const XPATH_TUILES = '//li[contains(concat(" ", normalize-space(@class), " "), " nodesListItem ")][not(ancestor::*[contains(concat(" ", normalize-space(@class), " "), " finished ")])]';
    public const ATTR_ID = 'data-node-id';
    public const ATTR_ONGLET = 'data-tab-name';
    public const CSS_LIEN = 'a.node';
    public const CSS_TITRE = 'h3';
    public const CSS_DESCRIPTION = 'p';
    /** Durée restante : « 3j », « 10h ». */
    public const CSS_FIN = '.offer-end';
    public const CSS_IMAGE = '.node__productImageWrapper img';

    /** Onglets qui ne sont pas des catégories de produits. */
    public const ONGLETS_IGNORES = ['', 'sélection de la semaine'];
    public const MOTIF_ONGLET_CAMPAGNE = '/^d[ée]fi\b/iu';
    public const MOTIF_ONGLET_ENSEIGNE = '/^uniquement chez\s+(.+)$/iu';
    public const MOTIF_ONGLET_CANAL = '/(supermarch|drive|livraison)/iu';
    public const MOTIF_100_REMBOURSE = '/(enti[eè]rement rembours|100\s*%\s*rembours|gratuit)/iu';
}

<?php

declare(strict_types=1);

namespace App\Collecte\Connector\Selectors;

/**
 * Sélecteurs centralisés de https://anti-crise.fr/catalogue/ (relevés le 29/10/2026).
 * Si le site change de structure, c'est le seul fichier à modifier.
 */
final class AntiCriseCatalogueSelectors
{
    public const URL_LISTE = 'https://anti-crise.fr/catalogue/';

    /** Blocs par enseigne : précédés d'un <h2> portant le nom de l'enseigne. */
    public const XPATH_BLOCS_ENSEIGNE = '//div[contains(concat(" ", normalize-space(@class), " "), " block-items-catalogue ")][preceding-sibling::h2]';
    public const XPATH_TITRE_ENSEIGNE = 'preceding-sibling::h2[1]';

    /** Bloc « Notre sélection » (sans enseigne explicite). */
    public const CSS_BLOC_SELECTION = '#catalogue-selection .block-items-catalogue';

    public const CSS_ITEM = 'div.catalogue-item';
    /** Dates « 27/10/2026 - 08/11/2026 » : premier <h6> (sections) ou .item-catalogue-title (sélection). */
    public const CSS_DATES = 'h6:not(.bottom), .item-catalogue-title';
    public const CSS_INTITULE = 'h6.bottom';
    public const CSS_LIEN = 'a.info';
    public const CSS_IMAGE = 'img';
    public const CLASSE_NOUVEAU = 'catalogue-new';

    /** Carrousel d'en-tête (seul endroit où figurent certaines enseignes, ex. Action). */
    public const CSS_CARROUSEL_LIEN = '#carousel li > a';
    public const CSS_CARROUSEL_ENSEIGNE = '.carousel-enseigne';
    public const CSS_CARROUSEL_DATE = '.carousel-date';

    /** Seules les URL de fiches catalogue sont retenues. */
    public const MOTIF_URL_CATALOGUE = '#^https://anti-crise\.fr/catalogue/catalogue-[a-z0-9-]+/?$#';
}

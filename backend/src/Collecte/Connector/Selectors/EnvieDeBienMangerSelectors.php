<?php

declare(strict_types=1);

namespace App\Collecte\Connector\Selectors;

/**
 * Sélecteurs de https://www.enviedebienmanger.fr/bons-de-reduction (relevés le 29/10/2026).
 * 9 bons par page, pagination Drupal « ?page=,N » (autorisée par le robots.txt, contrairement
 * aux combinaisons avec « term_node_tid_depth_1 »). Les images sont sous /sites/, interdit aux
 * robots : on n'en garde pas l'URL. Les bons n'ont pas de fiche propre : le lien pointe vers la liste.
 */
final class EnvieDeBienMangerSelectors
{
    public const URL_LISTE = 'https://www.enviedebienmanger.fr/bons-de-reduction';
    public const FORMAT_URL_PAGE = self::URL_LISTE.'?page=%%2C%d';
    /** Garde-fou contre une pagination infinie. */
    public const PAGES_MAX = 15;

    public const CSS_ITEM = '.br--item[data-br-id]';
    public const ATTR_ID = 'data-br-id';
    public const CSS_TITRE = '.product__title';
    public const CSS_MONTANT = '.discount__amount';
    public const CSS_DEVISE = '.discount__currency';
    /** Le logo porte le nom de la marque en attribut alt. */
    public const CSS_LOGO = '.product__logo img';
    public const CSS_DESCRIPTION = '.tooltip__content';
}

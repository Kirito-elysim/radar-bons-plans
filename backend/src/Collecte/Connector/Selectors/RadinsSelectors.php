<?php

declare(strict_types=1);

namespace App\Collecte\Connector\Selectors;

/**
 * Radins.com est une application Next.js : les offres sont lues dans le JSON
 * embarqué <script id="__NEXT_DATA__"> (props.pageProps.widgets[].widget_data.vouchers).
 * On ne suit jamais les liens de redirection (/clickout/, interdits par le robots.txt)
 * et on ne stocke pas les codes eux-mêmes : on renvoie vers la page marchand de Radins.
 */
final class RadinsSelectors
{
    public const BASE = 'https://www.radins.com/';
    public const URLS = [
        'https://www.radins.com/code-promo/',
        'https://www.radins.com/',
    ];

    public const CSS_NEXT_DATA = 'script#__NEXT_DATA__';
    /** Types d'offres qui nécessitent un code (« unique » = code personnel). */
    public const TYPES_CODE = ['code', 'unique'];
}

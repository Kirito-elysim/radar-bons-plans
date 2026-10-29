<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type de notification envoyée, journalisé pour éviter les doublons.
 */
enum TypeAlerte: string
{
    case ExpirationJ3 = 'expiration_j3';
    case ExpirationJ1 = 'expiration_j1';
    case NouveauCatalogue = 'nouveau_catalogue';
    case RecapQuotidien = 'recap_quotidien';
    case ConnecteurVide = 'connecteur_vide';
}

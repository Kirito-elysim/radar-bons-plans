<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Plateforme sociale d'un créateur de contenu suivi.
 */
enum Plateforme: string
{
    case TikTok = 'tiktok';
    case Instagram = 'instagram';
    case YouTube = 'youtube';
}

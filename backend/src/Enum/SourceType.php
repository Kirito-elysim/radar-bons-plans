<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Mode d'alimentation d'une source.
 */
enum SourceType: string
{
    /** Lecture de pages HTML publiques (dans le respect du robots.txt). */
    case Scraping = 'scraping';
    /** Bons plans saisis à la main (ex. vidéos TikTok). */
    case SaisieManuelle = 'saisie_manuelle';
    /** Pages « lien en bio » (Linktree, blogs…) des créateurs suivis. */
    case LienBio = 'lien_bio';
    /** API officielle d'un partenaire. */
    case Api = 'api';
}

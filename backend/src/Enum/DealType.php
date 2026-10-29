<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Grande famille d'un bon plan, utilisée pour les sections du tableau de bord.
 */
enum DealType: string
{
    case Catalogue = 'catalogue';
    case Remboursement = 'remboursement';
    case Bon = 'bon';
    case CodePromo = 'code_promo';
    case Shopmium = 'shopmium';
    case TikTok = 'tiktok';

    public function libelle(): string
    {
        return match ($this) {
            self::Catalogue => 'Catalogue',
            self::Remboursement => 'Offre de remboursement',
            self::Bon => 'Bon de réduction',
            self::CodePromo => 'Code promo',
            self::Shopmium => 'Shopmium',
            self::TikTok => 'TikTok',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Sous-type d'une offre de remboursement (classification Anti-crise.fr).
 */
enum RemboursementType: string
{
    case Rembourse100 = 'rembourse_100';
    case Partiel = 'partiel';
    case BonAchat = 'bon_achat';
    case OffreEssai = 'offre_essai';
    case OffreSpecifique = 'offre_specifique';

    public function libelle(): string
    {
        return match ($this) {
            self::Rembourse100 => '100 % remboursé',
            self::Partiel => 'Remboursement partiel',
            self::BonAchat => "Bon d'achat",
            self::OffreEssai => "Offre d'essai",
            self::OffreSpecifique => 'Offre spécifique',
        };
    }
}

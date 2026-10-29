<?php

declare(strict_types=1);

namespace App\Collecte\Connector\Selectors;

use App\Enum\RemboursementType;

/**
 * Sélecteurs de https://anti-crise.fr/les-offres-de-remboursement/ (relevés le 29/10/2026).
 * Le filtre « ?remboursement-type= » est appliqué côté navigateur : la page complète suffit.
 */
final class AntiCriseRemboursementSelectors
{
    public const URL_LISTE = 'https://anti-crise.fr/les-offres-de-remboursement/';

    public const CSS_ITEM = 'div.catalogue-item';
    public const CSS_LIEN = 'a.info';
    /** L'attribut title de l'image vaut « Offre de Remboursement {marque} : {titre} ». */
    public const CSS_IMAGE = 'a.info img';
    public const CSS_MARQUE = 'h6.bottom strong';
    public const CSS_TYPE = 'h6.bottom .offer-type';
    /** Contient « Fin le 14/02/2027 ». */
    public const CSS_PIED = 'h6.bottom';
    public const CSS_ICONE = 'img.icone';

    public const PREFIXE_TITRE = '#^offre de remboursement\s+.+?\s*:\s*#iu';
    public const MOTIF_URL = '#^https://anti-crise\.fr/les-offres-de-remboursement/[a-z0-9-]+/?$#';

    /** Classe CSS du badge → sous-type. */
    public const TYPES = [
        '100-rembourse' => RemboursementType::Rembourse100,
        'rembourse-partiellement' => RemboursementType::Partiel,
        'rembourse-en-bon-d-achat' => RemboursementType::BonAchat,
        'offre-d-essai' => RemboursementType::OffreEssai,
        'offre-specifique' => RemboursementType::OffreSpecifique,
    ];

    /** Nom du fichier d'icône → catégorie affichée. */
    public const CATEGORIES = [
        'alimentation-boisson' => 'Alimentation & boissons',
        'auto-moto' => 'Auto & moto',
        'bricolage-jardin-animaux' => 'Bricolage, jardin & animaux',
        'fournitures-de-bureau-scolaires' => 'Fournitures scolaires & bureau',
        'gros-electromenager' => 'Gros électroménager',
        'multimedia' => 'Multimédia',
        'petit-electromenager' => 'Petit électroménager',
        'produits-d-entretien' => "Produits d'entretien",
        'puericulture' => 'Puériculture',
        'sante-beaute-hygiene' => 'Santé, beauté & hygiène',
        'sport-loisirs-jeux' => 'Sport, loisirs & jeux',
        'telephonie' => 'Téléphonie',
        'autre' => 'Autre',
    ];
}

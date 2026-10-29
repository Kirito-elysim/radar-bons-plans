<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enum\DealType;
use App\Enum\RemboursementType;

/**
 * Critères de recherche des bons plans (tableau de bord, API, récap quotidien).
 */
final class CriteresBonsPlans
{
    public const TRI_DATE_FIN = 'dateFin';
    public const TRI_RECENTS = 'recents';
    public const TRI_VALEUR = 'valeur';

    /**
     * @param list<string>            $enseignes
     * @param list<DealType>          $types
     * @param list<RemboursementType> $sousTypes
     * @param list<string>            $categories
     */
    public function __construct(
        public array $enseignes = [],
        public array $types = [],
        public array $sousTypes = [],
        public array $categories = [],
        /** Uniquement les bons plans repérés depuis hier 00:00. */
        public bool $nouveaux = false,
        public ?string $texte = null,
        /** Uniquement ceux qui finissent dans N jours au plus. */
        public ?int $expireSousJours = null,
        public bool $inclureExpires = false,
        /** Uniquement les catalogues régionaux de cette zone (filtre explicite). */
        public ?string $region = null,
        public string $tri = self::TRI_DATE_FIN,
        /** @var list<int>|null restreint à ces identifiants (ex. favoris) */
        public ?array $ids = null,
        /** Zone de l'utilisateur : exclut les catalogues régionaux des autres zones (null = toutes). */
        public ?string $zone = null,
    ) {
    }
}

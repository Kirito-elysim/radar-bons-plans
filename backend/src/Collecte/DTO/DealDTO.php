<?php

declare(strict_types=1);

namespace App\Collecte\DTO;

use App\Enum\DealType;
use App\Enum\RemboursementType;

/**
 * Bon plan tel que renvoyé par un connecteur, avant persistance.
 * Les montants sont des chaînes décimales (« 0.50 ») pour éviter les flottants.
 */
final readonly class DealDTO
{
    public function __construct(
        public DealType $type,
        public string $titre,
        public string $url,
        public ?string $enseigne = null,
        public ?string $marque = null,
        public ?RemboursementType $sousType = null,
        public ?string $descriptionCourte = null,
        public ?string $categorie = null,
        public ?string $valeurEuros = null,
        public ?string $valeurPourcent = null,
        public ?string $condition = null,
        public ?\DateTimeImmutable $dateDebut = null,
        public ?\DateTimeImmutable $dateFin = null,
        public bool $dateFinEstimee = false,
        public bool $exclusifAppli = false,
        public ?string $region = null,
        public ?string $imageUrl = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Social;

/**
 * Informations complétées par l'utilisateur pour un bon plan repéré dans une vidéo.
 */
final readonly class SaisieBonPlanVideo
{
    public function __construct(
        public string $produit,
        public ?string $enseigne = null,
        /** Économie en euros, saisie libre (« 2,50 », « 3 € »). */
        public ?string $economie = null,
        public ?\DateTimeImmutable $dateFin = null,
        public ?string $condition = null,
    ) {
    }
}

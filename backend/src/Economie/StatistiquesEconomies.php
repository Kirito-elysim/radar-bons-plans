<?php

declare(strict_types=1);

namespace App\Economie;

/**
 * Résultat du calcul des économies d'un utilisateur. Montants en chaînes décimales (« 12.50 »).
 */
final readonly class StatistiquesEconomies
{
    /**
     * @param list<array{mois: string, total: string, nombre: int}>     $parMois     12 derniers mois, du plus ancien au plus récent
     * @param list<array{enseigne: string, total: string, nombre: int}> $parEnseigne du plus gros total au plus petit
     * @param list<array{type: string, libelle: string, total: string, nombre: int}> $parType
     */
    public function __construct(
        public string $total,
        public int $nombre,
        public string $totalMois,
        public string $totalMoisPrecedent,
        public string $totalAnnee,
        public string $totalSemaine,
        public string $moyenneMensuelle,
        public ?string $meilleurMois,
        public array $parMois,
        public array $parEnseigne,
        public array $parType,
    ) {
    }
}

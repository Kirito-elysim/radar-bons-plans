<?php

declare(strict_types=1);

namespace App\Collecte;

use App\Enum\RunStatus;

/**
 * Bilan d'une exécution de connecteur.
 */
final readonly class RapportCollecte
{
    /**
     * @param list<string> $pagesIgnorees
     */
    public function __construct(
        public string $codeSource,
        public RunStatus $statut,
        public int $nbRecus = 0,
        public int $nbNouveaux = 0,
        public int $nbMisAJour = 0,
        public array $pagesIgnorees = [],
        public ?string $erreur = null,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Collecte\Event;

use App\Entity\Source;

/**
 * Émis quand un connecteur s'exécute sans erreur mais renvoie 0 résultat :
 * le site a probablement changé de structure. Écouté par les alertes administrateur.
 */
final readonly class ConnecteurVideEvent
{
    public function __construct(public Source $source)
    {
    }
}

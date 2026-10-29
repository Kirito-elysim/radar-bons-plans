<?php

declare(strict_types=1);

namespace App\Collecte;

/**
 * Pour un connecteur dont le résultat peut légitimement être vide (ex. aucun lien en bio
 * renseigné) : évite une fausse alerte « site modifié ».
 */
interface VideAttenduInterface
{
    public function resultatVideAttendu(): bool;
}

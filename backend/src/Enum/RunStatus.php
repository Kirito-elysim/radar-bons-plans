<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Résultat de la dernière exécution d'un connecteur.
 */
enum RunStatus: string
{
    case EnCours = 'en_cours';
    case Succes = 'succes';
    /** Exécution sans erreur mais 0 résultat : le site a probablement changé. */
    case Vide = 'vide';
    case Erreur = 'erreur';
    /** Une ou plusieurs pages ignorées (robots.txt, erreur HTTP ponctuelle). */
    case Partiel = 'partiel';
}

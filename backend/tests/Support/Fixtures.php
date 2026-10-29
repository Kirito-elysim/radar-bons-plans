<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Accès aux pages HTML enregistrées dans tests/fixtures/.
 */
final class Fixtures
{
    public static function lire(string $chemin): string
    {
        $fichier = __DIR__.'/../fixtures/'.$chemin;
        $contenu = file_get_contents($fichier);
        if (false === $contenu) {
            throw new \RuntimeException('Fixture introuvable : '.$fichier);
        }

        return $contenu;
    }
}

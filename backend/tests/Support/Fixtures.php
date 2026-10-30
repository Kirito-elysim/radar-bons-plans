<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\TestCase;

/**
 * Accès aux pages HTML enregistrées dans tests/fixtures/.
 */
final class Fixtures
{
    /**
     * Page réelle d'un site source (tests/fixtures/reelles/, non versionné : droits des sites).
     * Absente (CI, clone public) : le test est ignoré, les fixtures synthétiques couvrent la structure.
     */
    public static function reelle(string $chemin): string
    {
        $fichier = __DIR__.'/../fixtures/reelles/'.$chemin;
        if (!is_file($fichier)) {
            TestCase::markTestSkipped('Page réelle non disponible : '.$chemin.' (app:fixtures:capturer pour la créer en local).');
        }

        return self::lire('reelles/'.$chemin);
    }

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

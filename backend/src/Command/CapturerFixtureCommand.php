<?php

declare(strict_types=1);

namespace App\Command;

use App\Collecte\Http\PoliteHttpClient;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Enregistre une page réelle dans tests/fixtures/ (via le client poli : robots.txt et throttling respectés).
 * Scripts, styles, iframes et publicités sont retirés pour ne garder que la structure utile au parsing ;
 * les scripts de données JSON (__NEXT_DATA__, application/json, ld+json) sont conservés.
 */
#[AsCommand(name: 'app:fixtures:capturer', description: 'Enregistre une page source comme fixture HTML de test')]
final class CapturerFixtureCommand
{
    public function __construct(
        private readonly PoliteHttpClient $http,
        #[Autowire('%kernel.project_dir%/tests/fixtures')]
        private readonly string $dossierFixtures,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'URL de la page')]
        string $url,
        #[Argument(description: 'Chemin relatif dans tests/fixtures, ex. reelles/anti-crise/catalogues.html (dossier non versionné)')]
        string $fichier,
    ): int {
        $html = self::alleger($this->http->get($url));
        $cible = $this->dossierFixtures.'/'.ltrim($fichier, '/');

        if (!is_dir(\dirname($cible)) && !mkdir(\dirname($cible), 0o775, true)) {
            $io->error('Impossible de créer le dossier '.\dirname($cible));

            return Command::FAILURE;
        }
        file_put_contents($cible, \sprintf("<!-- Fixture capturée le %s depuis %s -->\n%s", date('Y-m-d'), $url, $html));
        $io->success(\sprintf('%s enregistré (%d Ko).', $cible, intdiv(\strlen($html), 1024)));

        return Command::SUCCESS;
    }

    public static function alleger(string $html): string
    {
        $html = (string) preg_replace([
            '#<script\b(?![^>]*(?:__NEXT_DATA__|application/(?:ld\+)?json))[^>]*>.*?</script>#is',
            '#<style\b[^>]*>.*?</style>#is',
            '#<noscript\b[^>]*>.*?</noscript>#is',
            '#<iframe\b[^>]*>.*?</iframe>#is',
            '#<ins\b[^>]*>.*?</ins>#is',
            '#<svg\b[^>]*>.*?</svg>#is',
            '#<link\b[^>]*>#i',
            '#<!--(?!\s*Fixture).*?-->#s',
        ], '', $html);

        // Espaces superflus (la structure est conservée).
        return (string) preg_replace(["/[ \t]+/", "/\n\s*\n+/"], [' ', "\n"], $html);
    }
}

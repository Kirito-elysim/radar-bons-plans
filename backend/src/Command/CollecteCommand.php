<?php

declare(strict_types=1);

namespace App\Command;

use App\Collecte\CollecteOrchestrator;
use App\Collecte\RapportCollecte;
use App\Enum\RunStatus;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:collecte', description: 'Collecte les bons plans d\'une source (ou de toutes les sources actives)')]
final class CollecteCommand
{
    public function __construct(private readonly CollecteOrchestrator $orchestrateur)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Code de la source (ex. anti_crise_catalogue). Vide = toutes les sources actives.')]
        ?string $source = null,
        #[Option(description: 'Affiche les résultats sans rien enregistrer', name: 'dry-run')]
        bool $simulation = false,
    ): int {
        $rapports = null !== $source
            ? [$this->orchestrateur->executer($source, $simulation)]
            : $this->orchestrateur->executerTout();

        $io->table(
            ['Source', 'Statut', 'Reçus', 'Nouveaux', 'Mis à jour', 'Pages ignorées', 'Erreur'],
            array_map(static fn (RapportCollecte $r): array => [
                $r->codeSource, $r->statut->value, $r->nbRecus, $r->nbNouveaux, $r->nbMisAJour,
                \count($r->pagesIgnorees), $r->erreur ?? '',
            ], $rapports),
        );
        if ($simulation) {
            $io->note('Mode simulation : rien n\'a été enregistré.');
        }

        $enEchec = array_filter($rapports, static fn (RapportCollecte $r): bool => \in_array($r->statut, [RunStatus::Erreur, RunStatus::Vide], true));

        return [] === $enEchec ? Command::SUCCESS : Command::FAILURE;
    }
}

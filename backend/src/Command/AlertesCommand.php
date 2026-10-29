<?php

declare(strict_types=1);

namespace App\Command;

use App\Alerte\AlerteExpirationService;
use App\Alerte\NouveauCatalogueAlerteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:alertes', description: 'Lance les alertes J-3 / J-1 et « nouveau catalogue » (sans doublon)')]
final class AlertesCommand
{
    public function __construct(
        private readonly AlerteExpirationService $expirations,
        private readonly NouveauCatalogueAlerteService $catalogues,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf(
            'Alertes envoyées : %d expiration(s), %d nouveau(x) catalogue(s) (par utilisateur et par canal).',
            $this->expirations->executer(),
            $this->catalogues->executer(),
        ));

        return Command::SUCCESS;
    }
}

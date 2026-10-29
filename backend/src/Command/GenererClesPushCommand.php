<?php

declare(strict_types=1);

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:push:generer-cles', description: 'Génère une paire de clés VAPID pour le Web Push')]
final class GenererClesPushCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $cles = VAPID::createVapidKeys();
        $io->writeln('À ajouter dans backend/.env.local (ou dans les variables Coolify) :');
        $io->newLine();
        $io->writeln('VAPID_PUBLIC_KEY='.$cles['publicKey']);
        $io->writeln('VAPID_PRIVATE_KEY='.$cles['privateKey']);
        $io->newLine();
        $io->note('Gardez la clé privée secrète. En changer invalide les abonnements push existants.');

        return Command::SUCCESS;
    }
}

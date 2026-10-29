<?php

declare(strict_types=1);

namespace App\Command;

use App\Alerte\RecapQuotidienBuilder;
use App\Alerte\RecapQuotidienService;
use App\Notification\Canal\TelegramCanal;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:recap:quotidien', description: 'Envoie les récaps dus, ou celui d\'un utilisateur (--apercu pour l\'afficher sans l\'envoyer)')]
final class RecapCommand
{
    public function __construct(
        private readonly RecapQuotidienService $recaps,
        private readonly RecapQuotidienBuilder $builder,
        private readonly UserRepository $users,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'E-mail de l\'utilisateur (vide = tous ceux dont l\'heure est passée)')]
        ?string $email = null,
        #[Option(description: 'Affiche le récap sans l\'envoyer')]
        bool $apercu = false,
        #[Option(description: 'Renvoie même si le récap du jour est déjà parti')]
        bool $forcer = false,
    ): int {
        if (null === $email) {
            foreach ($this->recaps->envoyerRecapsDus() as $destinataire => $canaux) {
                $io->writeln(\sprintf('%s : %s', $destinataire, implode(', ', $canaux)));
            }
            $io->success('Récaps dus envoyés.');

            return Command::SUCCESS;
        }

        $user = $this->users->loadUserByIdentifier($email);
        if (null === $user) {
            $io->error('Utilisateur introuvable : '.$email);

            return Command::FAILURE;
        }
        if ($apercu) {
            $io->writeln(strip_tags(TelegramCanal::formater($this->builder->construire($user, new \DateTimeImmutable()))));

            return Command::SUCCESS;
        }

        $canaux = $this->recaps->envoyer($user, forcer: $forcer);
        [] === $canaux
            ? $io->warning('Rien envoyé (déjà parti aujourd\'hui ? canaux non configurés ?). Utilisez --forcer pour renvoyer.')
            : $io->success('Récap envoyé : '.implode(', ', $canaux));

        return Command::SUCCESS;
    }
}

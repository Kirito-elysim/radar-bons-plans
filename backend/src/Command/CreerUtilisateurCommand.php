<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:utilisateur:creer', description: 'Crée un compte (ou change son mot de passe), éventuellement administrateur')]
final class CreerUtilisateurCommand
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse e-mail')]
        string $email,
        #[Option(description: 'Donne le rôle administrateur')]
        bool $admin = false,
    ): int {
        $motDePasse = $io->askHidden('Mot de passe (10 caractères minimum)');
        if (!\is_string($motDePasse) || mb_strlen($motDePasse) < 10) {
            $io->error('Mot de passe trop court.');

            return Command::FAILURE;
        }

        $user = $this->users->findOneBy(['email' => mb_strtolower(trim($email))]);
        $nouveau = null === $user;
        $user ??= new User($email);
        $user->setPassword($this->hasher->hashPassword($user, $motDePasse));
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $this->em->persist($user);
        $this->em->flush();

        $io->success(\sprintf('%s %s%s.', $nouveau ? 'Compte créé :' : 'Mot de passe mis à jour :', $user->getEmail(), $user->estAdmin() ? ' (administrateur)' : ''));

        return Command::SUCCESS;
    }
}

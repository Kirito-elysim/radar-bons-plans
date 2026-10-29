<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Inscription;
use App\ApiResource\Profil;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Inscription, Profil>
 */
final class InscriptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        #[Autowire('%env(bool:INSCRIPTIONS_OUVERTES)%')]
        private readonly bool $inscriptionsOuvertes,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Profil
    {
        if (!$this->inscriptionsOuvertes) {
            throw new AccessDeniedHttpException('Les inscriptions sont fermées.');
        }

        $email = mb_strtolower(trim($data->email));
        if (null !== $this->users->findOneBy(['email' => $email])) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation('Un compte existe déjà avec cette adresse e-mail.', null, [], $data, 'email', $data->email),
            ]));
        }

        $user = (new User($email))->setPseudo($data->pseudo);
        $user->setPassword($this->hasher->hashPassword($user, $data->motDePasse));
        $this->em->persist($user);
        $this->em->flush();

        return Profil::depuis($user);
    }
}

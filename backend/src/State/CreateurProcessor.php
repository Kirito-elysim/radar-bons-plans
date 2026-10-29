<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Createur;
use App\Entity\WatchedCreator;
use App\Enum\Plateforme;
use App\Repository\WatchedCreatorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Createur, Createur>
 */
final class CreateurProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly WatchedCreatorRepository $createurs,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Createur
    {
        $plateforme = Plateforme::from($data->plateforme);
        $pseudo = ltrim(trim($data->pseudo), '@');

        if ($operation instanceof Post) {
            if (null !== $this->createurs->findOneBy(['plateforme' => $plateforme, 'pseudo' => $pseudo])) {
                throw new ValidationException(new ConstraintViolationList([new ConstraintViolation('Ce créateur est déjà suivi.', null, [], $data, 'pseudo', $data->pseudo)]));
            }
            $createur = new WatchedCreator($pseudo, $plateforme, $data->urlProfil ?? self::urlProfil($plateforme, $pseudo));
            $this->em->persist($createur);
        } else {
            $createur = $this->createurs->find((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Créateur introuvable.');
        }

        $createur->setLienBio(null !== $data->lienBio && '' !== $data->lienBio ? $data->lienBio : null)
            ->setActif($data->actif);
        $this->em->flush();

        return CreateurProvider::versRessource($createur);
    }

    private static function urlProfil(Plateforme $plateforme, string $pseudo): string
    {
        return match ($plateforme) {
            Plateforme::TikTok => 'https://www.tiktok.com/@'.$pseudo,
            Plateforme::Instagram => 'https://www.instagram.com/'.$pseudo,
            Plateforme::YouTube => 'https://www.youtube.com/@'.$pseudo,
        };
    }
}

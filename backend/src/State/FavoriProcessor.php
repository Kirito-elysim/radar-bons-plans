<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\Favori;
use App\Entity\Favorite;
use App\Repository\DealRepository;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<Favori, Favori|null>
 */
final class FavoriProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FavoriteRepository $favoris,
        private readonly DealRepository $deals,
        private readonly UtilisateurCourant $utilisateur,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Favori
    {
        $user = $this->utilisateur->get();
        $existant = $this->favoris->findOneBy(['user' => $user, 'deal' => (int) $data->bonPlanId]);

        if ($operation instanceof DeleteOperationInterface) {
            if (null !== $existant) {
                $this->em->remove($existant);
                $this->em->flush();
            }

            return null;
        }

        if (null === $existant) {
            $deal = $this->deals->find((int) $data->bonPlanId) ?? throw new NotFoundHttpException('Bon plan introuvable.');
            $existant = new Favorite($user, $deal);
            $this->em->persist($existant);
            $this->em->flush();
        }

        return FavoriProvider::versRessource($existant);
    }
}

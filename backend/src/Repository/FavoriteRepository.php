<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * Identifiants des bons plans favoris de l'utilisateur, éventuellement limités à une liste.
     *
     * @param list<int>|null $parmi
     *
     * @return array<int, true>
     */
    public function idsDealsFavoris(User $user, ?array $parmi = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->select('IDENTITY(f.deal) AS id')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user);
        if (null !== $parmi) {
            if ([] === $parmi) {
                return [];
            }
            $qb->andWhere('f.deal IN (:parmi)')->setParameter('parmi', $parmi);
        }

        $ids = [];
        foreach ($qb->getQuery()->getSingleColumnResult() as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }
}

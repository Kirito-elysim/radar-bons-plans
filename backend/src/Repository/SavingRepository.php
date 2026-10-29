<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Saving;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Saving>
 */
class SavingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Saving::class);
    }

    /**
     * Total des économies d'un utilisateur, éventuellement borné (dates incluses). Chaîne décimale.
     */
    public function total(User $user, ?\DateTimeImmutable $du = null, ?\DateTimeImmutable $au = null): string
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.montant), 0)')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user);

        if (null !== $du) {
            $qb->andWhere('s.date >= :du')->setParameter('du', $du->setTime(0, 0), Types::DATE_IMMUTABLE);
        }
        if (null !== $au) {
            $qb->andWhere('s.date <= :au')->setParameter('au', $au->setTime(0, 0), Types::DATE_IMMUTABLE);
        }

        $somme = (string) $qb->getQuery()->getSingleScalarResult();

        return is_numeric($somme) ? bcadd($somme, '0', 2) : '0.00';
    }
}

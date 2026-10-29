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

    /**
     * Économies d'un utilisateur, des plus récentes aux plus anciennes.
     *
     * @return array{elements: list<Saving>, total: int}
     */
    public function lister(User $user, ?\DateTimeImmutable $du, ?\DateTimeImmutable $au, int $offset = 0, int $limite = 30): array
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.date', 'DESC')
            ->addOrderBy('s.id', 'DESC');
        if (null !== $du) {
            $qb->andWhere('s.date >= :du')->setParameter('du', $du->setTime(0, 0), Types::DATE_IMMUTABLE);
        }
        if (null !== $au) {
            $qb->andWhere('s.date <= :au')->setParameter('au', $au->setTime(0, 0), Types::DATE_IMMUTABLE);
        }

        $total = (int) (clone $qb)->select('COUNT(s.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        /** @var list<Saving> $elements */
        $elements = $qb->setFirstResult($offset)->setMaxResults($limite)->getQuery()->getResult();

        return ['elements' => $elements, 'total' => $total];
    }

    /**
     * Toutes les économies d'un utilisateur (statistiques, export), des plus récentes aux plus anciennes.
     *
     * @return list<Saving>
     */
    public function toutes(User $user, ?\DateTimeImmutable $du = null, ?\DateTimeImmutable $au = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->addSelect('d')
            ->leftJoin('s.deal', 'd')
            ->andWhere('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.date', 'DESC')
            ->addOrderBy('s.id', 'DESC');
        if (null !== $du) {
            $qb->andWhere('s.date >= :du')->setParameter('du', $du->setTime(0, 0), Types::DATE_IMMUTABLE);
        }
        if (null !== $au) {
            $qb->andWhere('s.date <= :au')->setParameter('au', $au->setTime(0, 0), Types::DATE_IMMUTABLE);
        }

        /** @var list<Saving> */
        return $qb->getQuery()->getResult();
    }
}

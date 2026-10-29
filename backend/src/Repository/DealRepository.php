<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deal;
use App\Enum\RemboursementType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deal>
 */
class DealRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deal::class);
    }

    public function findOneByHash(string $hash): ?Deal
    {
        return $this->findOneBy(['hashUnicite' => $hash]);
    }

    /**
     * Offres en cours qui se terminent dans les $jours prochains jours (bornes incluses), triées par date de fin.
     *
     * @return list<Deal>
     */
    public function findExpirantSous(int $jours, \DateTimeImmutable $aujourdhui): array
    {
        $debut = $aujourdhui->setTime(0, 0);

        /** @var list<Deal> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.expire = false')
            ->andWhere('d.dateFin BETWEEN :debut AND :fin')
            ->setParameter('debut', $debut, Types::DATE_IMMUTABLE)
            ->setParameter('fin', $debut->modify(\sprintf('+%d days', $jours)), Types::DATE_IMMUTABLE)
            ->orderBy('d.dateFin', 'ASC')
            ->addOrderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre d'offres 100 % remboursées encore valables.
     */
    public function compterRembourse100EnCours(\DateTimeImmutable $aujourdhui): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.expire = false')
            ->andWhere('d.sousType = :sousType')
            ->andWhere('d.dateFin IS NULL OR d.dateFin >= :jour')
            ->setParameter('sousType', RemboursementType::Rembourse100)
            ->setParameter('jour', $aujourdhui->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Économie potentielle : somme des montants connus des offres en cours (chaîne décimale).
     */
    public function sommeValeurEnCours(\DateTimeImmutable $aujourdhui): string
    {
        $somme = $this->createQueryBuilder('d')
            ->select('COALESCE(SUM(d.valeurEuros), 0)')
            ->andWhere('d.expire = false')
            ->andWhere('d.dateFin IS NULL OR d.dateFin >= :jour')
            ->setParameter('jour', $aujourdhui->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();
        $somme = (string) $somme;

        return is_numeric($somme) ? bcadd($somme, '0', 2) : '0.00';
    }

    /**
     * Marque comme expirées les offres dont la date de fin est dépassée. Retourne le nombre de lignes modifiées.
     */
    public function marquerExpires(\DateTimeImmutable $aujourdhui): int
    {
        return (int) $this->createQueryBuilder('d')
            ->update()
            ->set('d.expire', 'true')
            ->andWhere('d.expire = false')
            ->andWhere('d.dateFin < :jour')
            ->setParameter('jour', $aujourdhui->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les offres expirées depuis plus de $joursConservation jours, sauf celles liées à une économie.
     */
    public function purgerExpires(\DateTimeImmutable $aujourdhui, int $joursConservation = 30): int
    {
        $limite = $aujourdhui->setTime(0, 0)->modify(\sprintf('-%d days', $joursConservation));

        return (int) $this->getEntityManager()->createQuery(
            'DELETE FROM App\Entity\Deal d
             WHERE d.expire = true AND d.dateFin < :limite
               AND NOT EXISTS (SELECT s.id FROM App\Entity\Saving s WHERE s.deal = d)'
        )
            ->setParameter('limite', $limite, Types::DATE_IMMUTABLE)
            ->execute();
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Deal;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * Recherche paginée.
     *
     * @return array{elements: list<Deal>, total: int}
     */
    public function rechercher(CriteresBonsPlans $c, \DateTimeImmutable $aujourdhui, int $offset = 0, int $limite = 30): array
    {
        $qb = $this->requeteRecherche($c, $aujourdhui)
            ->setFirstResult($offset)
            ->setMaxResults($limite);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        /** @var list<Deal> $elements */
        $elements = iterator_to_array($paginator->getIterator(), false);

        return ['elements' => $elements, 'total' => \count($paginator)];
    }

    public function compter(CriteresBonsPlans $c, \DateTimeImmutable $aujourdhui): int
    {
        return (int) $this->requeteRecherche($c, $aujourdhui)
            ->select('COUNT(d.id)')
            ->resetDQLPart('orderBy')
            ->resetDQLPart('join')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Bons plans dont le début ou la fin tombe dans l'intervalle (calendrier).
     *
     * @return list<Deal>
     */
    public function findDebutOuFinEntre(\DateTimeImmutable $du, \DateTimeImmutable $au, ?string $zone = null): array
    {
        $qb = $this->createQueryBuilder('d');
        self::filtrerZone($qb, $zone);

        /** @var list<Deal> */
        return $qb
            ->addSelect('s')
            ->join('d.source', 's')
            ->andWhere('(d.dateDebut BETWEEN :du AND :au) OR (d.dateFin BETWEEN :du AND :au)')
            ->setParameter('du', $du->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->setParameter('au', $au->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('d.dateFin', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Garde les bons plans nationaux et ceux de la zone ; écarte les catalogues régionaux des autres zones.
     */
    private static function filtrerZone(QueryBuilder $qb, ?string $zone): void
    {
        if (null !== $zone) {
            $qb->andWhere("d.region IS NULL OR CONCAT(',', d.region, ',') LIKE :zone")->setParameter('zone', '%,'.$zone.',%');
        }
    }

    private function requeteRecherche(CriteresBonsPlans $c, \DateTimeImmutable $aujourdhui): QueryBuilder
    {
        $jour = $aujourdhui->setTime(0, 0);
        $qb = $this->createQueryBuilder('d')
            ->addSelect('s')
            ->join('d.source', 's');

        if (!$c->inclureExpires) {
            $qb->andWhere('d.expire = false')
                ->andWhere('d.dateFin IS NULL OR d.dateFin >= :jour')
                ->setParameter('jour', $jour, Types::DATE_IMMUTABLE);
        }
        if ([] !== $c->enseignes) {
            // L'enseigne peut être portée par « enseigne » (catalogues…) ou « marque » (remboursements, bons).
            $qb->andWhere('LOWER(d.enseigne) IN (:enseignes) OR LOWER(d.marque) IN (:enseignes)')
                ->setParameter('enseignes', array_map('mb_strtolower', $c->enseignes));
        }
        if ([] !== $c->types) {
            $qb->andWhere('d.type IN (:types)')->setParameter('types', $c->types);
        }
        if ([] !== $c->sousTypes) {
            $qb->andWhere('d.sousType IN (:sousTypes)')->setParameter('sousTypes', $c->sousTypes);
        }
        if ([] !== $c->categories) {
            $qb->andWhere('LOWER(d.categorie) IN (:categories)')->setParameter('categories', array_map('mb_strtolower', $c->categories));
        }
        if ($c->nouveaux) {
            $qb->andWhere('d.premiereVueLe >= :hier')->setParameter('hier', $jour->modify('-1 day'));
        }
        if (null !== $c->texte && '' !== trim($c->texte)) {
            $qb->andWhere('LOWER(d.titre) LIKE :texte OR LOWER(d.marque) LIKE :texte OR LOWER(d.enseigne) LIKE :texte OR LOWER(d.descriptionCourte) LIKE :texte')
                ->setParameter('texte', '%'.addcslashes(mb_strtolower(trim($c->texte)), '%_').'%');
        }
        if (null !== $c->expireSousJours) {
            $qb->andWhere('d.dateFin BETWEEN :jourDebut AND :jourLimite')
                ->setParameter('jourDebut', $jour, Types::DATE_IMMUTABLE)
                ->setParameter('jourLimite', $jour->modify(\sprintf('+%d days', max(0, $c->expireSousJours))), Types::DATE_IMMUTABLE);
        }
        if (null !== $c->region) {
            $qb->andWhere("CONCAT(',', d.region, ',') LIKE :region")->setParameter('region', '%,'.$c->region.',%');
        }
        self::filtrerZone($qb, $c->zone);
        if (null !== $c->ids) {
            $qb->andWhere('d.id IN (:ids)')->setParameter('ids', [] !== $c->ids ? $c->ids : [0]);
        }

        // DQL ne connaît pas « NULLS LAST » : on trie d'abord sur un indicateur caché.
        match ($c->tri) {
            CriteresBonsPlans::TRI_RECENTS => $qb->orderBy('d.premiereVueLe', 'DESC'),
            CriteresBonsPlans::TRI_VALEUR => $qb->addSelect('CASE WHEN d.valeurEuros IS NULL THEN 1 ELSE 0 END AS HIDDEN sansValeur')
                ->orderBy('sansValeur', 'ASC')->addOrderBy('d.valeurEuros', 'DESC'),
            default => $qb->addSelect('CASE WHEN d.dateFin IS NULL THEN 1 ELSE 0 END AS HIDDEN sansFin')
                ->orderBy('sansFin', 'ASC')->addOrderBy('d.dateFin', 'ASC'),
        };

        return $qb->addOrderBy('d.id', 'ASC');
    }

    /**
     * Bons plans en cours qui se terminent exactement à cette date.
     *
     * @return list<Deal>
     */
    public function findFinissantLe(\DateTimeImmutable $date): array
    {
        /** @var list<Deal> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.expire = false')
            ->andWhere('d.dateFin = :date')
            ->setParameter('date', $date->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Bons plans repérés depuis une date, encore valables, éventuellement filtrés par type / sous-type.
     *
     * @return list<Deal>
     */
    public function findNouveauxDepuis(\DateTimeImmutable $depuis, \DateTimeImmutable $aujourdhui, ?DealType $type = null, ?RemboursementType $sousType = null, ?string $zone = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->andWhere('d.premiereVueLe >= :depuis')
            ->andWhere('d.expire = false')
            ->andWhere('d.dateFin IS NULL OR d.dateFin >= :jour')
            ->setParameter('depuis', $depuis)
            ->setParameter('jour', $aujourdhui->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->orderBy('d.premiereVueLe', 'DESC')
            ->addOrderBy('d.id', 'ASC');
        if (null !== $type) {
            $qb->andWhere('d.type = :type')->setParameter('type', $type);
        }
        if (null !== $sousType) {
            $qb->andWhere('d.sousType = :sousType')->setParameter('sousType', $sousType);
        }
        self::filtrerZone($qb, $zone);

        /** @var list<Deal> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre de bons plans encore valables par source (identifiant de source → nombre).
     *
     * @return array<int, int>
     */
    public function compterEnCoursParSource(\DateTimeImmutable $aujourdhui): array
    {
        $lignes = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.source) AS source, COUNT(d.id) AS nb')
            ->andWhere('d.expire = false')
            ->andWhere('d.dateFin IS NULL OR d.dateFin >= :jour')
            ->setParameter('jour', $aujourdhui->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->groupBy('d.source')
            ->getQuery()
            ->getArrayResult();

        $resultat = [];
        foreach ($lignes as $l) {
            $resultat[(int) $l['source']] = (int) $l['nb'];
        }

        return $resultat;
    }
}

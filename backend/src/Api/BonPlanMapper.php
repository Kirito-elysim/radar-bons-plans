<?php

declare(strict_types=1);

namespace App\Api;

use App\ApiResource\BonPlan;
use App\Entity\Deal;
use App\Entity\User;
use App\Region\Regions;
use App\Repository\FavoriteRepository;

/**
 * Convertit les entités Deal en ressources BonPlan (calcul des jours restants, urgence, favori).
 */
final class BonPlanMapper
{
    public const SEUIL_CRITIQUE = 3;
    public const SEUIL_PROCHE = 7;

    public function __construct(private readonly FavoriteRepository $favoris)
    {
    }

    /**
     * @param list<Deal> $deals
     *
     * @return list<BonPlan>
     */
    public function versRessources(array $deals, ?User $user, \DateTimeImmutable $aujourdhui): array
    {
        $ids = array_values(array_filter(array_map(static fn (Deal $d): ?int => $d->getId(), $deals)));
        $favoris = null !== $user ? $this->favoris->idsDealsFavoris($user, $ids) : [];

        return array_map(fn (Deal $d): BonPlan => self::versRessource($d, $aujourdhui, isset($favoris[(int) $d->getId()])), $deals);
    }

    public function versRessourceUnique(Deal $deal, ?User $user, \DateTimeImmutable $aujourdhui): BonPlan
    {
        return $this->versRessources([$deal], $user, $aujourdhui)[0];
    }

    public static function versRessource(Deal $d, \DateTimeImmutable $aujourdhui, bool $favori = false): BonPlan
    {
        $b = new BonPlan();
        $b->id = (int) $d->getId();
        $b->type = $d->getType()->value;
        $b->typeLibelle = $d->getType()->libelle();
        $b->sousType = $d->getSousType()?->value;
        $b->sousTypeLibelle = $d->getSousType()?->libelle();
        $b->titre = $d->getTitre();
        $b->enseigne = $d->getEnseigne();
        $b->marque = $d->getMarque();
        $b->description = $d->getDescriptionCourte();
        $b->categorie = $d->getCategorie();
        $b->valeurEuros = $d->getValeurEuros();
        $b->valeurPourcent = $d->getValeurPourcent();
        $b->condition = $d->getCondition();
        $b->dateDebut = $d->getDateDebut()?->format('Y-m-d');
        $b->dateFin = $d->getDateFin()?->format('Y-m-d');
        $b->dateFinEstimee = $d->isDateFinEstimee();
        $b->joursRestants = $d->joursRestants($aujourdhui);
        $b->urgence = self::urgence($b->joursRestants);
        $b->exclusifAppli = $d->isExclusifAppli();
        $b->region = $d->getRegion();
        $b->regionLibelle = Regions::libelle($d->getRegion());
        $b->url = $d->getUrl();
        $b->imageUrl = $d->getImageUrl();
        $b->sourceCode = $d->getSource()->getCode();
        $b->sourceNom = $d->getSource()->getNom();
        $b->createur = $d->getCreateur()?->getPseudo();
        $b->nouveau = $d->getPremiereVueLe() >= $aujourdhui->setTime(0, 0)->modify('-1 day');
        $b->expire = $d->isExpire() || $d->estTermineeAu($aujourdhui);
        $b->favori = $favori;
        $b->premiereVueLe = $d->getPremiereVueLe()->format(\DateTimeInterface::ATOM);

        return $b;
    }

    public static function urgence(?int $joursRestants): ?string
    {
        return match (true) {
            null === $joursRestants || $joursRestants < 0 => null,
            $joursRestants <= self::SEUIL_CRITIQUE => 'critique',
            $joursRestants <= self::SEUIL_PROCHE => 'proche',
            default => null,
        };
    }
}

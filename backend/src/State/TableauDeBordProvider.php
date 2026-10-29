<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\BonPlanMapper;
use App\Api\UtilisateurCourant;
use App\ApiResource\BonPlan;
use App\ApiResource\TableauDeBord;
use App\Enum\DealType;
use App\Repository\CriteresBonsPlans;
use App\Repository\DealRepository;
use App\Repository\SavingRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * @implements ProviderInterface<TableauDeBord>
 */
final class TableauDeBordProvider implements ProviderInterface
{
    private const LIMITE_A_SAISIR = 30;

    public function __construct(
        private readonly DealRepository $deals,
        private readonly SavingRepository $savings,
        private readonly BonPlanMapper $mapper,
        private readonly UtilisateurCourant $utilisateur,
        private readonly ClockInterface $clock,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TableauDeBord
    {
        $user = $this->utilisateur->get();
        $aujourdhui = $this->clock->now();
        $t = new TableauDeBord();

        $t->finissentSous3Jours = $this->deals->compter(new CriteresBonsPlans(expireSousJours: BonPlanMapper::SEUIL_CRITIQUE), $aujourdhui);
        $t->finissentSous7Jours = $this->deals->compter(new CriteresBonsPlans(expireSousJours: BonPlanMapper::SEUIL_PROCHE), $aujourdhui);
        $t->rembourses100EnCours = $this->deals->compterRembourse100EnCours($aujourdhui);
        $t->nouveauxDepuisHier = $this->deals->compter(new CriteresBonsPlans(nouveaux: true), $aujourdhui);
        $t->economiePotentielle = $this->deals->sommeValeurEnCours($aujourdhui);
        $t->economiesMois = $this->savings->total($user, $aujourdhui->modify('first day of this month'), $aujourdhui);
        $t->economiesTotal = $this->savings->total($user);
        foreach (DealType::cases() as $type) {
            $t->parType[$type->value] = $this->deals->compter(new CriteresBonsPlans(types: [$type]), $aujourdhui);
        }

        $resultat = $this->deals->rechercher(new CriteresBonsPlans(expireSousJours: BonPlanMapper::SEUIL_PROCHE), $aujourdhui, 0, 200);
        $bonsPlans = $this->mapper->versRessources($resultat['elements'], $user, $aujourdhui);
        $pref = $user->getPreference();
        // Priorité : favoris, puis enseignes suivies, puis date de fin.
        $score = static fn (BonPlan $b): int => ($b->favori ? 0 : 2) + ($pref->suitEnseigne($b->enseigne ?? $b->marque) ? 0 : 1);
        usort($bonsPlans, static fn (BonPlan $a, BonPlan $b): int => [$score($a), $a->joursRestants, $a->id] <=> [$score($b), $b->joursRestants, $b->id]);
        $t->aSaisir = \array_slice($bonsPlans, 0, self::LIMITE_A_SAISIR);

        return $t;
    }
}

<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Api\BonPlanMapper;
use App\Api\Filtres;
use App\Api\UtilisateurCourant;
use App\ApiResource\BonPlan;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Repository\CriteresBonsPlans;
use App\Repository\DealRepository;
use App\Repository\FavoriteRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * @implements ProviderInterface<BonPlan>
 */
final class BonPlanProvider implements ProviderInterface
{
    public function __construct(
        private readonly DealRepository $deals,
        private readonly FavoriteRepository $favoris,
        private readonly BonPlanMapper $mapper,
        private readonly UtilisateurCourant $utilisateur,
        private readonly Pagination $pagination,
        private readonly ClockInterface $clock,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $aujourdhui = $this->clock->now();
        $user = $this->utilisateur->getOuNull();

        if (!$operation instanceof CollectionOperationInterface) {
            $deal = $this->deals->find($uriVariables['id'] ?? 0);

            return null !== $deal ? $this->mapper->versRessourceUnique($deal, $user, $aujourdhui) : null;
        }

        $filtres = new Filtres($context);
        $criteres = $this->criteres($filtres);
        if (null !== $user && $filtres->booleen('favoris')) {
            $criteres->ids = array_keys($this->favoris->idsDealsFavoris($user));
        }
        // Catalogues régionaux : seulement ceux de la zone de l'utilisateur, sauf ?toutesRegions=1.
        if (null !== $user && !$filtres->booleen('toutesRegions')) {
            $criteres->zone = $user->getPreference()->getRegion();
        }

        $page = $this->pagination->getPage($context);
        $limite = $this->pagination->getLimit($operation, $context);
        $resultat = $this->deals->rechercher($criteres, $aujourdhui, ($page - 1) * $limite, $limite);

        return new TraversablePaginator(
            new \ArrayIterator($this->mapper->versRessources($resultat['elements'], $user, $aujourdhui)),
            $page,
            $limite,
            $resultat['total'],
        );
    }

    public static function criteres(Filtres $f): CriteresBonsPlans
    {
        return new CriteresBonsPlans(
            enseignes: $f->liste('enseigne'),
            types: array_values(array_filter(array_map(DealType::tryFrom(...), $f->liste('type')))),
            sousTypes: array_values(array_filter(array_map(RemboursementType::tryFrom(...), $f->liste('sousType')))),
            categories: $f->liste('categorie'),
            nouveaux: $f->booleen('nouveau'),
            texte: $f->texte('q'),
            expireSousJours: $f->entier('expireSous'),
            inclureExpires: $f->booleen('inclureExpires'),
            region: $f->texte('region'),
            tri: \in_array($f->texte('tri'), [CriteresBonsPlans::TRI_RECENTS, CriteresBonsPlans::TRI_VALEUR], true) ? (string) $f->texte('tri') : CriteresBonsPlans::TRI_DATE_FIN,
        );
    }
}

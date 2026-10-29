<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\Favori;
use App\Entity\Favorite;
use App\Repository\FavoriteRepository;

/**
 * @implements ProviderInterface<Favori>
 */
final class FavoriProvider implements ProviderInterface
{
    public function __construct(
        private readonly FavoriteRepository $favoris,
        private readonly UtilisateurCourant $utilisateur,
    ) {
    }

    /**
     * @return Favori|list<Favori>|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->utilisateur->get();

        if ($operation instanceof CollectionOperationInterface) {
            /** @var list<Favorite> $liste */
            $liste = $this->favoris->findBy(['user' => $user], ['creeLe' => 'DESC']);

            return array_map(self::versRessource(...), $liste);
        }

        $favori = $this->favoris->findOneBy(['user' => $user, 'deal' => (int) ($uriVariables['bonPlanId'] ?? 0)]);

        return null !== $favori ? self::versRessource($favori) : null;
    }

    public static function versRessource(Favorite $f): Favori
    {
        $r = new Favori();
        $r->bonPlanId = $f->getDeal()->getId();
        $r->creeLe = $f->getCreeLe()->format(\DateTimeInterface::ATOM);

        return $r;
    }
}

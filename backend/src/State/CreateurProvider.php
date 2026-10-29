<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Createur;
use App\Entity\WatchedCreator;
use App\Repository\WatchedCreatorRepository;

/**
 * @implements ProviderInterface<Createur>
 */
final class CreateurProvider implements ProviderInterface
{
    public function __construct(private readonly WatchedCreatorRepository $createurs)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            /** @var list<WatchedCreator> $liste */
            $liste = $this->createurs->findBy([], ['pseudo' => 'ASC']);

            return array_map(self::versRessource(...), $liste);
        }
        $createur = $this->createurs->find((int) ($uriVariables['id'] ?? 0));

        return null !== $createur ? self::versRessource($createur) : null;
    }

    public static function versRessource(WatchedCreator $w): Createur
    {
        $c = new Createur();
        $c->id = $w->getId();
        $c->pseudo = $w->getPseudo();
        $c->plateforme = $w->getPlateforme()->value;
        $c->urlProfil = $w->getUrlProfil();
        $c->lienBio = $w->getLienBio();
        $c->actif = $w->isActif();

        return $c;
    }
}

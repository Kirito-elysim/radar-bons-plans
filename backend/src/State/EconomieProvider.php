<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Api\Filtres;
use App\Api\UtilisateurCourant;
use App\ApiResource\Economie;
use App\Entity\Saving;
use App\Repository\SavingRepository;

/**
 * @implements ProviderInterface<Economie>
 */
final class EconomieProvider implements ProviderInterface
{
    public function __construct(
        private readonly SavingRepository $savings,
        private readonly UtilisateurCourant $utilisateur,
        private readonly Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->utilisateur->get();

        if (!$operation instanceof CollectionOperationInterface) {
            // Une économie d'un autre utilisateur est introuvable (404), pas interdite : on ne révèle rien.
            $saving = $this->savings->findOneBy(['id' => (int) ($uriVariables['id'] ?? 0), 'user' => $user]);

            return null !== $saving ? self::versRessource($saving) : null;
        }

        $f = new Filtres($context);
        $page = $this->pagination->getPage($context);
        $limite = $this->pagination->getLimit($operation, $context);
        $resultat = $this->savings->lister($user, self::date($f->texte('du')), self::date($f->texte('au')), ($page - 1) * $limite, $limite);

        return new TraversablePaginator(new \ArrayIterator(array_map(self::versRessource(...), $resultat['elements'])), $page, $limite, $resultat['total']);
    }

    public static function versRessource(Saving $s): Economie
    {
        $e = new Economie();
        $e->id = $s->getId();
        $e->libelle = $s->getLibelle();
        $e->montant = $s->getMontant();
        $e->date = $s->getDate()->format('Y-m-d');
        $e->enseigne = $s->getEnseigne();
        $e->bonPlanId = $s->getDeal()?->getId();
        $e->creeLe = $s->getCreeLe()->format(\DateTimeInterface::ATOM);

        return $e;
    }

    private static function date(?string $texte): ?\DateTimeImmutable
    {
        $date = null !== $texte ? \DateTimeImmutable::createFromFormat('!Y-m-d', $texte) : false;

        return false !== $date ? $date : null;
    }
}

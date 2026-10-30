<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SourceAdmin;
use App\Collecte\CollecteOrchestrator;
use App\Entity\Source;
use App\Repository\DealRepository;
use App\Repository\SourceRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * @implements ProviderInterface<SourceAdmin>
 */
final class SourceAdminProvider implements ProviderInterface
{
    public function __construct(
        private readonly SourceRepository $sources,
        private readonly DealRepository $deals,
        private readonly CollecteOrchestrator $orchestrateur,
        private readonly ClockInterface $clock,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $enCours = $this->deals->compterEnCoursParSource($this->clock->now());

        if ($operation instanceof CollectionOperationInterface) {
            /** @var list<Source> $liste */
            $liste = $this->sources->findBy([], ['nom' => 'ASC']);

            return array_map(fn (Source $s): SourceAdmin => $this->versRessource($s, $enCours), $liste);
        }

        $source = $this->sources->find((int) ($uriVariables['id'] ?? 0));

        return null !== $source ? $this->versRessource($source, $enCours) : null;
    }

    /**
     * @param array<int, int> $enCours
     */
    public function versRessource(Source $s, array $enCours): SourceAdmin
    {
        $r = new SourceAdmin();
        $r->id = (int) $s->getId();
        $r->code = $s->getCode();
        $r->nom = $s->getNom();
        $r->url = $s->getUrl();
        $r->type = $s->getType()->value;
        $r->actif = $s->isActif();
        $r->collectable = \in_array($s->getCode(), $this->orchestrateur->codesDisponibles(), true);
        $r->dernierRun = $s->getDernierRun()?->format(\DateTimeInterface::ATOM);
        $r->dernierStatut = $s->getDernierStatut()?->value;
        $r->dernierNbDeals = $s->getDernierNbDeals();
        $r->dernierErreur = $s->getDernierErreur();
        $r->dealsEnCours = $enCours[$r->id] ?? 0;

        return $r;
    }
}

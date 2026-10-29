<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Alerte\RecapQuotidienBuilder;
use App\Api\UtilisateurCourant;
use App\ApiResource\RecapApercu;
use App\Notification\LigneAlerte;
use Symfony\Component\Clock\ClockInterface;

/**
 * @implements ProviderInterface<RecapApercu>
 */
final class RecapApercuProvider implements ProviderInterface
{
    public function __construct(
        private readonly RecapQuotidienBuilder $builder,
        private readonly UtilisateurCourant $utilisateur,
        private readonly ClockInterface $clock,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RecapApercu
    {
        $message = $this->builder->construire($this->utilisateur->get(), $this->clock->now());
        $r = new RecapApercu();
        $r->sujet = $message->sujet;
        $r->intro = $message->intro;
        $r->resume = $message->resume;
        foreach ($message->sections as $titre => $lignes) {
            $r->sections[] = [
                'titre' => $titre,
                'lignes' => array_map(static fn (LigneAlerte $l): array => ['titre' => $l->titre, 'url' => $l->url, 'qui' => $l->qui, 'valeur' => $l->valeur, 'echeance' => $l->echeance], $lignes),
            ];
        }

        return $r;
    }
}

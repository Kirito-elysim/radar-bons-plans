<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\EconomiesStatistiques;
use App\Economie\CalculateurEconomies;
use App\Repository\SavingRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * @implements ProviderInterface<EconomiesStatistiques>
 */
final class EconomiesStatistiquesProvider implements ProviderInterface
{
    public function __construct(
        private readonly SavingRepository $savings,
        private readonly CalculateurEconomies $calculateur,
        private readonly UtilisateurCourant $utilisateur,
        private readonly ClockInterface $clock,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EconomiesStatistiques
    {
        $s = $this->calculateur->calculer($this->savings->toutes($this->utilisateur->get()), $this->clock->now());

        $r = new EconomiesStatistiques();
        foreach (get_object_vars($s) as $propriete => $valeur) {
            $r->{$propriete} = $valeur;
        }

        return $r;
    }
}

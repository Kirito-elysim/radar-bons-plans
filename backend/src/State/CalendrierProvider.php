<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Filtres;
use App\Api\UtilisateurCourant;
use App\ApiResource\Calendrier;
use App\Entity\Deal;
use App\Enum\DealType;
use App\Repository\DealRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * @implements ProviderInterface<Calendrier>
 */
final class CalendrierProvider implements ProviderInterface
{
    public function __construct(
        private readonly DealRepository $deals,
        private readonly ClockInterface $clock,
        private readonly UtilisateurCourant $utilisateur,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Calendrier
    {
        $f = new Filtres($context);
        $mois = $f->texte('mois');
        $debut = null !== $mois && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)
            ? new \DateTimeImmutable($mois.'-01', $this->clock->now()->getTimezone())
            : $this->clock->now()->modify('first day of this month')->setTime(0, 0);
        $fin = $debut->modify('last day of this month');
        $types = array_values(array_filter(array_map(DealType::tryFrom(...), $f->liste('type'))));

        $c = new Calendrier();
        $c->mois = $debut->format('Y-m');
        foreach ($this->deals->findDebutOuFinEntre($debut, $fin, $this->utilisateur->get()->getPreference()->getRegion()) as $deal) {
            if ([] !== $types && !\in_array($deal->getType(), $types, true)) {
                continue;
            }
            foreach (['debut' => $deal->getDateDebut(), 'fin' => $deal->getDateFin()] as $nature => $date) {
                if (null !== $date && $date >= $debut && $date <= $fin) {
                    $c->evenements[] = self::evenement($deal, $nature, $date);
                }
            }
        }
        usort($c->evenements, static fn (array $a, array $b): int => [$a['date'], $a['nature'], $a['bonPlanId']] <=> [$b['date'], $b['nature'], $b['bonPlanId']]);

        return $c;
    }

    /**
     * @return array{date: string, nature: string, bonPlanId: int, titre: string, type: string, enseigne: ?string, url: string}
     */
    private static function evenement(Deal $deal, string $nature, \DateTimeImmutable $date): array
    {
        return [
            'date' => $date->format('Y-m-d'),
            'nature' => $nature,
            'bonPlanId' => (int) $deal->getId(),
            'titre' => $deal->getTitre(),
            'type' => $deal->getType()->value,
            'enseigne' => $deal->getEnseigne() ?? $deal->getMarque(),
            'url' => $deal->getUrl(),
        ];
    }
}

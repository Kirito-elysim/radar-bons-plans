<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Alerte\AlerteExpirationService;
use App\Alerte\NouveauCatalogueAlerteService;
use App\Alerte\RecapQuotidienService;
use App\Collecte\CollecteOrchestrator;
use App\Message\CollecterSources;
use App\Message\EnvoyerRecapsDus;
use App\Message\PurgerExpires;
use App\Message\VerifierExpirations;
use App\Message\VerifierNouveauxCatalogues;
use App\Repository\DealRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Exécution des tâches planifiées (voir RadarSchedule) par le worker Messenger.
 */
final class TachesHandler
{
    /** Délai de conservation des offres expirées avant suppression. */
    private const JOURS_CONSERVATION = 30;

    public function __construct(
        private readonly CollecteOrchestrator $collecte,
        private readonly NouveauCatalogueAlerteService $nouveauxCatalogues,
        private readonly AlerteExpirationService $expirations,
        private readonly RecapQuotidienService $recaps,
        private readonly DealRepository $deals,
        private readonly MessageBusInterface $bus,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsMessageHandler]
    public function collecter(CollecterSources $message): void
    {
        $rapports = null !== $message->code ? [$this->collecte->executer($message->code)] : $this->collecte->executerTout();
        foreach ($rapports as $r) {
            $this->logger->info('Collecte {code} : {statut}, {nouveaux} nouveaux / {recus} reçus', [
                'code' => $r->codeSource, 'statut' => $r->statut->value, 'nouveaux' => $r->nbNouveaux, 'recus' => $r->nbRecus,
            ]);
        }
        // Après chaque collecte : nouveaux catalogues des enseignes suivies.
        $this->bus->dispatch(new VerifierNouveauxCatalogues());
    }

    #[AsMessageHandler]
    public function nouveauxCatalogues(VerifierNouveauxCatalogues $message): void
    {
        $this->nouveauxCatalogues->executer();
    }

    #[AsMessageHandler]
    public function expirations(VerifierExpirations $message): void
    {
        $this->expirations->executer();
    }

    #[AsMessageHandler]
    public function recaps(EnvoyerRecapsDus $message): void
    {
        $this->recaps->envoyerRecapsDus();
    }

    #[AsMessageHandler]
    public function purger(PurgerExpires $message): void
    {
        $maintenant = $this->clock->now();
        $marques = $this->deals->marquerExpires($maintenant);
        $supprimes = $this->deals->purgerExpires($maintenant, self::JOURS_CONSERVATION);
        $this->logger->info('Purge : {marques} offres expirées, {supprimes} supprimées', ['marques' => $marques, 'supprimes' => $supprimes]);
    }
}

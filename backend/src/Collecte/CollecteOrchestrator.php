<?php

declare(strict_types=1);

namespace App\Collecte;

use App\Collecte\DTO\DealDTO;
use App\Collecte\Event\ConnecteurVideEvent;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Entity\Deal;
use App\Entity\Source;
use App\Enum\RunStatus;
use App\Repository\DealRepository;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Exécute les connecteurs, enregistre (ou met à jour) les bons plans dédoublonnés
 * et l'état de chaque source.
 */
class CollecteOrchestrator
{
    /** @var array<string, SourceConnectorInterface> */
    private array $connecteurs = [];

    /**
     * @param iterable<SourceConnectorInterface> $connecteurs
     */
    public function __construct(
        #[AutowireIterator(SourceConnectorInterface::TAG)]
        iterable $connecteurs,
        private readonly SourceRepository $sources,
        private readonly DealRepository $deals,
        private readonly EntityManagerInterface $em,
        private readonly PoliteHttpClient $http,
        private readonly DealHasher $hasher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $collecteLogger,
    ) {
        foreach ($connecteurs as $connecteur) {
            $this->connecteurs[$connecteur->getCode()] = $connecteur;
        }
    }

    /**
     * @return list<string>
     */
    public function codesDisponibles(): array
    {
        return array_keys($this->connecteurs);
    }

    /**
     * Lance toutes les sources actives disposant d'un connecteur.
     *
     * @return list<RapportCollecte>
     */
    public function executerTout(): array
    {
        $rapports = [];
        foreach ($this->sources->findActives() as $source) {
            if (isset($this->connecteurs[$source->getCode()])) {
                $rapports[] = $this->executer($source->getCode());
            }
        }

        return $rapports;
    }

    /**
     * @param bool $simulation si vrai, rien n'est écrit en base
     */
    public function executer(string $code, bool $simulation = false): RapportCollecte
    {
        $connecteur = $this->connecteurs[$code] ?? throw new \InvalidArgumentException(\sprintf('Aucun connecteur pour la source « %s ». Disponibles : %s', $code, implode(', ', $this->codesDisponibles())));
        $source = $this->sources->findOneByCode($code) ?? throw new \InvalidArgumentException(\sprintf('Source « %s » absente de la base (migrations appliquées ?).', $code));

        $this->http->reinitialiserJournal();
        $debut = $this->clock->now();

        try {
            $dtos = $connecteur->fetch();
        } catch (CollecteException $e) {
            $this->collecteLogger->error('Connecteur {code} en erreur : {erreur}', ['code' => $code, 'erreur' => $e->getMessage(), 'exception' => $e]);
            $rapport = new RapportCollecte($code, RunStatus::Erreur, pagesIgnorees: $this->http->getPagesIgnorees(), erreur: $e->getMessage());
            if (!$simulation) {
                $source->enregistrerRun($debut, RunStatus::Erreur, 0, $e->getMessage());
                $this->em->flush();
            }

            return $rapport;
        }

        $pagesIgnorees = $this->http->getPagesIgnorees();
        $statut = match (true) {
            [] === $dtos => RunStatus::Vide,
            [] !== $pagesIgnorees => RunStatus::Partiel,
            default => RunStatus::Succes,
        };

        [$nouveaux, $misAJour] = $simulation ? [0, 0] : $this->enregistrer($source, $dtos, $debut);

        if (RunStatus::Vide === $statut) {
            $this->collecteLogger->critical('Connecteur {code} : 0 résultat, le site a peut-être changé de structure.', ['code' => $code]);
        }

        if (!$simulation) {
            $erreur = [] !== $pagesIgnorees ? 'Pages ignorées (robots.txt) : '.implode(', ', $pagesIgnorees) : null;
            $source->enregistrerRun($debut, $statut, \count($dtos), $erreur);
            $this->em->flush();
            if (RunStatus::Vide === $statut) {
                $this->dispatcher->dispatch(new ConnecteurVideEvent($source));
            }
        }

        return new RapportCollecte($code, $statut, \count($dtos), $nouveaux, $misAJour, $pagesIgnorees);
    }

    /**
     * @param list<DealDTO> $dtos
     *
     * @return array{int, int} nombre de nouveaux et de mis à jour
     */
    private function enregistrer(Source $source, array $dtos, \DateTimeImmutable $vuLe): array
    {
        $nouveaux = 0;
        $misAJour = 0;
        $vus = [];

        foreach ($dtos as $dto) {
            $hash = $this->hasher->hash($source->getCode(), $dto->url, $dto->titre);
            if (isset($vus[$hash])) {
                continue;
            }
            $vus[$hash] = true;

            $deal = $this->deals->findOneByHash($hash);
            if (null === $deal) {
                $deal = new Deal($source, $dto->type, $dto->titre, $dto->url, $hash, $vuLe);
                $this->em->persist($deal);
                ++$nouveaux;
            } else {
                ++$misAJour;
            }
            self::appliquer($deal, $dto, $vuLe);
        }

        return [$nouveaux, $misAJour];
    }

    private static function appliquer(Deal $deal, DealDTO $dto, \DateTimeImmutable $maintenant): void
    {
        $deal->setTitre($dto->titre)
            ->setEnseigne($dto->enseigne)
            ->setMarque($dto->marque)
            ->setSousType($dto->sousType)
            ->setDescriptionCourte($dto->descriptionCourte)
            ->setCategorie($dto->categorie)
            ->setValeurEuros($dto->valeurEuros)
            ->setValeurPourcent($dto->valeurPourcent)
            ->setCondition($dto->condition)
            ->setDateDebut($dto->dateDebut)
            ->setDateFin($dto->dateFin, $dto->dateFinEstimee)
            ->setExclusifAppli($dto->exclusifAppli)
            ->setRegion($dto->region)
            ->setImageUrl($dto->imageUrl);

        // Une offre revue sur la source mais déjà terminée reste expirée ; sinon elle est (ré)activée.
        $deal->setExpire($deal->estTermineeAu($maintenant));
    }
}

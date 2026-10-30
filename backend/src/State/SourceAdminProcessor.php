<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\SourceAdmin;
use App\Collecte\CollecteOrchestrator;
use App\Repository\DealRepository;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Activation d'une source (PATCH) et relance immédiate de sa collecte (POST …/relancer).
 *
 * @implements ProcessorInterface<SourceAdmin, SourceAdmin>
 */
final class SourceAdminProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SourceRepository $sources,
        private readonly DealRepository $deals,
        private readonly CollecteOrchestrator $orchestrateur,
        private readonly SourceAdminProvider $provider,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $collecteLogger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): SourceAdmin
    {
        $source = $this->sources->find((int) ($uriVariables['id'] ?? 0)) ?? throw new NotFoundHttpException('Source introuvable.');
        $rapport = null;

        if ('source_relancer' === $operation->getName()) {
            if (!\in_array($source->getCode(), $this->orchestrateur->codesDisponibles(), true)) {
                throw new UnprocessableEntityHttpException('Cette source n\'a pas de connecteur : elle est alimentée à la main.');
            }
            // La relance respecte les mêmes règles que la collecte planifiée (robots.txt, 1 requête / 2 s par domaine).
            set_time_limit(120);
            $this->collecteLogger->info('Relance manuelle de {code} par {admin}', ['code' => $source->getCode(), 'admin' => $this->security->getUser()?->getUserIdentifier()]);
            $r = $this->orchestrateur->executer($source->getCode());
            $rapport = [
                'statut' => $r->statut->value,
                'recus' => $r->nbRecus,
                'nouveaux' => $r->nbNouveaux,
                'misAJour' => $r->nbMisAJour,
                'pagesIgnorees' => $r->pagesIgnorees,
                'erreur' => $r->erreur,
            ];
            $this->em->refresh($source);
        } else {
            $source->setActif($data->actif);
            $this->em->flush();
        }

        $ressource = $this->provider->versRessource($source, $this->deals->compterEnCoursParSource($this->clock->now()));
        $ressource->rapport = $rapport;

        return $ressource;
    }
}

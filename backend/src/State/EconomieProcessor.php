<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\Economie;
use App\Entity\Saving;
use App\Repository\DealRepository;
use App\Repository\SavingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<Economie, Economie|null>
 */
final class EconomieProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SavingRepository $savings,
        private readonly DealRepository $deals,
        private readonly UtilisateurCourant $utilisateur,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Economie
    {
        $user = $this->utilisateur->get();
        $date = null !== $data->date ? \DateTimeImmutable::createFromFormat('!Y-m-d', $data->date) : false;

        try {
            if ($operation instanceof Post) {
                if (null !== $data->bonPlanId) {
                    $deal = $this->deals->find($data->bonPlanId) ?? throw new NotFoundHttpException('Bon plan introuvable.');
                    $saving = Saving::depuisDeal($user, $deal, false !== $date ? $date : $this->clock->now(), $data->montant);
                    if (null !== $data->libelle && '' !== trim($data->libelle)) {
                        $saving->setLibelle(trim($data->libelle));
                    }
                    if (null !== $data->enseigne) {
                        $saving->setEnseigne($data->enseigne);
                    }
                } else {
                    $saving = (new Saving($user, trim((string) $data->libelle), (string) $data->montant, false !== $date ? $date : $this->clock->now()))
                        ->setEnseigne($data->enseigne);
                }
                $this->em->persist($saving);
                $this->em->flush();

                return EconomieProvider::versRessource($saving);
            }

            $saving = $this->savings->findOneBy(['id' => (int) ($uriVariables['id'] ?? 0), 'user' => $user]) ?? throw new NotFoundHttpException('Économie introuvable.');

            if ($operation instanceof DeleteOperationInterface) {
                $this->em->remove($saving);
                $this->em->flush();

                return null;
            }

            // PATCH : l'objet $data a été pré-rempli par le provider puis fusionné avec le corps de la requête.
            if (null !== $data->libelle && '' !== trim($data->libelle)) {
                $saving->setLibelle(trim($data->libelle));
            }
            if (null !== $data->montant) {
                $saving->setMontant($data->montant);
            }
            if (false !== $date) {
                $saving->setDate($date);
            }
            $saving->setEnseigne($data->enseigne);
            $this->em->flush();

            return EconomieProvider::versRessource($saving);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }
    }
}

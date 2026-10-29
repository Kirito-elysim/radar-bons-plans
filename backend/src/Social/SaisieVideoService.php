<?php

declare(strict_types=1);

namespace App\Social;

use App\Collecte\DealHasher;
use App\Collecte\Parsing\MontantParser;
use App\Entity\Deal;
use App\Entity\User;
use App\Enum\DealType;
use App\Repository\DealRepository;
use App\Repository\SourceRepository;
use App\Repository\WatchedCreatorRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Crée (ou met à jour) un bon plan à partir d'une vidéo collée par l'utilisateur.
 */
final class SaisieVideoService
{
    public const CODE_SOURCE = 'tiktok';

    public function __construct(
        private readonly SourceRepository $sources,
        private readonly DealRepository $deals,
        private readonly WatchedCreatorRepository $createurs,
        private readonly EntityManagerInterface $em,
        private readonly DealHasher $hasher,
        private readonly MontantParser $montants,
    ) {
    }

    public function enregistrer(VideoMetadata $video, SaisieBonPlanVideo $saisie, ?User $auteur = null): Deal
    {
        $source = $this->sources->findOneByCode(self::CODE_SOURCE) ?? throw new \LogicException('Source « tiktok » absente (migrations appliquées ?).');
        $produit = trim($saisie->produit);
        if ('' === $produit) {
            throw new \InvalidArgumentException('Le produit est obligatoire.');
        }

        // Une vidéo peut présenter plusieurs bons plans : la clé combine vidéo et produit.
        $idExterne = ($video->idVideo ?? DealHasher::normaliserUrl($video->url)).':'.DealHasher::normaliserTitre($produit);
        $hash = $this->hasher->hash($source->getCode(), $video->url, $produit, $idExterne);

        $deal = $this->deals->findOneByHash($hash);
        if (null === $deal) {
            $deal = new Deal($source, DealType::TikTok, $produit, $video->url, $hash);
            $deal->setAjoutePar($auteur);
            $this->em->persist($deal);
        }

        $createur = null !== $video->auteurPseudo
            ? $this->createurs->findOneBy(['plateforme' => $video->plateforme, 'pseudo' => $video->auteurPseudo])
            : null;

        $deal->setTitre($produit)
            ->setEnseigne(null !== $saisie->enseigne && '' !== trim($saisie->enseigne) ? trim($saisie->enseigne) : null)
            ->setValeurEuros(null !== $saisie->economie ? $this->montants->euros($saisie->economie.' €') : null)
            ->setCondition($saisie->condition)
            ->setDateFin($saisie->dateFin)
            ->setDescriptionCourte($video->titre)
            ->setImageUrl($video->miniatureUrl)
            ->setCreateur($createur);
        $this->em->flush();

        return $deal;
    }
}

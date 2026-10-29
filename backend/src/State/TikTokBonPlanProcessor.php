<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\BonPlanMapper;
use App\Api\UtilisateurCourant;
use App\ApiResource\BonPlan;
use App\ApiResource\TikTokBonPlan;
use App\Enum\Plateforme;
use App\Social\SaisieBonPlanVideo;
use App\Social\SaisieVideoService;
use App\Social\SocialVideoResolver;
use App\Social\VideoIntrouvableException;
use App\Social\VideoMetadata;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<TikTokBonPlan, BonPlan>
 */
final class TikTokBonPlanProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SocialVideoResolver $videos,
        private readonly SaisieVideoService $saisie,
        private readonly BonPlanMapper $mapper,
        private readonly UtilisateurCourant $utilisateur,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BonPlan
    {
        $user = $this->utilisateur->get();

        try {
            $video = $this->videos->recuperer($data->url);
        } catch (VideoIntrouvableException $e) {
            // oEmbed indisponible (limite TikTok, vidéo privée…) : on enregistre quand même la saisie manuelle.
            $video = self::metadonneesMinimales($data->url) ?? throw new UnprocessableEntityHttpException($e->getMessage(), $e);
            $this->logger->notice('oEmbed TikTok indisponible, saisie enregistrée sans métadonnées : {erreur}', ['erreur' => $e->getMessage()]);
        }

        $dateFin = null !== $data->dateFin ? \DateTimeImmutable::createFromFormat('!Y-m-d', $data->dateFin) : false;
        $deal = $this->saisie->enregistrer($video, new SaisieBonPlanVideo(
            produit: $data->produit,
            enseigne: $data->enseigne,
            economie: $data->economie,
            dateFin: false !== $dateFin ? $dateFin : null,
            condition: $data->condition,
        ), $user);

        return $this->mapper->versRessourceUnique($deal, $user, $this->clock->now());
    }

    /**
     * Pseudo et identifiant lus dans l'URL d'une vidéo complète (pas de lien court).
     */
    private static function metadonneesMinimales(string $url): ?VideoMetadata
    {
        if (!preg_match('#^https://(?:www\.|m\.)?tiktok\.com/@([\w.-]+)/video/(\d+)#i', $url, $m)) {
            return null;
        }

        return new VideoMetadata(Plateforme::TikTok, (string) strtok($url, '?'), $m[2], '', $m[1], 'https://www.tiktok.com/@'.$m[1], null);
    }
}

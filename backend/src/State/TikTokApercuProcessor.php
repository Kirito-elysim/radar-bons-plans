<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\TikTokApercu;
use App\Repository\WatchedCreatorRepository;
use App\Social\SocialVideoResolver;
use App\Social\VideoIntrouvableException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<TikTokApercu, TikTokApercu>
 */
final class TikTokApercuProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SocialVideoResolver $videos,
        private readonly WatchedCreatorRepository $createurs,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TikTokApercu
    {
        try {
            $video = $this->videos->recuperer($data->url);
        } catch (VideoIntrouvableException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        $data->url = $video->url;
        $data->titre = $video->titre;
        $data->auteurPseudo = $video->auteurPseudo;
        $data->miniatureUrl = $video->miniatureUrl;
        $data->idVideo = $video->idVideo;
        $data->createurSuivi = null !== $video->auteurPseudo
            && null !== $this->createurs->findOneBy(['plateforme' => $video->plateforme, 'pseudo' => $video->auteurPseudo]);

        return $data;
    }
}

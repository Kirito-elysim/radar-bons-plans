<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\UtilisateurCourant;
use App\ApiResource\Preferences;
use App\Enum\NotificationChannel;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<Preferences, Preferences>
 */
final class PreferencesProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly UtilisateurCourant $utilisateur,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Preferences
    {
        $user = $this->utilisateur->get();

        $pref = $user->getPreference()
            ->setEnseignesSuivies($data->enseignesSuivies)
            ->setCategories($data->categories)
            ->setSeuilAlerteJours($data->seuilAlerteJours)
            ->setCanaux(array_values(array_filter(array_map(NotificationChannel::tryFrom(...), $data->canaux))))
            ->setHeureRecap($data->heureRecap)
            ->setRecapActif($data->recapActif)
            ->setAlertesRembourse100($data->alertesRembourse100)
            ->setAlertesNouveauCatalogue($data->alertesNouveauCatalogue)
            ->setTelegramChatId(null !== $data->telegramChatId && '' !== $data->telegramChatId ? $data->telegramChatId : null);
        $this->em->persist($pref);
        $this->em->flush();

        return Preferences::depuis($pref);
    }
}

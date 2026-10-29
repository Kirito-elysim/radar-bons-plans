<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\CollecterSources;
use App\Message\EnvoyerRecapsDus;
use App\Message\PurgerExpires;
use App\Message\VerifierExpirations;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Planning (heure de Paris), consommé par : bin/console messenger:consume scheduler_radar
 * - collecte de toutes les sources toutes les 6 h (suivie de l'alerte « nouveau catalogue ») ;
 * - purge des offres expirées à 3 h ;
 * - alertes J-3 / J-1 à 12 h (rappel du midi, après le récap du matin) ;
 * - récap quotidien : vérification toutes les 5 minutes de l'heure choisie par chaque utilisateur (7h40 par défaut).
 */
#[AsSchedule('radar')]
final class RadarSchedule implements ScheduleProviderInterface
{
    private const FUSEAU = 'Europe/Paris';

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                RecurringMessage::cron('15 */6 * * *', new CollecterSources(), self::FUSEAU),
                RecurringMessage::cron('0 3 * * *', new PurgerExpires(), self::FUSEAU),
                RecurringMessage::cron('0 12 * * *', new VerifierExpirations(), self::FUSEAU),
                RecurringMessage::cron('*/5 * * * *', new EnvoyerRecapsDus(), self::FUSEAU),
            )
            // Mémorise le dernier passage : une tâche manquée pendant un redémarrage est rattrapée.
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}

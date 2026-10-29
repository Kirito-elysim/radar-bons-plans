<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Message\CollecterSources;
use App\Message\EnvoyerRecapsDus;
use App\Message\PurgerExpires;
use App\Message\VerifierExpirations;
use App\Scheduler\RadarSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

final class RadarScheduleTest extends TestCase
{
    public function testPlanning(): void
    {
        $messages = (new RadarSchedule(new ArrayAdapter()))->getSchedule()->getRecurringMessages();
        $parClasse = [];
        foreach ($messages as $m) {
            $contexte = new MessageContext('radar', $m->getId(), $m->getTrigger(), new \DateTimeImmutable());
            foreach ($m->getMessages($contexte) as $message) {
                $parClasse[$message::class] = $m;
            }
        }

        $prochain = static fn (RecurringMessage $m, string $depuis): string => (string) $m->getTrigger()->getNextRunDate(new \DateTimeImmutable($depuis, new \DateTimeZone('Europe/Paris')))?->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i');

        self::assertSame('2026-10-29 18:15', $prochain($parClasse[CollecterSources::class], '2026-10-29 12:20'), 'Collecte toutes les 6 h');
        self::assertSame('2026-10-30 03:00', $prochain($parClasse[PurgerExpires::class], '2026-10-29 12:20'), 'Purge à 3 h');
        self::assertSame('2026-10-30 12:00', $prochain($parClasse[VerifierExpirations::class], '2026-10-29 12:20'), 'Alertes à midi');
        self::assertSame('2026-10-29 07:40', $prochain($parClasse[EnvoyerRecapsDus::class], '2026-10-29 07:36'), 'Récap vérifié toutes les 5 min');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Saving;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Repository\SavingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SavingRepositoryTest extends KernelTestCase
{
    public function testTotauxParPeriodeEtParUtilisateur(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repo = self::getContainer()->get(SavingRepository::class);

        $moi = new User('moi@exemple.test');
        $autre = new User('autre@exemple.test');
        $em->persist($moi);
        $em->persist($autre);
        foreach ([['2026-09-30', '1,10'], ['2026-10-01', '2.20'], ['2026-10-31', '3.30'], ['2026-11-01', '0,01']] as [$date, $montant]) {
            $em->persist(new Saving($moi, 'Achat', $montant, new \DateTimeImmutable($date)));
        }
        $em->persist(new Saving($autre, 'Autre', '100', new \DateTimeImmutable('2026-10-15')));
        $em->flush();

        self::assertSame('6.61', $repo->total($moi));
        self::assertSame('5.50', $repo->total($moi, new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-31 23:00')));
        self::assertSame('0.00', $repo->total($moi, new \DateTimeImmutable('2027-01-01')));
    }

    public function testLesPreferencesSontPersisteesAvecLUtilisateur(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $user = new User('prefs@exemple.test');
        $user->getPreference()
            ->setEnseignesSuivies(['Carrefour', 'Lidl'])
            ->setCanaux([NotificationChannel::Push, NotificationChannel::Telegram])
            ->setHeureRecap('07:40');
        $em->persist($user);
        $em->flush();
        $em->clear();

        $relu = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'prefs@exemple.test']);
        self::assertNotNull($relu);
        self::assertSame(['Carrefour', 'Lidl'], $relu->getPreference()->getEnseignesSuivies());
        self::assertSame([NotificationChannel::Push, NotificationChannel::Telegram], $relu->getPreference()->getCanaux());
        self::assertSame('07:40', $relu->getPreference()->getHeureRecap()->format('H:i'));
    }
}

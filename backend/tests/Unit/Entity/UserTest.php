<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Enum\NotificationChannel;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmailNormaliseEtRoleUserToujoursPresent(): void
    {
        $user = new User('  Moi@Exemple.TEST ');

        self::assertSame('moi@exemple.test', $user->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->estAdmin());

        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
        self::assertTrue($user->estAdmin());
    }

    public function testPreferencesParDefaut(): void
    {
        $pref = (new User('moi@exemple.test'))->getPreference();

        self::assertSame(UserPreference::SEUIL_ALERTE_DEFAUT, $pref->getSeuilAlerteJours());
        self::assertSame('07:40', $pref->getHeureRecap()->format('H:i'));
        self::assertSame([NotificationChannel::Email], $pref->getCanaux());
        self::assertTrue($pref->isRecapActif());
        self::assertTrue($pref->isAlertesRembourse100());
    }

    public function testEnseignesSuiviesNettoyeesEtInsensiblesALaCasse(): void
    {
        $pref = (new User('moi@exemple.test'))->getPreference()
            ->setEnseignesSuivies(['Carrefour', ' Lidl ', '', 'Carrefour']);

        self::assertSame(['Carrefour', 'Lidl'], $pref->getEnseignesSuivies());
        self::assertTrue($pref->suitEnseigne('LIDL'));
        self::assertFalse($pref->suitEnseigne('Aldi'));
        self::assertFalse($pref->suitEnseigne(null));
    }

    public function testCanauxSansDoublon(): void
    {
        $pref = (new User('moi@exemple.test'))->getPreference()
            ->setCanaux([NotificationChannel::Push, NotificationChannel::Telegram, NotificationChannel::Push]);

        self::assertSame([NotificationChannel::Push, NotificationChannel::Telegram], $pref->getCanaux());
        self::assertTrue($pref->aCanal(NotificationChannel::Telegram));
        self::assertFalse($pref->aCanal(NotificationChannel::Email));
    }

    public function testHeureRecapInvalide(): void
    {
        $pref = (new User('moi@exemple.test'))->getPreference();
        self::assertSame('06:05', $pref->setHeureRecap('06:05')->getHeureRecap()->format('H:i'));

        $this->expectException(\InvalidArgumentException::class);
        $pref->setHeureRecap('7h40');
    }

    public function testSeuilAlerteBorne(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new User('moi@exemple.test'))->getPreference()->setSeuilAlerteJours(45);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Alerte;

use App\Collecte\DealHasher;
use App\Entity\Deal;
use App\Entity\User;
use App\Enum\DealType;
use App\Enum\NotificationChannel;
use App\Enum\RemboursementType;
use App\Repository\SourceRepository;
use App\Tests\Support\FauxEnvoyeurPush;
use App\Tests\Support\ReponsesHttpSimulees;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

/**
 * Base des tests d'alertes : horloge figée, e-mails capturés, Telegram et push simulés.
 */
abstract class AlerteTestCase extends KernelTestCase
{
    use ClockSensitiveTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime('2026-10-29 12:00:00 Europe/Paris');
        ReponsesHttpSimulees::reinitialiser();
        FauxEnvoyeurPush::reinitialiser();
    }

    protected function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param list<NotificationChannel> $canaux
     */
    protected function utilisateur(string $email = 'moi@exemple.test', array $canaux = [NotificationChannel::Email], ?string $telegram = null): User
    {
        $user = new User($email);
        $user->getPreference()->setCanaux($canaux)->setTelegramChatId($telegram);
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function deal(
        string $titre,
        ?int $finDans,
        DealType $type = DealType::Remboursement,
        ?RemboursementType $sousType = null,
        ?string $enseigne = null,
        ?string $valeur = null,
        string $vuIlYA = '10 days',
    ): Deal {
        $code = DealType::Catalogue === $type ? 'anti_crise_catalogue' : 'anti_crise_remboursement';
        $source = self::getContainer()->get(SourceRepository::class)->findOneByCode($code);
        self::assertNotNull($source);
        $url = 'https://exemple.test/'.rawurlencode($titre);
        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        $deal = (new Deal($source, $type, $titre, $url, (new DealHasher())->hash($code, $url, $titre), $maintenant->modify('-'.$vuIlYA)))
            ->setSousType($sousType)
            ->setEnseigne($enseigne)
            ->setValeurEuros($valeur)
            ->setDateFin(null !== $finDans ? $maintenant->setTime(0, 0)->modify(\sprintf('%+d days', $finDans)) : null);
        $this->em()->persist($deal);
        $this->em()->flush();

        return $deal;
    }

    /**
     * @return list<Email>
     */
    protected function emails(): array
    {
        /** @var list<Email> */
        return array_values(array_filter(self::getMailerMessages(), static fn (object $m): bool => $m instanceof Email));
    }
}

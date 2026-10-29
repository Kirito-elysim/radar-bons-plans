<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Collecte\DealHasher;
use App\Entity\Deal;
use App\Entity\Favorite;
use App\Entity\Saving;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Repository\DealRepository;
use App\Repository\SourceRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DealRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DealRepository $deals;
    private Source $source;
    private \DateTimeImmutable $aujourdhui;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->deals = self::getContainer()->get(DealRepository::class);
        $this->aujourdhui = new \DateTimeImmutable('2026-10-29 07:40');

        // Source insérée par la migration de données de référence.
        $source = self::getContainer()->get(SourceRepository::class)->findOneByCode('anti_crise_remboursement');
        self::assertNotNull($source, 'La migration doit créer les sources de référence.');
        $this->source = $source;
    }

    private function creerDeal(string $titre, ?string $dateFin, ?string $valeur = null, ?RemboursementType $sousType = null): Deal
    {
        $url = 'https://anti-crise.fr/offre/'.rawurlencode($titre);
        $deal = (new Deal($this->source, DealType::Remboursement, $titre, $url, (new DealHasher())->hash($this->source->getCode(), $url, $titre)))
            ->setDateFin(null !== $dateFin ? new \DateTimeImmutable($dateFin) : null)
            ->setValeurEuros($valeur)
            ->setSousType($sousType);
        $this->em->persist($deal);

        return $deal;
    }

    public function testFindExpirantSousTrieParDateDeFin(): void
    {
        $this->creerDeal('Dans 5 jours', '2026-11-03');
        $this->creerDeal('Dans 3 jours', '2026-11-01');
        $this->creerDeal('Aujourd\'hui', '2026-10-29');
        $this->creerDeal('Hier', '2026-10-28');
        $this->creerDeal('Sans date', null);
        $this->em->flush();

        $titres = array_map(static fn (Deal $d): string => $d->getTitre(), $this->deals->findExpirantSous(3, $this->aujourdhui));

        self::assertSame(['Aujourd\'hui', 'Dans 3 jours'], $titres);
    }

    public function testCompteursDuTableauDeBord(): void
    {
        $this->creerDeal('Lessive', '2026-11-10', '8.99', RemboursementType::Rembourse100);
        $this->creerDeal('Yaourts', null, '2.10', RemboursementType::Rembourse100);
        $this->creerDeal('Café', '2026-11-10', '1.50', RemboursementType::Partiel);
        $this->creerDeal('Terminé', '2026-10-01', '50.00', RemboursementType::Rembourse100);
        $this->em->flush();

        self::assertSame(2, $this->deals->compterRembourse100EnCours($this->aujourdhui));
        self::assertSame('12.59', $this->deals->sommeValeurEnCours($this->aujourdhui));
    }

    public function testMarquerPuisPurgerLesExpiresEnGardantCeuxLiesAUneEconomie(): void
    {
        $vieux = $this->creerDeal('Vieux', '2026-09-01');
        $vieuxUtilise = $this->creerDeal('Vieux utilisé', '2026-09-01', '3.00');
        $recent = $this->creerDeal('Expiré hier', '2026-10-28');
        $enCours = $this->creerDeal('En cours', '2026-10-29');
        $user = new User('integration@exemple.test');
        $this->em->persist($user);
        $this->em->persist(Saving::depuisDeal($user, $vieuxUtilise, new \DateTimeImmutable('2026-08-30')));
        $this->em->flush();

        self::assertSame(3, $this->deals->marquerExpires($this->aujourdhui));
        self::assertSame(0, $this->deals->marquerExpires($this->aujourdhui), 'Opération idempotente.');

        self::assertSame(1, $this->deals->purgerExpires($this->aujourdhui, 30));
        $this->em->clear();

        self::assertNull($this->deals->find($vieux->getId()));
        self::assertTrue($this->deals->find($vieuxUtilise->getId())?->isExpire());
        self::assertTrue($this->deals->find($recent->getId())?->isExpire());
        self::assertFalse($this->deals->find($enCours->getId())?->isExpire());
    }

    public function testLeHashEmpecheLesDoublons(): void
    {
        $this->creerDeal('Doublon', '2026-11-01');
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->creerDeal('Doublon', '2026-11-05');
        $this->em->flush();
    }

    public function testUnFavoriNePeutEtreAjouteQuUneFois(): void
    {
        $deal = $this->creerDeal('Favori', '2026-11-01');
        $user = new User('favori@exemple.test');
        $this->em->persist($user);
        $this->em->persist(new Favorite($user, $deal));
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->persist(new Favorite($user, $deal));
        $this->em->flush();
    }
}

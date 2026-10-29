<?php

declare(strict_types=1);

namespace App\Tests\Integration\Alerte;

use App\Alerte\RecapQuotidienService;
use App\Collecte\Event\ConnecteurVideEvent;
use App\Entity\Saving;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Repository\SourceRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class RecapQuotidienTest extends AlerteTestCase
{
    public function testEnvoyeALHeureChoisieUneSeuleFoisParJour(): void
    {
        $user = $this->utilisateur();
        $service = self::getContainer()->get(RecapQuotidienService::class);

        self::mockTime('2026-10-29 07:35:00 Europe/Paris');
        self::assertSame([], $service->envoyerRecapsDus(), 'Avant 7h40');

        self::mockTime('2026-10-29 07:40:00 Europe/Paris');
        self::assertSame(['moi@exemple.test' => ['email']], $service->envoyerRecapsDus());

        self::mockTime('2026-10-29 07:45:00 Europe/Paris');
        self::assertSame([], $service->envoyerRecapsDus(), 'Déjà envoyé aujourd\'hui');

        self::mockTime('2026-10-30 07:40:00 Europe/Paris');
        self::assertSame(['moi@exemple.test' => ['email']], $service->envoyerRecapsDus(), 'Le lendemain');

        $user->getPreference()->setRecapActif(false);
        self::mockTime('2026-10-31 08:00:00 Europe/Paris');
        self::assertSame([], $service->envoyerRecapsDus(), 'Récap désactivé');
    }

    public function testContenuDuRecap(): void
    {
        self::mockTime('2026-10-29 07:40:00 Europe/Paris');
        $user = $this->utilisateur();
        $user->getPreference()->setEnseignesSuivies(['Lidl']);
        $this->deal('Catalogue Lidl', 6, DealType::Catalogue, enseigne: 'Lidl', vuIlYA: '3 hours');
        $this->deal('Catalogue Aldi', 6, DealType::Catalogue, enseigne: 'Aldi', vuIlYA: '3 hours');
        $this->deal('Lidl Exclus Nord', 6, DealType::Catalogue, enseigne: 'Lidl', vuIlYA: '3 hours')->setRegion('nord');
        $this->deal('Lessive 100 %', 20, sousType: RemboursementType::Rembourse100, valeur: '8.99', vuIlYA: '2 hours');
        $this->deal('Yaourts 100 % bientôt finis', 2, sousType: RemboursementType::Rembourse100, vuIlYA: '20 days');
        $this->deal('ODR TV', 30, valeur: '100.00', vuIlYA: '1 hour');
        $this->deal('Ancienne offre', 30, valeur: '50.00', vuIlYA: '3 days');
        $this->em()->persist(new Saving($user, 'Lait', '2.10', new \DateTimeImmutable('2026-10-27')));
        $this->em()->persist(new Saving($user, 'Trop ancien', '9.00', new \DateTimeImmutable('2026-10-01')));
        $this->em()->flush();

        self::getContainer()->get(RecapQuotidienService::class)->envoyer($user);

        $emails = $this->emails();
        self::assertCount(1, $emails);
        self::assertSame('☀️ Vos bons plans du jeudi 29 octobre', $emails[0]->getSubject());
        $html = (string) $emails[0]->getHtmlBody();
        $texte = (string) $emails[0]->getTextBody();

        self::assertStringContainsString('4 nouveaux bons plans repérés', $texte);
        self::assertStringContainsString('108,99 € d\'économie potentielle repérée', $texte);
        self::assertStringContainsString('2,10 € économisés ces 7 derniers jours', $texte);
        self::assertStringContainsString('NOUVEAUX CATALOGUES', $texte);
        self::assertStringContainsString('Catalogue Lidl', $texte);
        self::assertStringNotContainsString('Catalogue Aldi', $texte, 'Enseigne non suivie');
        self::assertStringNotContainsString('Lidl Exclus Nord', $texte, 'Autre région que PACA');
        self::assertStringContainsString('NOUVELLES OFFRES 100 % REMBOURSÉES', $texte);
        self::assertStringContainsString('SE TERMINENT SOUS 3 JOURS', $texte);
        self::assertStringContainsString('Yaourts 100 % bientôt finis', $texte);
        self::assertStringNotContainsString('Ancienne offre', $texte);
        self::assertStringContainsString('href="https://exemple.test/Lessive%20100%20%25"', $html, 'Lien vers la source');
        self::assertStringContainsString('href="https://radar.exemple.test/"', $html, 'Lien vers l\'application');
    }

    public function testAlerteAdministrateurQuandUnConnecteurEstVide(): void
    {
        $source = self::getContainer()->get(SourceRepository::class)->findOneByCode('shopmium');
        self::assertNotNull($source);
        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);

        $dispatcher->dispatch(new ConnecteurVideEvent($source));
        $dispatcher->dispatch(new ConnecteurVideEvent($source));

        $emails = $this->emails();
        self::assertCount(1, $emails, 'Une seule alerte par jour et par source');
        self::assertSame('admin@exemple.test', $emails[0]->getTo()[0]->getAddress());
        self::assertSame('⚠️ Connecteur « Shopmium » : 0 résultat', $emails[0]->getSubject());
    }
}

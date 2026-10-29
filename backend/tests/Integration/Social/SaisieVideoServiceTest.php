<?php

declare(strict_types=1);

namespace App\Tests\Integration\Social;

use App\Enum\DealType;
use App\Enum\Plateforme;
use App\Repository\DealRepository;
use App\Social\SaisieBonPlanVideo;
use App\Social\SaisieVideoService;
use App\Social\VideoMetadata;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SaisieVideoServiceTest extends KernelTestCase
{
    private function video(): VideoMetadata
    {
        return new VideoMetadata(
            Plateforme::TikTok,
            'https://www.tiktok.com/@nelson_mrq/video/7430000000000000001',
            '7430000000000000001',
            'Lessive à 0,30 € chez Lidl grâce à l\'ODR 100 % remboursée 🔥 #bonplan',
            'nelson_mrq',
            'https://www.tiktok.com/@nelson_mrq',
            'https://p16-sign-va.tiktokcdn.com/obj/exemple-miniature.jpeg',
        );
    }

    public function testCreeUnBonPlanRattacheAuCreateurSuivi(): void
    {
        self::bootKernel();
        $service = self::getContainer()->get(SaisieVideoService::class);

        $deal = $service->enregistrer($this->video(), new SaisieBonPlanVideo('Lessive Skip 30 lavages', 'Lidl', '8,99', new \DateTimeImmutable('2026-11-15')));

        self::assertSame(DealType::TikTok, $deal->getType());
        self::assertSame('Lessive Skip 30 lavages', $deal->getTitre());
        self::assertSame('Lidl', $deal->getEnseigne());
        self::assertSame('8.99', $deal->getValeurEuros());
        self::assertSame('2026-11-15', $deal->getDateFin()?->format('Y-m-d'));
        self::assertSame('nelson_mrq', $deal->getCreateur()?->getPseudo(), 'Créateur inséré par la migration');
        self::assertSame('tiktok', $deal->getSource()->getCode());
    }

    public function testResaisieMetAJourEtPlusieursProduitsParVideo(): void
    {
        self::bootKernel();
        $service = self::getContainer()->get(SaisieVideoService::class);

        $premier = $service->enregistrer($this->video(), new SaisieBonPlanVideo('Lessive Skip', 'Lidl', '8'));
        $corrige = $service->enregistrer($this->video(), new SaisieBonPlanVideo('Lessive Skip', 'Lidl', '8,99'));
        $autre = $service->enregistrer($this->video(), new SaisieBonPlanVideo('Adoucissant Soupline', 'Lidl', '2'));

        self::assertSame($premier->getId(), $corrige->getId());
        self::assertSame('8.99', $corrige->getValeurEuros());
        self::assertNotSame($premier->getId(), $autre->getId());
        self::assertSame(2, self::getContainer()->get(DealRepository::class)->count(['type' => DealType::TikTok]));
    }

    public function testProduitObligatoire(): void
    {
        self::bootKernel();

        $this->expectException(\InvalidArgumentException::class);
        self::getContainer()->get(SaisieVideoService::class)->enregistrer($this->video(), new SaisieBonPlanVideo('  '));
    }
}

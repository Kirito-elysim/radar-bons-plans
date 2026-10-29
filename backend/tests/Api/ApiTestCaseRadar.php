<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Test\ApiTestCase;
use ApiPlatform\Test\Client;
use App\Collecte\DealHasher;
use App\Entity\Deal;
use App\Entity\Source;
use App\Entity\User;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Repository\SourceRepository;
use App\Tests\Support\ReponsesHttpSimulees;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Base des tests fonctionnels de l'API : utilisateurs, jetons et bons plans de test.
 */
abstract class ApiTestCaseRadar extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    protected Client $client;
    protected \DateTimeImmutable $aujourdhui;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->aujourdhui = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));
        ReponsesHttpSimulees::reinitialiser();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function creerUtilisateur(string $email = 'moi@exemple.test', bool $admin = false, string $motDePasse = 'motdepasse-solide'): User
    {
        $user = new User($email);
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, $motDePasse));
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    /**
     * @return array{auth_bearer: string}
     */
    protected function authentifie(User $user): array
    {
        return ['auth_bearer' => static::getContainer()->get(JWTTokenManagerInterface::class)->create($user)];
    }

    protected function source(string $code): Source
    {
        $source = static::getContainer()->get(SourceRepository::class)->findOneByCode($code);
        self::assertNotNull($source);

        return $source;
    }

    /**
     * Crée un bon plan ; $finDans = nombre de jours avant la fin (null = pas de date).
     */
    protected function creerDeal(
        string $titre,
        ?int $finDans,
        DealType $type = DealType::Remboursement,
        ?string $marque = null,
        ?string $enseigne = null,
        ?string $valeur = null,
        ?RemboursementType $sousType = null,
        ?\DateTimeImmutable $vuLe = null,
        ?int $debutIlYA = null,
    ): Deal {
        $code = match ($type) {
            DealType::Catalogue => 'anti_crise_catalogue',
            DealType::Shopmium => 'shopmium',
            DealType::Bon => 'envie_de_bien_manger',
            DealType::CodePromo => 'radins',
            DealType::TikTok => 'tiktok',
            DealType::Remboursement => 'anti_crise_remboursement',
        };
        $url = 'https://exemple.test/'.rawurlencode($titre);
        $deal = (new Deal($this->source($code), $type, $titre, $url, (new DealHasher())->hash($code, $url, $titre), $vuLe ?? $this->aujourdhui->modify('-10 days')))
            ->setMarque($marque)
            ->setEnseigne($enseigne)
            ->setValeurEuros($valeur)
            ->setSousType($sousType)
            ->setDateFin(null !== $finDans ? $this->aujourdhui->modify(\sprintf('%+d days', $finDans)) : null)
            ->setDateDebut(null !== $debutIlYA ? $this->aujourdhui->modify(\sprintf('-%d days', $debutIlYA)) : null);
        $this->em()->persist($deal);
        $this->em()->flush();

        return $deal;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected static function membres(mixed $reponse): array
    {
        self::assertIsArray($reponse);
        $membres = $reponse['member'] ?? $reponse['hydra:member'] ?? null;
        self::assertIsArray($membres);

        /** @var list<array<string, mixed>> */
        return $membres;
    }

    /**
     * @param list<array<string, mixed>> $membres
     *
     * @return list<mixed>
     */
    protected static function colonne(array $membres, string $cle): array
    {
        return array_map(static fn (array $m): mixed => $m[$cle] ?? null, $membres);
    }
}

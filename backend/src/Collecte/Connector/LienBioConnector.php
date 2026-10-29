<?php

declare(strict_types=1);

namespace App\Collecte\Connector;

use App\Collecte\Connector\Selectors\LienBioSelectors as S;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\MontantParser;
use App\Collecte\SourceConnectorInterface;
use App\Collecte\VideAttenduInterface;
use App\Entity\WatchedCreator;
use App\Enum\DealType;
use App\Repository\WatchedCreatorRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Connecteur générique des pages « lien en bio » des créateurs suivis (Linktree, blog…).
 * Chaque page n'est lue que si son robots.txt l'autorise ; les liens ne sont jamais suivis.
 */
final class LienBioConnector implements SourceConnectorInterface, VideAttenduInterface
{
    use CrawlerTrait;

    public const CODE = 'lien_bio';

    public function __construct(
        private readonly PoliteHttpClient $http,
        private readonly WatchedCreatorRepository $createurs,
        private readonly MontantParser $montants,
        private readonly LoggerInterface $collecteLogger,
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    /**
     * Vide normal tant qu'aucun créateur actif n'a de lien en bio renseigné.
     */
    public function resultatVideAttendu(): bool
    {
        return [] === $this->pagesConfigurees();
    }

    public function fetch(): array
    {
        $deals = [];
        foreach ($this->pagesConfigurees() as [$createur, $lien]) {
            try {
                foreach ($this->parser($this->http->get($lien), $lien, $createur->getPseudo()) as $deal) {
                    $deals[$deal->idExterne] ??= $deal;
                }
            } catch (CollecteException $e) {
                // Page interdite (déjà journalisée) ou indisponible : on passe au créateur suivant.
                $this->collecteLogger->warning('Lien en bio de @{pseudo} illisible : {erreur}', ['pseudo' => $createur->getPseudo(), 'erreur' => $e->getMessage()]);
            }
        }

        return array_values($deals);
    }

    /**
     * @return list<array{WatchedCreator, string}>
     */
    private function pagesConfigurees(): array
    {
        $pages = [];
        foreach ($this->createurs->findBy(['actif' => true]) as $createur) {
            if (null !== $lien = $createur->getLienBio()) {
                $pages[] = [$createur, $lien];
            }
        }

        return $pages;
    }

    /**
     * @return list<DealDTO>
     */
    public function parser(string $html, string $urlPage, string $pseudo): array
    {
        $crawler = new Crawler($html, $urlPage);
        $liens = $this->liensLinktree($crawler) ?? $this->liensHtml($crawler);

        $deals = [];
        foreach ($liens as [$titre, $url]) {
            if (!self::estBonPlan($titre, $url)) {
                continue;
            }
            $deals[$url] ??= new DealDTO(
                type: DealType::TikTok,
                titre: mb_substr($titre, 0, 255),
                url: $url,
                descriptionCourte: \sprintf('Lien en bio de @%s', $pseudo),
                valeurEuros: $this->montants->euros($titre),
                valeurPourcent: $this->montants->pourcent($titre),
                condition: $this->montants->condition($titre),
                idExterne: $pseudo.':'.$url,
                createurPseudo: $pseudo,
            );
        }

        return array_values($deals);
    }

    /**
     * @return list<array{string, string}>|null null si la page n'est pas une page Linktree
     */
    private function liensLinktree(Crawler $crawler): ?array
    {
        $script = $crawler->filter(S::CSS_NEXT_DATA);
        if (0 === $script->count()) {
            return null;
        }
        $json = json_decode($script->text(''), true);
        $liens = \is_array($json) ? ($json['props']['pageProps']['links'] ?? null) : null;
        if (!\is_array($liens)) {
            return null;
        }

        $resultat = [];
        foreach ($liens as $lien) {
            if (\is_array($lien) && \is_string($lien['title'] ?? null) && \is_string($lien['url'] ?? null)) {
                $resultat[] = [trim($lien['title']), trim($lien['url'])];
            }
        }

        return $resultat;
    }

    /**
     * @return list<array{string, string}>
     */
    private function liensHtml(Crawler $crawler): array
    {
        $resultat = [];
        foreach ($crawler->filter(S::CSS_LIENS)->links() as $lien) {
            $titre = trim((string) preg_replace('/\s+/u', ' ', $lien->getNode()->textContent));
            $titre = '' !== $titre ? $titre : trim((string) $lien->getNode()->getAttribute('title'));
            if ('' !== $titre) {
                $resultat[] = [$titre, $lien->getUri()];
            }
        }

        return $resultat;
    }

    private static function estBonPlan(string $titre, string $url): bool
    {
        $hote = strtolower((string) parse_url($url, \PHP_URL_HOST));
        if ('' === $hote || !str_starts_with($url, 'http')) {
            return false;
        }
        foreach (S::DOMAINES_EXCLUS as $domaine) {
            if ($hote === $domaine || str_ends_with($hote, '.'.$domaine)) {
                return false;
            }
        }

        return 1 === preg_match(S::MOTIF_BON_PLAN, $titre.' '.$url);
    }
}

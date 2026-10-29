<?php

declare(strict_types=1);

namespace App\Collecte\Connector;

use App\Collecte\Connector\Selectors\EnvieDeBienMangerSelectors as S;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Exception\PageInterditeException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\MontantParser;
use App\Collecte\SourceConnectorInterface;
use App\Enum\DealType;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Bons de réduction Envie de Bien Manger (à imprimer ou en remboursement).
 * Les bons sont valables 30 jours après impression : pas de date de fin sur la liste.
 */
final class EnvieDeBienMangerConnector implements SourceConnectorInterface
{
    use CrawlerTrait;

    public const CODE = 'envie_de_bien_manger';

    public function __construct(
        private readonly PoliteHttpClient $http,
        private readonly MontantParser $montants,
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function fetch(): array
    {
        $deals = [];
        for ($page = 0; $page < S::PAGES_MAX; ++$page) {
            $url = 0 === $page ? S::URL_LISTE : \sprintf(S::FORMAT_URL_PAGE, $page);
            try {
                $html = $this->http->get($url);
            } catch (PageInterditeException) {
                // Déjà journalisée par le client : on garde les pages déjà lues.
                break;
            } catch (CollecteException $e) {
                if (0 === $page) {
                    throw $e;
                }
                break;
            }

            $trouves = $this->parser($html);
            if ([] === $trouves) {
                break;
            }
            foreach ($trouves as $deal) {
                $deals[$deal->idExterne] ??= $deal;
            }
        }

        return array_values($deals);
    }

    /**
     * Analyse une page de la liste.
     *
     * @return list<DealDTO>
     */
    public function parser(string $html): array
    {
        $crawler = new Crawler($html, S::URL_LISTE);
        if (0 === $crawler->filter('body')->count()) {
            throw new CollecteException('Page Envie de Bien Manger vide ou illisible.');
        }

        $deals = [];
        $crawler->filter(S::CSS_ITEM)->each(function (Crawler $item) use (&$deals): void {
            $id = self::attribut($item, S::ATTR_ID);
            $titre = self::texte($item->filter(S::CSS_TITRE));
            if (null === $id || null === $titre) {
                return;
            }

            $montant = (string) self::texte($item->filter(S::CSS_MONTANT));
            $devise = (string) self::texte($item->filter(S::CSS_DEVISE));
            $description = self::texte($item->filter(S::CSS_DESCRIPTION));

            $deals[] = new DealDTO(
                type: DealType::Bon,
                titre: $titre,
                url: S::URL_LISTE,
                marque: self::attribut($item->filter(S::CSS_LOGO), 'alt'),
                descriptionCourte: $description,
                valeurEuros: '%' === $devise ? null : $this->montants->euros(ltrim($montant, '-').' €'),
                valeurPourcent: '%' === $devise ? $this->montants->pourcent(ltrim($montant, '-').' %') : null,
                condition: null !== $description ? $this->montants->condition($description) : null,
                idExterne: $id,
            );
        });

        return $deals;
    }
}

<?php

declare(strict_types=1);

namespace App\Collecte\Connector;

use App\Collecte\Connector\Selectors\AntiCriseRemboursementSelectors as S;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\FrenchDateParser;
use App\Collecte\Parsing\MontantParser;
use App\Collecte\SourceConnectorInterface;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Offres de remboursement (https://anti-crise.fr/les-offres-de-remboursement/).
 */
final class AntiCriseRemboursementConnector implements SourceConnectorInterface
{
    use CrawlerTrait;

    public const CODE = 'anti_crise_remboursement';

    public function __construct(
        private readonly PoliteHttpClient $http,
        private readonly FrenchDateParser $dates,
        private readonly MontantParser $montants,
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function fetch(): array
    {
        return $this->parser($this->http->get(S::URL_LISTE));
    }

    /**
     * @return list<DealDTO>
     */
    public function parser(string $html): array
    {
        $crawler = new Crawler($html, S::URL_LISTE);
        if (0 === $crawler->filter('body')->count()) {
            throw new CollecteException('Page des remboursements vide ou illisible.');
        }

        $deals = [];
        $crawler->filter(S::CSS_ITEM)->each(function (Crawler $item) use (&$deals): void {
            $deal = $this->lireItem($item);
            if (null !== $deal) {
                $deals[$deal->url] ??= $deal;
            }
        });

        return array_values($deals);
    }

    private function lireItem(Crawler $item): ?DealDTO
    {
        $url = self::urlPropre(self::attribut($item->filter(S::CSS_LIEN), 'href'));
        if (null === $url || !preg_match(S::MOTIF_URL, $url)) {
            return null;
        }

        $intitule = self::attribut($item->filter(S::CSS_IMAGE), 'title') ?? self::attribut($item->filter(S::CSS_IMAGE), 'alt');
        $titre = null !== $intitule ? trim((string) preg_replace(S::PREFIXE_TITRE, '', $intitule)) : null;
        if (null === $titre || '' === $titre) {
            return null;
        }

        $marque = self::texte($item->filter(S::CSS_MARQUE));
        $sousType = $this->sousType($item->filter(S::CSS_TYPE));
        $fin = $this->dates->parseDate((string) preg_replace('/^.*fin le\s*/iu', '', self::texte($item->filter(S::CSS_PIED)) ?? ''));

        $pourcent = $this->montants->pourcent($titre);
        if (RemboursementType::Rembourse100 === $sousType) {
            $pourcent = '100.00';
        }

        return new DealDTO(
            type: DealType::Remboursement,
            titre: $titre,
            url: $url,
            marque: null !== $marque ? self::majuscule($marque) : null,
            sousType: $sousType,
            descriptionCourte: $sousType?->libelle(),
            categorie: $this->categorie($item->filter(S::CSS_ICONE)),
            valeurEuros: $this->montants->euros($titre),
            valeurPourcent: $pourcent,
            condition: $this->montants->condition($titre),
            dateFin: $fin,
            imageUrl: self::urlImage($item->filter(S::CSS_IMAGE)),
            idExterne: $url,
        );
    }

    private function sousType(Crawler $badge): ?RemboursementType
    {
        foreach (explode(' ', (string) self::attribut($badge, 'class')) as $classe) {
            if (isset(S::TYPES[$classe])) {
                return S::TYPES[$classe];
            }
        }

        return null;
    }

    private function categorie(Crawler $icone): ?string
    {
        $src = self::attribut($icone, 'data-src') ?? self::attribut($icone, 'src');
        if (null === $src || !preg_match('#/icones/([a-z0-9-]+)\.png#', $src, $m)) {
            return null;
        }

        return S::CATEGORIES[$m[1]] ?? self::majuscule(str_replace('-', ' ', $m[1]));
    }
}

<?php

declare(strict_types=1);

namespace App\Collecte\Connector;

use App\Collecte\Connector\Selectors\AntiCriseCatalogueSelectors as S;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\FrenchDateParser;
use App\Collecte\SourceConnectorInterface;
use App\Enum\DealType;
use App\Region\Regions;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @phpstan-type Item array{url: string, enseigne: ?string, intitule: ?string, debut: ?\DateTimeImmutable, fin: ?\DateTimeImmutable, image: ?string}
 *
 * Catalogues des enseignes (https://anti-crise.fr/catalogue/).
 *
 * La page liste les catalogues en cours et à venir dans trois zones : un bloc par enseigne
 * (le plus complet, avec l'intitulé ex. « Provencia »), un bloc « Notre sélection » et un
 * carrousel d'en-tête. Les trois sont fusionnés par URL, le bloc par enseigne étant prioritaire.
 * La pagination (/page/2/) renvoie la même liste : une seule page est lue.
 */
final class AntiCriseCatalogueConnector implements SourceConnectorInterface
{
    public const CODE = 'anti_crise_catalogue';

    /** Enseignes à mettre en avant (préférences par défaut, tri du tableau de bord). */
    public const ENSEIGNES_PRIORITAIRES = [
        'Carrefour', 'Carrefour Market', 'Intermarché', 'Leclerc', 'Lidl',
        'Auchan', 'Super U', 'Aldi', 'Netto', 'Action',
    ];

    public function __construct(
        private readonly PoliteHttpClient $http,
        private readonly FrenchDateParser $dates,
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
     * Analyse le HTML de la page liste (public pour les tests sur fixtures).
     *
     * @return list<DealDTO>
     */
    public function parser(string $html, ?\DateTimeImmutable $reference = null): array
    {
        $crawler = new Crawler($html, S::URL_LISTE);
        if (0 === $crawler->filter('body')->count()) {
            throw new CollecteException('Page catalogue vide ou illisible.');
        }

        /** @var array<string, Item> $parUrl */
        $parUrl = [];
        /** Correspondance slug d'URL → nom d'enseigne, apprise sur les blocs par enseigne. */
        $slugs = [];

        // 1. Blocs par enseigne
        $crawler->filterXPath(S::XPATH_BLOCS_ENSEIGNE)->each(function (Crawler $bloc) use (&$parUrl, &$slugs): void {
            $enseigne = self::enseigneDuBloc($bloc);
            $bloc->filter(S::CSS_ITEM)->each(function (Crawler $item) use ($enseigne, &$parUrl, &$slugs): void {
                $donnees = $this->lireItem($item, $enseigne);
                if (null === $donnees) {
                    return;
                }
                $parUrl[$donnees['url']] = $donnees;
                if (null !== $enseigne && null !== $slug = self::slugEnseigne($donnees['url'])) {
                    $slugs[$slug] ??= $enseigne;
                }
            });
        });

        // 2. « Notre sélection »
        $crawler->filter(S::CSS_BLOC_SELECTION)->filter(S::CSS_ITEM)->each(function (Crawler $item) use (&$parUrl): void {
            $donnees = $this->lireItem($item, null);
            if (null !== $donnees) {
                $parUrl[$donnees['url']] ??= $donnees;
            }
        });

        // 3. Carrousel
        $crawler->filter(S::CSS_CARROUSEL_LIEN)->each(function (Crawler $lien) use (&$parUrl, $reference): void {
            $url = self::urlCatalogue($lien->attr('href'));
            if (null === $url || isset($parUrl[$url])) {
                return;
            }
            $periode = $this->dates->parsePeriode(self::texte($lien->filter(S::CSS_CARROUSEL_DATE)) ?? '', $reference);
            $parUrl[$url] = [
                'url' => $url,
                'enseigne' => self::texte($lien->filter(S::CSS_CARROUSEL_ENSEIGNE)),
                'intitule' => null,
                'debut' => $periode['debut'] ?? null,
                'fin' => $periode['fin'] ?? null,
                'image' => self::image($lien),
            ];
        });

        $deals = [];
        /** @var Item $d */
        foreach ($parUrl as $url => $d) {
            $enseigne = $d['enseigne'] ?? self::enseigneDepuisSlug($url, $slugs);
            $deals[] = new DealDTO(
                type: DealType::Catalogue,
                titre: self::titre($enseigne, $d['intitule']),
                url: $url,
                enseigne: $enseigne,
                descriptionCourte: $d['intitule'],
                dateDebut: $d['debut'],
                dateFin: $d['fin'],
                region: self::region($d['intitule'], $url),
                imageUrl: $d['image'],
            );
        }

        return $deals;
    }

    /**
     * @return Item|null
     */
    private function lireItem(Crawler $item, ?string $enseigne): ?array
    {
        $lien = $item->filter(S::CSS_LIEN);
        $url = $lien->count() > 0 ? self::urlCatalogue($lien->attr('href')) : null;
        if (null === $url) {
            return null;
        }

        $periode = $this->dates->parsePeriode(self::texte($item->filter(S::CSS_DATES)) ?? '');

        return [
            'url' => $url,
            'enseigne' => $enseigne,
            'intitule' => self::texte($item->filter(S::CSS_INTITULE)),
            'debut' => $periode['debut'] ?? null,
            'fin' => $periode['fin'] ?? null,
            'image' => self::image($lien),
        ];
    }

    /**
     * Nom de l'enseigne : texte du <h2> qui précède le bloc. On passe par DOMXPath car
     * Crawler::filterXPath() ne gère pas les axes relatifs comme « preceding-sibling:: ».
     */
    private static function enseigneDuBloc(Crawler $bloc): ?string
    {
        $noeud = $bloc->getNode(0);
        if (!$noeud instanceof \DOMElement || null === $noeud->ownerDocument) {
            return null;
        }
        $h2 = (new \DOMXPath($noeud->ownerDocument))->query(S::XPATH_TITRE_ENSEIGNE, $noeud);
        $premier = false !== $h2 ? $h2->item(0) : null;
        $texte = $premier instanceof \DOMElement ? trim((string) preg_replace('/\s+/u', ' ', $premier->textContent)) : '';

        return '' !== $texte ? $texte : null;
    }

    private static function texte(Crawler $noeud): ?string
    {
        if (0 === $noeud->count()) {
            return null;
        }
        $texte = trim((string) preg_replace('/\s+/u', ' ', $noeud->first()->text('')));

        return '' !== $texte ? $texte : null;
    }

    private static function image(Crawler $lien): ?string
    {
        $img = $lien->filter(S::CSS_IMAGE);
        if (0 === $img->count()) {
            return null;
        }
        $src = $img->attr('data-src') ?? $img->attr('src');

        return null !== $src && str_starts_with($src, 'https://') ? $src : null;
    }

    private static function urlCatalogue(?string $href): ?string
    {
        if (null === $href) {
            return null;
        }
        $url = strtok(trim($href), '#?');

        return false !== $url && preg_match(S::MOTIF_URL_CATALOGUE, $url) ? $url : null;
    }

    /**
     * « …/catalogue-carrefour-market-du-03-au-15-novembre-2026/ » → « carrefour-market ».
     */
    private static function slugEnseigne(string $url): ?string
    {
        return preg_match('#/catalogue-([a-z0-9-]+?)-(?:du|valable|jusqu)-#', $url, $m) ? $m[1] : null;
    }

    /**
     * @param array<string, string> $slugs
     */
    private static function enseigneDepuisSlug(string $url, array $slugs): ?string
    {
        $slug = self::slugEnseigne($url);
        if (null === $slug) {
            return null;
        }

        return $slugs[$slug] ?? ucwords(str_replace('-', ' ', $slug));
    }

    private static function titre(?string $enseigne, ?string $intitule): string
    {
        $titre = 'Catalogue '.($enseigne ?? 'enseigne inconnue');

        return null !== $intitule ? $titre.' — '.$intitule : $titre;
    }

    /**
     * Zones d'un catalogue régional (« Exclus PACA », « Provencia », « Nord »…), null si national.
     * Sans intitulé (carrousel), on regarde la fin de l'URL après l'année (« …-2026-provencia/ »).
     */
    private static function region(?string $intitule, string $url): ?string
    {
        $suffixe = preg_match('#-20\d{2}-([a-z0-9-]+)/?$#', $url, $m) ? $m[1] : '';

        return Regions::detecter(($intitule ?? '').' '.$suffixe);
    }
}

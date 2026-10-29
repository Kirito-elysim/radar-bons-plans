<?php

declare(strict_types=1);

namespace App\Collecte\Connector;

use App\Collecte\Connector\Selectors\ShopmiumSelectors as S;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\FrenchDateParser;
use App\Collecte\Parsing\MontantParser;
use App\Collecte\SourceConnectorInterface;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Offres Shopmium (remboursement via l'application, toujours « exclusif appli »).
 * Une même offre apparaît dans plusieurs onglets : on les fusionne par identifiant
 * pour récupérer catégorie, enseigne exclusive et canal d'achat.
 */
final class ShopmiumConnector implements SourceConnectorInterface
{
    use CrawlerTrait;

    public const CODE = 'shopmium';

    public function __construct(
        private readonly PoliteHttpClient $http,
        private readonly FrenchDateParser $dates,
        private readonly MontantParser $montants,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function fetch(): array
    {
        return $this->parser($this->http->get(S::URL_LISTE), $this->clock->now());
    }

    /**
     * @return list<DealDTO>
     */
    public function parser(string $html, \DateTimeImmutable $maintenant): array
    {
        $crawler = new Crawler($html, S::URL_LISTE);
        if (0 === $crawler->filter('body')->count()) {
            throw new CollecteException('Page Shopmium vide ou illisible.');
        }

        /** @var array<string, array{titre: string, url: string, description: ?string, fin: ?string, image: ?string, onglets: list<string>}> $offres */
        $offres = [];
        $crawler->filterXPath(S::XPATH_TUILES)->each(function (Crawler $tuile) use (&$offres): void {
            $id = self::attribut($tuile, S::ATTR_ID);
            $url = self::urlPropre(self::attribut($tuile->filter(S::CSS_LIEN), 'href'));
            $titre = self::texte($tuile->filter(S::CSS_TITRE));
            if (null === $id || null === $url || null === $titre || !str_starts_with($url, 'https://offers.shopmium.com/')) {
                return;
            }

            $offres[$id] ??= [
                'titre' => $titre,
                'url' => $url,
                'description' => self::texte($tuile->filter(S::CSS_DESCRIPTION)),
                'fin' => self::texte($tuile->filter(S::CSS_FIN)),
                'image' => self::urlImage($tuile->filter(S::CSS_IMAGE)),
                'onglets' => [],
            ];
            $offres[$id]['onglets'][] = trim((string) $tuile->attr(S::ATTR_ONGLET));
            $offres[$id]['fin'] ??= self::texte($tuile->filter(S::CSS_FIN));
        });

        $deals = [];
        foreach ($offres as $id => $o) {
            [$categorie, $enseigne, $canal] = self::interpreterOnglets($o['onglets']);
            $fin = null !== $o['fin'] ? $this->dates->parseDureeRestante($o['fin'], $maintenant) : null;
            $texte = $o['titre'].' '.$o['description'];

            $deals[] = new DealDTO(
                type: DealType::Shopmium,
                titre: $o['titre'],
                url: $o['url'],
                enseigne: $enseigne,
                sousType: preg_match(S::MOTIF_100_REMBOURSE, $texte) ? RemboursementType::Rembourse100 : null,
                descriptionCourte: $o['description'],
                categorie: $categorie,
                valeurEuros: $this->montants->euros($texte),
                condition: $canal,
                dateFin: $fin,
                dateFinEstimee: null !== $fin,
                exclusifAppli: true,
                imageUrl: $o['image'],
                idExterne: (string) $id,
            );
        }

        return $deals;
    }

    /**
     * @param list<string> $onglets
     *
     * @return array{?string, ?string, ?string} catégorie, enseigne exclusive, canal d'achat
     */
    private static function interpreterOnglets(array $onglets): array
    {
        $categorie = $enseigne = $canal = null;
        foreach ($onglets as $onglet) {
            $onglet = trim($onglet);
            if (\in_array(mb_strtolower($onglet), S::ONGLETS_IGNORES, true) || preg_match(S::MOTIF_ONGLET_CAMPAGNE, $onglet)) {
                continue;
            }
            if (preg_match(S::MOTIF_ONGLET_ENSEIGNE, $onglet, $m)) {
                $enseigne ??= trim($m[1]);
            } elseif (preg_match(S::MOTIF_ONGLET_CANAL, $onglet)) {
                $canal ??= $onglet;
            } else {
                $categorie ??= $onglet;
            }
        }

        return [$categorie, $enseigne, $canal];
    }
}

<?php

declare(strict_types=1);

namespace App\Collecte\Connector;

use App\Collecte\Connector\Selectors\RadinsSelectors as S;
use App\Collecte\DTO\DealDTO;
use App\Collecte\Exception\CollecteException;
use App\Collecte\Exception\PageInterditeException;
use App\Collecte\Http\PoliteHttpClient;
use App\Collecte\Parsing\MontantParser;
use App\Collecte\SourceConnectorInterface;
use App\Enum\DealType;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Codes promo et bons plans de Radins.com (page d'accueil et page codes promo).
 */
final class RadinsConnector implements SourceConnectorInterface
{
    use CrawlerTrait;

    public const CODE = 'radins';

    private readonly \DateTimeZone $paris;

    public function __construct(
        private readonly PoliteHttpClient $http,
        private readonly MontantParser $montants,
    ) {
        $this->paris = new \DateTimeZone('Europe/Paris');
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function fetch(): array
    {
        $deals = [];
        $erreurs = [];
        foreach (S::URLS as $url) {
            try {
                foreach ($this->parser($this->http->get($url)) as $deal) {
                    $deals[$deal->idExterne] ??= $deal;
                }
            } catch (PageInterditeException) {
                continue;
            } catch (CollecteException $e) {
                $erreurs[] = $e->getMessage();
            }
        }
        if ([] === $deals && [] !== $erreurs) {
            throw new CollecteException(implode(' | ', $erreurs));
        }

        return array_values($deals);
    }

    /**
     * @return list<DealDTO>
     */
    public function parser(string $html): array
    {
        $script = (new Crawler($html, S::BASE))->filter(S::CSS_NEXT_DATA);
        if (0 === $script->count()) {
            throw new CollecteException('Radins : bloc __NEXT_DATA__ absent (le site a peut-être changé).');
        }

        try {
            $donnees = json_decode($script->text(''), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new CollecteException('Radins : JSON __NEXT_DATA__ illisible.', 0, $e);
        }

        $deals = [];
        foreach (self::vouchers($donnees) as $v) {
            $deal = $this->lireVoucher($v);
            if (null !== $deal) {
                $deals[$deal->idExterne] ??= $deal;
            }
        }

        return array_values($deals);
    }

    /**
     * @return list<array<mixed>>
     */
    private static function vouchers(mixed $donnees): array
    {
        $widgets = \is_array($donnees) ? ($donnees['props']['pageProps']['widgets'] ?? []) : [];
        $vouchers = [];
        foreach (\is_array($widgets) ? $widgets : [] as $widget) {
            $liste = \is_array($widget) && \is_array($widget['widget_data'] ?? null) ? ($widget['widget_data']['vouchers'] ?? []) : [];
            foreach (\is_array($liste) ? $liste : [] as $v) {
                if (\is_array($v)) {
                    $vouchers[] = $v;
                }
            }
        }

        return $vouchers;
    }

    /**
     * @param array<mixed> $v
     */
    private function lireVoucher(array $v): ?DealDTO
    {
        $id = self::chaine($v['idPool'] ?? null);
        $titre = self::chaine($v['title'] ?? null);
        $retailer = \is_array($v['retailer'] ?? null) ? $v['retailer'] : [];
        $chemin = self::chaine($retailer['rlpUrl'] ?? null);
        if (null === $id || null === $titre || null === $chemin || true === ($v['isExpired'] ?? false)) {
            return null;
        }

        $caption = \is_array($v['caption'] ?? null) ? $v['caption'] : [];
        $valeur = self::chaine($caption['first'] ?? null) ?? '';
        $estCode = \in_array($v['type'] ?? null, S::TYPES_CODE, true);
        $conditions = strip_tags(self::chaine(\is_array($v['termsAndConditions'] ?? null) ? ($v['termsAndConditions']['text'] ?? null) : null) ?? '');

        return new DealDTO(
            type: DealType::CodePromo,
            titre: mb_substr($titre, 0, 255),
            url: S::BASE.ltrim($chemin, '/'),
            enseigne: self::chaine($retailer['name'] ?? null),
            descriptionCourte: $estCode ? 'Code promo' : 'Bon plan',
            categorie: $estCode ? 'Code promo' : 'Bon plan',
            valeurEuros: str_contains($valeur, '€') ? $this->montants->euros($valeur) : null,
            valeurPourcent: str_contains($valeur, '%') ? $this->montants->pourcent($valeur) : null,
            condition: $this->montants->condition($titre.' '.$conditions),
            dateFin: $this->dateFin(self::chaine($v['endTime'] ?? null)),
            idExterne: $id,
        );
    }

    /**
     * « 2027-01-09T22:59:59Z » (UTC) → 09/01/2027 à Paris.
     */
    private function dateFin(?string $iso): ?\DateTimeImmutable
    {
        if (null === $iso) {
            return null;
        }
        try {
            return (new \DateTimeImmutable($iso))->setTimezone($this->paris)->setTime(0, 0);
        } catch (\Exception) {
            return null;
        }
    }

    private static function chaine(mixed $valeur): ?string
    {
        return \is_string($valeur) && '' !== trim($valeur) ? trim($valeur) : null;
    }
}

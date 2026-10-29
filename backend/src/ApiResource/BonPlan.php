<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\State\BonPlanProvider;

/**
 * Bon plan tel qu'exposé par l'API (lecture seule).
 */
#[ApiResource(
    shortName: 'BonPlan',
    operations: [
        new GetCollection(
            uriTemplate: '/bons-plans',
            description: 'Recherche des bons plans en cours, triés par date de fin.',
            parameters: [
                'enseigne' => new QueryParameter(description: 'Enseigne ou marque, plusieurs séparées par des virgules', schema: ['type' => 'string']),
                'type' => new QueryParameter(description: 'catalogue, remboursement, bon, code_promo, shopmium, tiktok (virgules possibles)', schema: ['type' => 'string']),
                'sousType' => new QueryParameter(description: 'rembourse_100, partiel, bon_achat, offre_essai, offre_specifique', schema: ['type' => 'string']),
                'categorie' => new QueryParameter(description: 'Catégorie (virgules possibles)', schema: ['type' => 'string']),
                'nouveau' => new QueryParameter(description: 'Uniquement les bons plans repérés depuis hier', schema: ['type' => 'boolean']),
                'q' => new QueryParameter(description: 'Recherche texte (titre, marque, enseigne, description)', schema: ['type' => 'string']),
                'expireSous' => new QueryParameter(description: 'Se termine dans N jours au plus', schema: ['type' => 'integer', 'minimum' => 0]),
                'favoris' => new QueryParameter(description: 'Uniquement mes favoris', schema: ['type' => 'boolean']),
                'region' => new QueryParameter(description: 'Uniquement les catalogues régionaux de cette zone (paca, nord…)', schema: ['type' => 'string']),
                'toutesRegions' => new QueryParameter(description: 'Inclure les catalogues régionaux des autres zones que la mienne', schema: ['type' => 'boolean']),
                'inclureExpires' => new QueryParameter(description: 'Inclure les offres terminées', schema: ['type' => 'boolean']),
                'tri' => new QueryParameter(description: 'dateFin (défaut), recents, valeur', schema: ['type' => 'string', 'enum' => ['dateFin', 'recents', 'valeur']]),
            ],
        ),
        new Get(uriTemplate: '/bons-plans/{id}'),
    ],
    provider: BonPlanProvider::class,
)]
final class BonPlan
{
    #[ApiProperty(identifier: true)]
    public int $id;

    /** catalogue | remboursement | bon | code_promo | shopmium | tiktok */
    public string $type;
    public string $typeLibelle;
    public ?string $sousType = null;
    public ?string $sousTypeLibelle = null;
    public string $titre;
    public ?string $enseigne = null;
    public ?string $marque = null;
    public ?string $description = null;
    public ?string $categorie = null;
    /** Montant en euros (chaîne décimale « 2.50 »), null si inconnu. */
    public ?string $valeurEuros = null;
    public ?string $valeurPourcent = null;
    public ?string $condition = null;
    /** Format AAAA-MM-JJ. */
    public ?string $dateDebut = null;
    public ?string $dateFin = null;
    public bool $dateFinEstimee = false;
    /** Jours restants (0 = dernier jour), null si date de fin inconnue. */
    public ?int $joursRestants = null;
    /** « critique » (≤ 3 jours), « proche » (≤ 7 jours) ou null. */
    public ?string $urgence = null;
    public bool $exclusifAppli = false;
    /** Codes de zones d'un catalogue régional (« paca », « paca,occitanie,sud-ouest »), null = national. */
    public ?string $region = null;
    public ?string $regionLibelle = null;
    /** Lien vers la source : l'application ne recopie pas le contenu. */
    public string $url;
    public ?string $imageUrl = null;
    public string $sourceCode;
    public string $sourceNom;
    public ?string $createur = null;
    public bool $nouveau = false;
    public bool $expire = false;
    public bool $favori = false;
    public string $premiereVueLe;
}

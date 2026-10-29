<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Données de référence : sources à agréger et créateurs TikTok suivis.
 * Idempotente (ON CONFLICT DO NOTHING) pour pouvoir être rejouée sans risque.
 */
final class Version20261029140000 extends AbstractMigration
{
    private const SOURCES = [
        ['anti_crise_catalogue', 'Anti-crise.fr — Catalogues', 'https://anti-crise.fr/catalogue/', 'scraping'],
        ['anti_crise_remboursement', 'Anti-crise.fr — Offres de remboursement', 'https://anti-crise.fr/les-offres-de-remboursement/', 'scraping'],
        ['shopmium', 'Shopmium', 'https://offers.shopmium.com/fr', 'scraping'],
        ['envie_de_bien_manger', 'Envie de Bien Manger', 'https://www.enviedebienmanger.fr/bons-de-reduction', 'scraping'],
        ['radins', 'Radins.com', 'https://www.radins.com/code-promo/', 'scraping'],
        ['tiktok', 'TikTok (saisie manuelle)', 'https://www.tiktok.com/', 'saisie_manuelle'],
    ];

    private const CREATEURS = [
        ['nelson_mrq', 'https://www.tiktok.com/@nelson_mrq'],
        ['lejusticierdbp', 'https://www.tiktok.com/@lejusticierdbp'],
    ];

    public function getDescription(): string
    {
        return 'Sources de collecte et créateurs TikTok suivis';
    }

    public function up(Schema $schema): void
    {
        foreach (self::SOURCES as [$code, $nom, $url, $type]) {
            $this->addSql(
                'INSERT INTO source (code, nom, url, type, actif) VALUES (:code, :nom, :url, :type, true) ON CONFLICT (code) DO NOTHING',
                ['code' => $code, 'nom' => $nom, 'url' => $url, 'type' => $type],
            );
        }

        foreach (self::CREATEURS as [$pseudo, $url]) {
            $this->addSql(
                "INSERT INTO watched_creator (pseudo, plateforme, url_profil, actif, ajoute_le) VALUES (:pseudo, 'tiktok', :url, true, NOW()) ON CONFLICT (plateforme, pseudo) DO NOTHING",
                ['pseudo' => $pseudo, 'url' => $url],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM watched_creator WHERE plateforme = 'tiktok' AND pseudo IN ('nelson_mrq', 'lejusticierdbp')");
        $this->addSql("DELETE FROM source WHERE code IN ('anti_crise_catalogue', 'anti_crise_remboursement', 'shopmium', 'envie_de_bien_manger', 'radins', 'tiktok')");
    }
}

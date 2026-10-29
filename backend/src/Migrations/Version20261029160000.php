<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Source des pages « lien en bio » des créateurs suivis.
 */
final class Version20261029160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Source « lien_bio » (pages Linktree / blogs des créateurs suivis)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO source (code, nom, url, type, actif) VALUES ('lien_bio', 'Liens en bio des créateurs', 'https://linktr.ee/', 'lien_bio', true) ON CONFLICT (code) DO NOTHING");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM source WHERE code = 'lien_bio'");
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * game jadvaliga match_at_uz qo'shadi — O'zbekiston (Asia/Tashkent) vaqti.
 */
final class Version20250128150100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'game jadvaliga match_at_uz (O\'zbekiston vaqti) ustunini qo\'shadi.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game ADD match_at_uz DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\' AFTER match_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP match_at_uz');
    }
}

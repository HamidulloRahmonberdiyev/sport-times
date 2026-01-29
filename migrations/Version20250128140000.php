<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * club, competition, game jadvallari.
 * TOP‑5 + UCL o'yinlari: klublar (asl + o'zbekcha nom), musobaqalar, o'yinlar vaqtlari.
 */
final class Version20250128140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'club, competition, game jadvallarini yaratadi (TOP‑5 + UCL o\'yinlar sync uchun).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE club (
            id INT AUTO_INCREMENT NOT NULL,
            external_id VARCHAR(64) NOT NULL,
            name_original VARCHAR(255) NOT NULL,
            name_uz VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uq_club_external_id (external_id),
            INDEX idx_club_name_original (name_original),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE competition (
            id INT AUTO_INCREMENT NOT NULL,
            external_id VARCHAR(64) NOT NULL,
            code VARCHAR(20) NOT NULL,
            name_original VARCHAR(255) NOT NULL,
            name_uz VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uq_competition_external_id (external_id),
            INDEX idx_competition_code (code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE game (
            id INT AUTO_INCREMENT NOT NULL,
            home_club_id INT NOT NULL,
            away_club_id INT NOT NULL,
            competition_id INT NOT NULL,
            external_id VARCHAR(64) NOT NULL,
            match_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            home_score SMALLINT DEFAULT NULL,
            away_score SMALLINT DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            venue VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uq_game_external_id (external_id),
            INDEX idx_game_match_at (match_at),
            INDEX idx_game_competition_id (competition_id),
            INDEX idx_game_match_at_competition (match_at, competition_id),
            INDEX idx_game_status (status),
            PRIMARY KEY(id),
            CONSTRAINT fk_game_home_club FOREIGN KEY (home_club_id) REFERENCES club (id) ON DELETE CASCADE,
            CONSTRAINT fk_game_away_club FOREIGN KEY (away_club_id) REFERENCES club (id) ON DELETE CASCADE,
            CONSTRAINT fk_game_competition FOREIGN KEY (competition_id) REFERENCES competition (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE game');
        $this->addSql('DROP TABLE competition');
        $this->addSql('DROP TABLE club');
    }
}

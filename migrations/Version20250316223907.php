<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250316223907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE moderation_request (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', conference_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', moderator_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', status VARCHAR(50) NOT NULL, comments LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_10D44400604B8382 (conference_id), INDEX IDX_10D44400D0AFA354 (moderator_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE moderation_request ADD CONSTRAINT FK_10D44400604B8382 FOREIGN KEY (conference_id) REFERENCES conference (id)');
        $this->addSql('ALTER TABLE moderation_request ADD CONSTRAINT FK_10D44400D0AFA354 FOREIGN KEY (moderator_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE moderation_request DROP FOREIGN KEY FK_10D44400604B8382');
        $this->addSql('ALTER TABLE moderation_request DROP FOREIGN KEY FK_10D44400D0AFA354');
        $this->addSql('DROP TABLE moderation_request');
    }
}

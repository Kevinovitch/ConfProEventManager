<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250318184017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE feedback (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', registration_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', rating SMALLINT NOT NULL, comment LONGTEXT DEFAULT NULL, submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', aspect_rated VARCHAR(255) DEFAULT NULL, INDEX IDX_D2294458833D8F43 (registration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE media (id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', conference_id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', type VARCHAR(50) NOT NULL, url VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, filename VARCHAR(255) DEFAULT NULL, file_size INT DEFAULT NULL, uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6A2CA10C604B8382 (conference_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE feedback ADD CONSTRAINT FK_D2294458833D8F43 FOREIGN KEY (registration_id) REFERENCES registration (id)');
        $this->addSql('ALTER TABLE media ADD CONSTRAINT FK_6A2CA10C604B8382 FOREIGN KEY (conference_id) REFERENCES conference (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE feedback DROP FOREIGN KEY FK_D2294458833D8F43');
        $this->addSql('ALTER TABLE media DROP FOREIGN KEY FK_6A2CA10C604B8382');
        $this->addSql('DROP TABLE feedback');
        $this->addSql('DROP TABLE media');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106163215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE couche_appliquee (id INT AUTO_INCREMENT NOT NULL, face VARCHAR(1) DEFAULT NULL, specification JSON DEFAULT NULL, orientation INT DEFAULT NULL, position VARCHAR(32) DEFAULT NULL, decalage JSON DEFAULT NULL, epaisseur_override INT DEFAULT NULL, ordre INT NOT NULL, role VARCHAR(64) NOT NULL, emplacement VARCHAR(64) NOT NULL, materiau_id INT DEFAULT NULL, systeme_applique_id INT NOT NULL, INDEX IDX_346C0B99CE19B47A (materiau_id), INDEX IDX_346C0B997141B9A9 (systeme_applique_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE systeme_applique (id INT AUTO_INCREMENT NOT NULL, variante VARCHAR(255) NOT NULL, actif TINYINT DEFAULT 0 NOT NULL, parametres JSON NOT NULL, definition VARCHAR(64) NOT NULL, surface_id INT DEFAULT NULL, cloison_id INT DEFAULT NULL, INDEX IDX_ECA8C54CCA11F534 (surface_id), INDEX IDX_ECA8C54CA0C51D75 (cloison_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE couche_appliquee ADD CONSTRAINT FK_346C0B99CE19B47A FOREIGN KEY (materiau_id) REFERENCES materiau (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE couche_appliquee ADD CONSTRAINT FK_346C0B997141B9A9 FOREIGN KEY (systeme_applique_id) REFERENCES systeme_applique (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE systeme_applique ADD CONSTRAINT FK_ECA8C54CCA11F534 FOREIGN KEY (surface_id) REFERENCES surface (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE systeme_applique ADD CONSTRAINT FK_ECA8C54CA0C51D75 FOREIGN KEY (cloison_id) REFERENCES cloison (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE couche_appliquee DROP FOREIGN KEY FK_346C0B99CE19B47A');
        $this->addSql('ALTER TABLE couche_appliquee DROP FOREIGN KEY FK_346C0B997141B9A9');
        $this->addSql('ALTER TABLE systeme_applique DROP FOREIGN KEY FK_ECA8C54CCA11F534');
        $this->addSql('ALTER TABLE systeme_applique DROP FOREIGN KEY FK_ECA8C54CA0C51D75');
        $this->addSql('DROP TABLE couche_appliquee');
        $this->addSql('DROP TABLE systeme_applique');
    }
}

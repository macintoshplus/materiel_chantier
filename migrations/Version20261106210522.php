<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106210522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equipement (id INT AUTO_INCREMENT NOT NULL, famille VARCHAR(16) NOT NULL, type VARCHAR(32) NOT NULL, nom VARCHAR(255) DEFAULT NULL, u INT NOT NULL, v INT NOT NULL, parametres JSON NOT NULL, reseau_id INT DEFAULT NULL, piece_id INT NOT NULL, surface_id INT NOT NULL, INDEX IDX_B8B4C6F3445D170C (reseau_id), INDEX IDX_B8B4C6F3C40FCFA8 (piece_id), INDEX IDX_B8B4C6F3CA11F534 (surface_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reseau (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, famille VARCHAR(16) NOT NULL, type VARCHAR(16) NOT NULL, section NUMERIC(5, 2) NOT NULL, protection INT NOT NULL, parametres JSON NOT NULL, projet_id INT NOT NULL, INDEX IDX_CDE52CB8C18272 (projet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE equipement ADD CONSTRAINT FK_B8B4C6F3445D170C FOREIGN KEY (reseau_id) REFERENCES reseau (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE equipement ADD CONSTRAINT FK_B8B4C6F3C40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipement ADD CONSTRAINT FK_B8B4C6F3CA11F534 FOREIGN KEY (surface_id) REFERENCES surface (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reseau ADD CONSTRAINT FK_CDE52CB8C18272 FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipement DROP FOREIGN KEY FK_B8B4C6F3445D170C');
        $this->addSql('ALTER TABLE equipement DROP FOREIGN KEY FK_B8B4C6F3C40FCFA8');
        $this->addSql('ALTER TABLE equipement DROP FOREIGN KEY FK_B8B4C6F3CA11F534');
        $this->addSql('ALTER TABLE reseau DROP FOREIGN KEY FK_CDE52CB8C18272');
        $this->addSql('DROP TABLE equipement');
        $this->addSql('DROP TABLE reseau');
    }
}

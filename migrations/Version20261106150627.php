<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106150627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE jonction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(32) NOT NULL, sommet_debut_b VARCHAR(32) DEFAULT NULL, sommet_fin_b VARCHAR(32) DEFAULT NULL, debut_mm_b INT DEFAULT NULL, fin_mm_b INT DEFAULT NULL, sommet_debut_a VARCHAR(32) NOT NULL, sommet_fin_a VARCHAR(32) NOT NULL, piece_id INT NOT NULL, surface_a_id INT NOT NULL, surface_b_id INT DEFAULT NULL, INDEX IDX_5EAFC0F2C40FCFA8 (piece_id), INDEX IDX_5EAFC0F2244E7ABB (surface_a_id), INDEX IDX_5EAFC0F236FBD555 (surface_b_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE jonction ADD CONSTRAINT FK_5EAFC0F2C40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE jonction ADD CONSTRAINT FK_5EAFC0F2244E7ABB FOREIGN KEY (surface_a_id) REFERENCES surface (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE jonction ADD CONSTRAINT FK_5EAFC0F236FBD555 FOREIGN KEY (surface_b_id) REFERENCES surface (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE jonction DROP FOREIGN KEY FK_5EAFC0F2C40FCFA8');
        $this->addSql('ALTER TABLE jonction DROP FOREIGN KEY FK_5EAFC0F2244E7ABB');
        $this->addSql('ALTER TABLE jonction DROP FOREIGN KEY FK_5EAFC0F236FBD555');
        $this->addSql('DROP TABLE jonction');
    }
}

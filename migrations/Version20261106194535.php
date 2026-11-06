<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106194535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chiffrage (id INT AUTO_INCREMENT NOT NULL, total_ttc_centimes INT DEFAULT 0 NOT NULL, libelle VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, strategie_fournisseur VARCHAR(32) NOT NULL, parametres JSON NOT NULL, avertissements JSON NOT NULL, projet_id INT NOT NULL, INDEX IDX_C95FD4EAC18272 (projet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ligne_chiffrage (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(16) NOT NULL, piece_nom VARCHAR(255) DEFAULT NULL, surface_nom VARCHAR(255) DEFAULT NULL, categorie VARCHAR(48) DEFAULT NULL, materiau_id INT DEFAULT NULL, materiau_reference VARCHAR(64) DEFAULT NULL, materiau_libelle VARCHAR(255) DEFAULT NULL, longueur INT DEFAULT NULL, largeur INT DEFAULT NULL, epaisseur INT DEFAULT NULL, hauteur INT DEFAULT NULL, reference_initiale VARCHAR(64) DEFAULT NULL, fournisseur_nom VARCHAR(255) DEFAULT NULL, fournisseur_adresse LONGTEXT DEFAULT NULL, reference_fournisseur VARCHAR(64) DEFAULT NULL, quantite_besoin INT DEFAULT 0 NOT NULL, marge_centiemes INT DEFAULT 0 NOT NULL, quantite_avec_marge_milli INT DEFAULT 0 NOT NULL, unites_par_lot INT DEFAULT 1 NOT NULL, nombre_lots INT DEFAULT 0 NOT NULL, quantite_achetee INT DEFAULT 0 NOT NULL, prix_lot_centimes INT DEFAULT NULL, total_centimes INT DEFAULT 0 NOT NULL, chute INT DEFAULT 0 NOT NULL, taux_perte_centiemes INT DEFAULT 0 NOT NULL, prix_materiau_id INT DEFAULT NULL, prix_manquant TINYINT DEFAULT 0 NOT NULL, chiffrage_id INT NOT NULL, piece_id INT DEFAULT NULL, surface_id INT DEFAULT NULL, fournisseur_id INT DEFAULT NULL, INDEX idx_ligne_chiffrage_type (chiffrage_id, type), INDEX IDX_6BADB62DCB4DCF1 (chiffrage_id), INDEX IDX_6BADB62C40FCFA8 (piece_id), INDEX IDX_6BADB62CA11F534 (surface_id), INDEX IDX_6BADB62670C757F (fournisseur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chiffrage ADD CONSTRAINT FK_C95FD4EAC18272 FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_chiffrage ADD CONSTRAINT FK_6BADB62DCB4DCF1 FOREIGN KEY (chiffrage_id) REFERENCES chiffrage (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_chiffrage ADD CONSTRAINT FK_6BADB62C40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ligne_chiffrage ADD CONSTRAINT FK_6BADB62CA11F534 FOREIGN KEY (surface_id) REFERENCES surface (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE ligne_chiffrage ADD CONSTRAINT FK_6BADB62670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chiffrage DROP FOREIGN KEY FK_C95FD4EAC18272');
        $this->addSql('ALTER TABLE ligne_chiffrage DROP FOREIGN KEY FK_6BADB62DCB4DCF1');
        $this->addSql('ALTER TABLE ligne_chiffrage DROP FOREIGN KEY FK_6BADB62C40FCFA8');
        $this->addSql('ALTER TABLE ligne_chiffrage DROP FOREIGN KEY FK_6BADB62CA11F534');
        $this->addSql('ALTER TABLE ligne_chiffrage DROP FOREIGN KEY FK_6BADB62670C757F');
        $this->addSql('DROP TABLE chiffrage');
        $this->addSql('DROP TABLE ligne_chiffrage');
    }
}

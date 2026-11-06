<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106142110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cloison (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, debut JSON NOT NULL, fin JSON NOT NULL, epaisseur INT DEFAULT NULL, ancrage_debut JSON NOT NULL, ancrage_fin JSON NOT NULL, parametres JSON NOT NULL, piece_id INT NOT NULL, INDEX IDX_1B90F30CC40FCFA8 (piece_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fournisseur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, adresse_ligne1 VARCHAR(255) DEFAULT NULL, adresse_ligne2 VARCHAR(255) DEFAULT NULL, code_postal VARCHAR(16) DEFAULT NULL, ville VARCHAR(128) DEFAULT NULL, pays VARCHAR(64) DEFAULT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, site_web VARCHAR(255) DEFAULT NULL, telephone VARCHAR(32) DEFAULT NULL, actif TINYINT DEFAULT 1 NOT NULL, frais_livraison_ttc NUMERIC(10, 2) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE materiau (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(64) NOT NULL, libelle VARCHAR(255) NOT NULL, categorie VARCHAR(48) NOT NULL, longueur INT DEFAULT NULL, largeur INT DEFAULT NULL, epaisseur INT DEFAULT NULL, hauteur INT DEFAULT NULL, unite_vente VARCHAR(32) NOT NULL, unites_par_lot INT DEFAULT 1 NOT NULL, poids NUMERIC(8, 3) DEFAULT NULL, marge_securite_pct NUMERIC(5, 2) DEFAULT NULL, caracteristiques JSON NOT NULL, actif TINYINT DEFAULT 1 NOT NULL, INDEX idx_materiau_categorie (categorie), UNIQUE INDEX uniq_materiau_reference (reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ouverture (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) DEFAULT NULL, type VARCHAR(32) NOT NULL, u INT NOT NULL, v INT NOT NULL, largeur INT NOT NULL, hauteur INT NOT NULL, polygone JSON DEFAULT NULL, type_pose VARCHAR(16) NOT NULL, type_contour VARCHAR(32) DEFAULT NULL, refoncement INT NOT NULL, parametres JSON NOT NULL, surface_id INT DEFAULT NULL, cloison_id INT DEFAULT NULL, INDEX IDX_43461EABCA11F534 (surface_id), INDEX IDX_43461EABA0C51D75 (cloison_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE piece (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, hauteur_sous_plafond INT DEFAULT NULL, forme_sol JSON NOT NULL, source_mesures VARCHAR(16) NOT NULL, projet_id INT NOT NULL, INDEX IDX_44CA0B23C18272 (projet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prix_materiau (id INT AUTO_INCREMENT NOT NULL, reference_fournisseur VARCHAR(64) DEFAULT NULL, date_fin DATETIME DEFAULT NULL, prix_lot_ttc NUMERIC(10, 2) NOT NULL, date_debut DATETIME NOT NULL, unites_par_lot INT DEFAULT NULL, source VARCHAR(16) NOT NULL, taux_tva NUMERIC(4, 2) DEFAULT NULL, materiau_id INT NOT NULL, fournisseur_id INT NOT NULL, INDEX idx_prix_materiau_fournisseur (materiau_id, fournisseur_id), INDEX IDX_63EAC427CE19B47A (materiau_id), INDEX IDX_63EAC427670C757F (fournisseur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE projet (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, adresse_chantier VARCHAR(255) DEFAULT NULL, latitude NUMERIC(10, 7) DEFAULT NULL, longitude NUMERIC(10, 7) DEFAULT NULL, parametres JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE surface (id INT AUTO_INCREMENT NOT NULL, cote VARCHAR(1) DEFAULT NULL, nom VARCHAR(255) NOT NULL, origine_3d JSON NOT NULL, vecteur_u JSON NOT NULL, vecteur_v JSON NOT NULL, polygone JSON NOT NULL, contraintes JSON NOT NULL, type VARCHAR(16) NOT NULL, cloison_id INT DEFAULT NULL, piece_id INT NOT NULL, INDEX IDX_BC419E6EA0C51D75 (cloison_id), INDEX IDX_BC419E6EC40FCFA8 (piece_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE cloison ADD CONSTRAINT FK_1B90F30CC40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ouverture ADD CONSTRAINT FK_43461EABCA11F534 FOREIGN KEY (surface_id) REFERENCES surface (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ouverture ADD CONSTRAINT FK_43461EABA0C51D75 FOREIGN KEY (cloison_id) REFERENCES cloison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE piece ADD CONSTRAINT FK_44CA0B23C18272 FOREIGN KEY (projet_id) REFERENCES projet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prix_materiau ADD CONSTRAINT FK_63EAC427CE19B47A FOREIGN KEY (materiau_id) REFERENCES materiau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE prix_materiau ADD CONSTRAINT FK_63EAC427670C757F FOREIGN KEY (fournisseur_id) REFERENCES fournisseur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE surface ADD CONSTRAINT FK_BC419E6EA0C51D75 FOREIGN KEY (cloison_id) REFERENCES cloison (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE surface ADD CONSTRAINT FK_BC419E6EC40FCFA8 FOREIGN KEY (piece_id) REFERENCES piece (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cloison DROP FOREIGN KEY FK_1B90F30CC40FCFA8');
        $this->addSql('ALTER TABLE ouverture DROP FOREIGN KEY FK_43461EABCA11F534');
        $this->addSql('ALTER TABLE ouverture DROP FOREIGN KEY FK_43461EABA0C51D75');
        $this->addSql('ALTER TABLE piece DROP FOREIGN KEY FK_44CA0B23C18272');
        $this->addSql('ALTER TABLE prix_materiau DROP FOREIGN KEY FK_63EAC427CE19B47A');
        $this->addSql('ALTER TABLE prix_materiau DROP FOREIGN KEY FK_63EAC427670C757F');
        $this->addSql('ALTER TABLE surface DROP FOREIGN KEY FK_BC419E6EA0C51D75');
        $this->addSql('ALTER TABLE surface DROP FOREIGN KEY FK_BC419E6EC40FCFA8');
        $this->addSql('DROP TABLE cloison');
        $this->addSql('DROP TABLE fournisseur');
        $this->addSql('DROP TABLE materiau');
        $this->addSql('DROP TABLE ouverture');
        $this->addSql('DROP TABLE piece');
        $this->addSql('DROP TABLE prix_materiau');
        $this->addSql('DROP TABLE projet');
        $this->addSql('DROP TABLE surface');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

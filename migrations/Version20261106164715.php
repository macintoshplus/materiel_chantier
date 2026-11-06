<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261106164715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE couche_existante (id INT AUTO_INCREMENT NOT NULL, epaisseur INT DEFAULT NULL, r_forfaitaire_milli INT DEFAULT NULL, ordre INT NOT NULL, materiau_id INT DEFAULT NULL, paroi_id INT NOT NULL, INDEX IDX_3160435ECE19B47A (materiau_id), INDEX IDX_3160435E6615E155 (paroi_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paroi_existante (id INT AUTO_INCREMENT NOT NULL, surface_id INT NOT NULL, UNIQUE INDEX UNIQ_6266D92BCA11F534 (surface_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE couche_existante ADD CONSTRAINT FK_3160435ECE19B47A FOREIGN KEY (materiau_id) REFERENCES materiau (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE couche_existante ADD CONSTRAINT FK_3160435E6615E155 FOREIGN KEY (paroi_id) REFERENCES paroi_existante (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE paroi_existante ADD CONSTRAINT FK_6266D92BCA11F534 FOREIGN KEY (surface_id) REFERENCES surface (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE couche_existante DROP FOREIGN KEY FK_3160435ECE19B47A');
        $this->addSql('ALTER TABLE couche_existante DROP FOREIGN KEY FK_3160435E6615E155');
        $this->addSql('ALTER TABLE paroi_existante DROP FOREIGN KEY FK_6266D92BCA11F534');
        $this->addSql('DROP TABLE couche_existante');
        $this->addSql('DROP TABLE paroi_existante');
    }
}

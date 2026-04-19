<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418091538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_media (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(16) NOT NULL, url VARCHAR(2048) NOT NULL, position INT NOT NULL, `primary` TINYINT NOT NULL, product_id INT NOT NULL, variant_id INT DEFAULT NULL, INDEX IDX_CB70DA504584665A (product_id), INDEX IDX_CB70DA503B69A9AF (variant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_variant (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, sku VARCHAR(255) DEFAULT NULL, stock INT NOT NULL, active TINYINT NOT NULL, position INT NOT NULL, product_id INT NOT NULL, INDEX IDX_209AA41D4584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product_media ADD CONSTRAINT FK_CB70DA504584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_media ADD CONSTRAINT FK_CB70DA503B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE product_variant ADD CONSTRAINT FK_209AA41D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product ADD promo_tiers JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_media DROP FOREIGN KEY FK_CB70DA504584665A');
        $this->addSql('ALTER TABLE product_media DROP FOREIGN KEY FK_CB70DA503B69A9AF');
        $this->addSql('ALTER TABLE product_variant DROP FOREIGN KEY FK_209AA41D4584665A');
        $this->addSql('DROP TABLE product_media');
        $this->addSql('DROP TABLE product_variant');
        $this->addSql('ALTER TABLE product DROP promo_tiers');
    }
}

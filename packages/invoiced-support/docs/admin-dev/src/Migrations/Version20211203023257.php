<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211203023257 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE cs_contracts (id INT AUTO_INCREMENT NOT NULL, customer VARCHAR(255) NOT NULL, start_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', end_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', created_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', invoiced_customer VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cs_orders CHANGE date date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE date_fulfilled date_fulfilled DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE start_date start_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', ADD contract_id INT DEFAULT NULL, ADD CONSTRAINT FK_2048FAAF2576E0FD FOREIGN KEY (contract_id) REFERENCES cs_contracts (id)');
        $this->addSql('CREATE INDEX IDX_2048FAAF2576E0FD ON cs_orders (contract_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders CHANGE date date DATE NOT NULL, CHANGE start_date start_date DATE NOT NULL, CHANGE date_fulfilled date_fulfilled DATETIME DEFAULT NULL');
        $this->addSql('DROP TABLE cs_contracts');
        $this->addSql('ALTER TABLE cs_orders DROP FOREIGN KEY FK_2048FAAF2576E0FD');
        $this->addSql('DROP INDEX IDX_2048FAAF2576E0FD ON cs_orders');
        $this->addSql('ALTER TABLE cs_orders DROP contract_id');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

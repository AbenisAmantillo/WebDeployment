<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification table for admin-to-client mobile app messages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL, recipient_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_BF5476CAE92F8F78 (recipient_id), INDEX IDX_BF5476CAB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAE92F8F78 FOREIGN KEY (recipient_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAE92F8F78');
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAB03A8386');
        $this->addSql('DROP TABLE notification');
    }
}

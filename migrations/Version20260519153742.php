<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519153742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_log (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) NOT NULL, roles JSON NOT NULL, action VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE furniture (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, status VARCHAR(255) NOT NULL, stock INT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, property_id INT DEFAULT NULL, INDEX IDX_665DDAB3549213EC (property_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, amount DOUBLE PRECISION NOT NULL, payment_method VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, date DATETIME NOT NULL, customer_id INT NOT NULL, transaction_id INT NOT NULL, INDEX IDX_6D28840D9395C3F3 (customer_id), INDEX IDX_6D28840D2FC0CB0F (transaction_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE property (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status LONGTEXT NOT NULL, price DOUBLE PRECISION NOT NULL, address VARCHAR(255) NOT NULL, image_file_name VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, purchase_type VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, date DATETIME NOT NULL, customer_id INT NOT NULL, property_id INT NOT NULL, INDEX IDX_723705D19395C3F3 (customer_id), INDEX IDX_723705D1549213EC (property_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction_furniture (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, transaction_id INT NOT NULL, furniture_id INT NOT NULL, INDEX IDX_257A41F02FC0CB0F (transaction_id), INDEX IDX_257A41F0CF5485C3 (furniture_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, is_enabled TINYINT NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, verification_token VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME (username), UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE furniture ADD CONSTRAINT FK_665DDAB3549213EC FOREIGN KEY (property_id) REFERENCES property (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9395C3F3 FOREIGN KEY (customer_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D19395C3F3 FOREIGN KEY (customer_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1549213EC FOREIGN KEY (property_id) REFERENCES property (id)');
        $this->addSql('ALTER TABLE transaction_furniture ADD CONSTRAINT FK_257A41F02FC0CB0F FOREIGN KEY (transaction_id) REFERENCES transaction (id)');
        $this->addSql('ALTER TABLE transaction_furniture ADD CONSTRAINT FK_257A41F0CF5485C3 FOREIGN KEY (furniture_id) REFERENCES furniture (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE furniture DROP FOREIGN KEY FK_665DDAB3549213EC');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9395C3F3');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D2FC0CB0F');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D19395C3F3');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1549213EC');
        $this->addSql('ALTER TABLE transaction_furniture DROP FOREIGN KEY FK_257A41F02FC0CB0F');
        $this->addSql('ALTER TABLE transaction_furniture DROP FOREIGN KEY FK_257A41F0CF5485C3');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('DROP TABLE furniture');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE property');
        $this->addSql('DROP TABLE transaction');
        $this->addSql('DROP TABLE transaction_furniture');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}

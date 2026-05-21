<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store client-submitted payment details on transaction until staff confirms receipt';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction ADD client_downpayment_amount DOUBLE PRECISION DEFAULT NULL, ADD client_payment_plan_months INT DEFAULT NULL, ADD client_payment_method VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction DROP client_downpayment_amount, DROP client_payment_plan_months, DROP client_payment_method');
    }
}

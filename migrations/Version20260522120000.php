<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist confirmed rent payment plan months (12, 24, or 36) on transaction';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction ADD payment_plan_months INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE transaction DROP payment_plan_months');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profile_image_file_name column to user table';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->getTable('user');
        if ($user->hasColumn('profile_image_file_name')) {
            return;
        }

        $this->addSql('ALTER TABLE `user` ADD profile_image_file_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP profile_image_file_name');
    }
}

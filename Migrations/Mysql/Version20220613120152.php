<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220613120152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform() instanceof AbstractPlatform
            && $this->connection->getDatabasePlatform()->getName() !== "mysql",
            "Migration can only be executed safely on MySql and MariaDB."
        );

        $this->addSql(<<<SQL
    CREATE TABLE codeq_linkchecker_domain_model_resultitem (
        persistence_object_identifier VARCHAR(40) NOT NULL,
        domain VARCHAR(255) NOT NULL,
        source VARCHAR(2000) NULL,
        sourcepath VARCHAR(2000) NULL,
        target VARCHAR(2000) NOT NULL,
        statuscode INT NOT NULL,
        `ignore` TINYINT(1) NOT NULL,
        createdat DATETIME NOT NULL,
        checkedat DATETIME NOT NULL,
        PRIMARY KEY(persistence_object_identifier)
    )
    DEFAULT CHARACTER SET utf8mb4
    COLLATE `utf8mb4_unicode_ci`
    ENGINE = InnoDB
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform() instanceof AbstractPlatform
            && $this->connection->getDatabasePlatform()->getName() !== "mysql",
            "Migration can only be executed safely on MySql and MariaDB."
        );

        $this->addSql('DROP TABLE codeq_linkchecker_domain_model_resultitem');
    }
}

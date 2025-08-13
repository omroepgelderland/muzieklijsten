<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

/**
 * Functies met structuurwijzigingen in de database van versie-updates.
 * Voeg hier bij wijzingen nieuwe functies toe met een nieuw volgnummer.
 * Voer de wijzigingen ook door in install/install.sql
 * Zie ook bin/update.php.
 */
class DBUpdates
{
    public function __construct(
        private DB $db,
    ) {
    }

    /**
     * Update vanaf database zonder versienummers.
     */
    public function update_1(): void
    {
        $this->db->query(<<<EOT
        CREATE TABLE `versie` (
            `versie` int(10) unsigned NOT NULL,
            PRIMARY KEY (`versie`)
           ) ENGINE=InnoDB;
        EOT);
        $this->db->insertMulti('versie', ['versie' => 1]);
    }

    public function update_2(): void
    {
        $this->db->query(<<<EOT
        ALTER TABLE `lijsten`
            DROP `veld_telefoonnummer`,
            DROP `veld_email`,
            DROP `veld_woonplaats`,
            DROP `veld_adres`,
            DROP `veld_uitzenddatum`,
            DROP `veld_vrijekeus`;
        EOT);
        $this->db->query(<<<EOT
        ALTER TABLE `stemmers`
            DROP `naam`,
            DROP `adres`,
            DROP `postcode`,
            DROP `woonplaats`,
            DROP `telefoonnummer`,
            DROP `emailadres`,
            DROP `uitzenddatum`,
            DROP `vrijekeus`;
        EOT);
    }

    public function update_3(): void
    {
        $this->db->query('ALTER TABLE `lijsten` ADD `vrijekeuzes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `maxkeuzes`;');
        $this->db->query('ALTER TABLE `nummers` ADD `is_vrijekeuze` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `opener`');
        $this->db->query('ALTER TABLE stemmen DROP FOREIGN KEY stemmen_ibfk_5');
        $this->db->query('ALTER TABLE `stemmen` ADD `is_vrijekeuze` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `behandeld`');
    }

    public function update_4(): void
    {
        $this->db->query(
            'ALTER TABLE `lijsten` ADD `mail_stemmers` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `bedankt_tekst`'
        );
    }

    public function update_5(): void
    {
        $this->db->query(
            'ALTER TABLE lijsten ADD random_volgorde TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER mail_stemmers'
        );
    }

    public function update_6(): void
    {
        $this->db->query(<<<EOT
        ALTER TABLE `stemmers`
            ADD `lijst_id` INT UNSIGNED NOT NULL AFTER `id`, ADD INDEX (`lijst_id`);
        EOT);
        $this->db->query(<<<EOT
        UPDATE stemmers
        JOIN stemmen ON
            stemmers.id = stemmen.stemmer_id
        SET
            stemmers.lijst_id = stemmen.lijst_id
        EOT);
        $this->db->query(<<<EOT
        ALTER TABLE `stemmers`
            ADD FOREIGN KEY (`lijst_id`) REFERENCES `lijsten`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        EOT);

        // alle indexen droppen in stemmen behalve stemmer_id
        $this->db->query(<<<EOT
        ALTER TABLE `stemmen` DROP INDEX `lijst_id_2`;
        EOT);
        $this->db->query(<<<EOT
        ALTER TABLE `stemmen` DROP INDEX `lijst_id`;
        EOT);
        $this->db->query(<<<EOT
        ALTER TABLE `stemmen` DROP PRIMARY KEY;
        EOT);

        // drop foreign key lijst_id en dan kolom lijst_id
        $this->db->query(<<<EOT
        ALTER TABLE stemmen DROP FOREIGN KEY stemmen_ibfk_2;
        EOT);
        $this->db->query(<<<EOT
        ALTER TABLE `stemmen` DROP `lijst_id`;
        EOT);

        $this->db->query(<<<EOT
        ALTER TABLE `stemmen` ADD UNIQUE (`nummer_id`, `stemmer_id`);
        EOT);
        $this->db->query(<<<EOT
        ALTER TABLE `stemmen` ADD PRIMARY KEY (`nummer_id`, `stemmer_id`);
        EOT);
        $this->db->query(<<<EOT
        RENAME TABLE `stemmen` TO `stemmers_nummers`;
        EOT);
    }

    public function update_7(): void
    {
        $this->db->query(<<<EOT
        ALTER TABLE `nummers`
        ADD `duur` INT UNSIGNED NULL DEFAULT NULL AFTER `opener`;
        EOT);
    }
}

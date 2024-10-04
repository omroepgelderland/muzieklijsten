<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

/**
 * Functies met structuurwijzigingen in de database van versie-updates.
 * Voeg hier bij wijzingen nieuwe functies toe met een nieuw volgnummer.
 * Voer de wijzigingen ook door in install/install.sql
 * Zie ook bin/update.php.
 */
class DBUpdates {

    /**
     * Update vanaf database zonder versienummers.
     */
    public static function update_1(): void {
        DB::query(<<<EOT
        CREATE TABLE `versie` (
            `versie` int(10) unsigned NOT NULL,
            PRIMARY KEY (`versie`)
           ) ENGINE=InnoDB;
        EOT);
        DB::insertMulti('versie', ['versie' => 1]);
    }

    public static function update_2(): void {
        DB::query(<<<EOT
        ALTER TABLE `lijsten`
            DROP `veld_telefoonnummer`,
            DROP `veld_email`,
            DROP `veld_woonplaats`,
            DROP `veld_adres`,
            DROP `veld_uitzenddatum`,
            DROP `veld_vrijekeus`;
        EOT);
        DB::query(<<<EOT
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

    public static function update_3(): void {
        DB::query('ALTER TABLE `lijsten` ADD `vrijekeuzes` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `maxkeuzes`;');
        DB::query('ALTER TABLE `nummers` ADD `is_vrijekeuze` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `opener`');
        DB::query('ALTER TABLE stemmen DROP FOREIGN KEY stemmen_ibfk_5');
        DB::query('ALTER TABLE `stemmen` ADD `is_vrijekeuze` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `behandeld`');
    }

    public static function update_4(): void {
        DB::query(
            'ALTER TABLE `lijsten` ADD `mail_stemmers` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `bedankt_tekst`'
        );
    }

    public static function update_5(): void {
        DB::query(
            'ALTER TABLE lijsten ADD random_volgorde TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER mail_stemmers'
        );
    }

}

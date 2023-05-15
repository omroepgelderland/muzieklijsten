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

}

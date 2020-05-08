## Database aanpassingen
CREATE TABLE `rtvgelderland`.`muzieklijst_extra_velden` ( `id` INT UNSIGNED NOT NULL AUTO_INCREMENT , `label` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL , `placeholder` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' , `type` VARCHAR(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'text' , `verplicht` TINYINT UNSIGNED NOT NULL DEFAULT '0' , `leeg_feedback` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'Vul het ontbrekende veld in a.u.b.' , PRIMARY KEY (`id`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

CREATE TABLE `rtvgelderland`.`muzieklijst_lijsten_extra_velden` ( `lijst_id` INT NOT NULL , `extra_veld_id` INT UNSIGNED NOT NULL , `verplicht` TINYINT UNSIGNED NOT NULL DEFAULT '0' , PRIMARY KEY (`lijst_id`, `extra_veld_id`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

ALTER TABLE `muzieklijst_extra_velden` DROP `verplicht`;

CREATE TABLE `rtvgelderland`.`muzieklijst_stemmers_extra_velden` ( `stemmer_id` INT NOT NULL , `extra_veld_id` INT UNSIGNED NOT NULL , `waarde` VARCHAR(1024) CHARACTER SET utf8 COLLATE utf8_general_ci NULL , PRIMARY KEY (`stemmer_id`, `extra_veld_id`)) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;

ALTER TABLE `muzieklijst_lijsten_extra_velden` ADD FOREIGN KEY (`lijst_id`) REFERENCES `rtvgelderland`.`muzieklijst_lijsten`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `muzieklijst_lijsten_extra_velden` ADD FOREIGN KEY (`extra_veld_id`) REFERENCES `rtvgelderland`.`muzieklijst_extra_velden`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

DELETE FROM muzieklijst_nummers_lijst WHERE nummer_id NOT IN (SELECT id FROM muzieklijst_nummers);

ALTER TABLE `muzieklijst_nummers_lijst` ADD FOREIGN KEY (`nummer_id`) REFERENCES `rtvgelderland`.`muzieklijst_nummers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `muzieklijst_nummers_lijst` ADD FOREIGN KEY (`lijst_id`) REFERENCES `rtvgelderland`.`muzieklijst_lijsten`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `muzieklijst_stemmen` ADD INDEX(`nummer_id`);

ALTER TABLE `muzieklijst_stemmen` ADD INDEX(`lijst_id`);

ALTER TABLE `muzieklijst_stemmen` ADD INDEX(`stemmer_id`);

ALTER TABLE `muzieklijst_stemmen` ADD FOREIGN KEY (`nummer_id`) REFERENCES `rtvgelderland`.`muzieklijst_nummers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `muzieklijst_stemmen` ADD FOREIGN KEY (`lijst_id`) REFERENCES `rtvgelderland`.`muzieklijst_lijsten`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `muzieklijst_stemmen` ADD FOREIGN KEY (`stemmer_id`) REFERENCES `rtvgelderland`.`muzieklijst_stemmers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `muzieklijst_stemmers_extra_velden` ADD FOREIGN KEY (`stemmer_id`) REFERENCES `rtvgelderland`.`muzieklijst_stemmers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `muzieklijst_stemmers_extra_velden` ADD FOREIGN KEY (`extra_veld_id`) REFERENCES `rtvgelderland`.`muzieklijst_extra_velden`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

INSERT INTO `muzieklijst_lijsten` (`actief`, `naam`, `minkeuzes`, `maxkeuzes`, `stemmen_per_ip`, `artiest_eenmalig`, `veld_telefoonnummer`, `veld_email`, `veld_woonplaats`, `veld_adres`, `veld_uitzenddatum`, `veld_vrijekeus`, `recaptcha`, `email`) VALUES
(1, 'Zorghelden', 1, 1, NULL, 0, 1, 1, 0, 0, 0, 0, 0, 'rglaser@gld.nl');

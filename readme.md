## Database aanpassingen
ALTER TABLE `muzieklijst_stemmen` ADD FOREIGN KEY (`nummer_id`) REFERENCES `rtvgelderland`.`muzieklijst_nummers`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;






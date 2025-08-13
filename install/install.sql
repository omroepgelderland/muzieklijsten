-- Structuur
CREATE TABLE `blacklist` (
  `ip` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE `lijsten` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actief` tinyint(1) UNSIGNED NOT NULL DEFAULT 1,
  `naam` varchar(255) NOT NULL,
  `minkeuzes` int(2) UNSIGNED NOT NULL DEFAULT 0,
  `maxkeuzes` int(2) UNSIGNED NOT NULL DEFAULT 0,
  `vrijekeuzes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `stemmen_per_ip` int(2) UNSIGNED DEFAULT NULL,
  `artiest_eenmalig` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `recaptcha` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `email` varchar(100) DEFAULT NULL,
  `bedankt_tekst` varchar(4096) NOT NULL DEFAULT 'Bedankt voor je keuze.',
  `mail_stemmers` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `random_volgorde` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `lijsten_nummers` (
  `nummer_id` int(10) UNSIGNED NOT NULL,
  `lijst_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`nummer_id`,`lijst_id`),
  KEY `lijst_id` (`lijst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `lijsten_velden` (
  `lijst_id` int(10) UNSIGNED NOT NULL,
  `veld_id` int(10) UNSIGNED NOT NULL,
  `verplicht` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`lijst_id`,`veld_id`),
  KEY `extra_veld_id` (`veld_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `nummers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `muziek_id` varchar(20) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `titel` varchar(128) NOT NULL,
  `artiest` varchar(128) NOT NULL,
  `jaar` year(4) DEFAULT NULL,
  `categorie` varchar(256) DEFAULT NULL,
  `map` varchar(256) DEFAULT NULL,
  `opener` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `duur` int(10) UNSIGNED DEFAULT NULL,
  `is_vrijekeuze` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `muziek_id` (`muziek_id`),
  UNIQUE KEY `artiest` (`artiest`,`titel`,`jaar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `stemmers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lijst_id` int(10) UNSIGNED NOT NULL,
  `ip` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_geanonimiseerd` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `lijst_id` (`lijst_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `stemmers_nummers` (
  `nummer_id` int(10) UNSIGNED NOT NULL,
  `stemmer_id` int(10) UNSIGNED NOT NULL,
  `toelichting` text DEFAULT NULL,
  `behandeld` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `is_vrijekeuze` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`nummer_id`,`stemmer_id`),
  KEY `stemmer_id` (`stemmer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `stemmers_velden` (
  `stemmer_id` int(10) UNSIGNED NOT NULL,
  `veld_id` int(10) UNSIGNED NOT NULL,
  `waarde` varchar(1024) NOT NULL,
  PRIMARY KEY (`stemmer_id`,`veld_id`),
  KEY `extra_veld_id` (`veld_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `velden` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `label` varchar(64) NOT NULL,
  `leeg_feedback` varchar(128) NOT NULL DEFAULT 'Vul het ontbrekende veld in a.u.b.',
  `max` int(10) UNSIGNED DEFAULT NULL,
  `maxlength` int(10) UNSIGNED DEFAULT NULL,
  `min` int(10) UNSIGNED DEFAULT NULL,
  `minlength` int(10) UNSIGNED DEFAULT NULL,
  `placeholder` varchar(128) DEFAULT NULL,
  `type` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'text',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci ROW_FORMAT=COMPACT;

CREATE TABLE `versie` (
  `versie` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`versie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

-- Constraints
ALTER TABLE `lijsten_nummers`
  ADD CONSTRAINT `lijsten_nummers_ibfk_1` FOREIGN KEY (`lijst_id`) REFERENCES `lijsten` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `lijsten_nummers_ibfk_2` FOREIGN KEY (`nummer_id`) REFERENCES `nummers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `lijsten_velden`
  ADD CONSTRAINT `lijsten_velden_ibfk_1` FOREIGN KEY (`lijst_id`) REFERENCES `lijsten` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `lijsten_velden_ibfk_2` FOREIGN KEY (`veld_id`) REFERENCES `velden` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `stemmers`
  ADD CONSTRAINT `stemmers_ibfk_1` FOREIGN KEY (`lijst_id`) REFERENCES `lijsten` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `stemmers_nummers`
  ADD CONSTRAINT `stemmers_nummers_ibfk_4` FOREIGN KEY (`nummer_id`) REFERENCES `nummers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `stemmers_nummers_ibfk_6` FOREIGN KEY (`stemmer_id`) REFERENCES `stemmers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `stemmers_velden`
  ADD CONSTRAINT `stemmers_velden_ibfk_1` FOREIGN KEY (`stemmer_id`) REFERENCES `stemmers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `stemmers_velden_ibfk_2` FOREIGN KEY (`veld_id`) REFERENCES `velden` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- Data
INSERT IGNORE INTO `velden` (`label`, `leeg_feedback`, `max`, `maxlength`, `min`, `minlength`, `placeholder`, `type`) VALUES
('Naam', 'Vul uw naam in a.u.b.', NULL, 100, NULL, NULL, '', 'text'),
('Adres', 'Vul uw adres in a.u.b.', NULL, 100, NULL, NULL, '', 'text'),
('Postcode', 'Vul uw postcode in a.u.b.', NULL, 100, NULL, NULL, '', 'postcode'),
('Woonplaats', 'Vul uw woonplaats in a.u.b.', NULL, 100, NULL, NULL, '', 'text'),
('Telefoonnummer', 'Vul uw telefoonnummer in a.u.b.', NULL, 100, NULL, NULL, '', 'tel'),
('E‑mailadres', 'Vul uw e-mailadres in a.u.b.', NULL, 100, NULL, NULL, '', 'email'),
('Vrije keuze', 'Vul een eigen keuze in a.u.b.', NULL, NULL, NULL, NULL, 'Vul hier je eigen favoriet in.', 'text');

INSERT INTO `versie` (`versie`) VALUES (7);

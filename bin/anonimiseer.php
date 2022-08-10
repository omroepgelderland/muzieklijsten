<?php
/**
 * Anonimiseert persoonlijke data van stemmers.
 */

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

function anonimiseer_stemmen( int $stemmer_id ): void {
	$query = <<<EOT
		SELECT toelichting
		FROM stemmen
		WHERE
			stemmer_id = {$stemmer_id}
			AND toelichting IS NOT NULL
			AND toelichting != ''
		GROUP BY toelichting
	EOT;
	foreach ( DB::selectSingleColumn($query) as $toelichting ) {
		$escaped = DB::escape_string($toelichting);
		DB::updateMulti(
			'stemmen', [
			'toelichting' => anonimiseer($toelichting)
		],
		"toelichting = \"$escaped\"");
	}
}

function anonimiseer_extra_velden( int $stemmer_id ): void {
	$query = <<<EOT
		SELECT waarde
		FROM stemmers_extra_velden
		WHERE
			stemmer_id = {$stemmer_id}
			AND waarde IS NOT NULL
			AND waarde != ''
		GROUP BY waarde
	EOT;
	foreach ( DB::selectSingleColumn($query) as $waarde ) {
		$escaped = DB::escape_string($waarde);
		DB::updateMulti(
			'stemmers_extra_velden', [
			'waarde' => anonimiseer($waarde)
		],
		"waarde = \"$escaped\"");
	}
}

DB::disableAutocommit();

$toen = (new \DateTime())->sub(new \DateInterval('P3M'));
$query = <<<EOT
	SELECT *
	FROM stemmers
	WHERE
		is_geanonimiseerd = 0
		AND timestamp < "{$toen->format('Y-m-d H:i:s')}"
EOT;
foreach ( DB::query($query) as $entry ) {
	$id = (int)$entry['id'];
	DB::updateMulti(
		'stemmers', [
			'naam' => anonimiseer($entry['naam']),
			'adres' => anonimiseer($entry['adres']),
			'postcode' => anonimiseer($entry['postcode']),
			'woonplaats' => anonimiseer($entry['woonplaats']),
			'telefoonnummer' => anonimiseer($entry['telefoonnummer']),
			'emailadres' => anonimiseer($entry['emailadres']),
			'ip' => anonimiseer($entry['ip']),
			'is_geanonimiseerd' => true
		],
		"id = {$id}"
	);
	anonimiseer_stemmen($id);
	anonimiseer_extra_velden($id);
}

DB::commit();

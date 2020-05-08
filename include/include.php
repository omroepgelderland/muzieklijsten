<?php

require_once __DIR__.'/../vendor/autoload.php';

foreach ( scandir(__DIR__) as $bestand ) {
	$pad = __DIR__.'/'.$bestand;
	if ( is_file($pad) ) {
		require_once $pad;
	}
}

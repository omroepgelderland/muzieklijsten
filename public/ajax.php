<?php
/**
 * 
 * @author Remy Glaser <rglaser@gld.nl>
 * @package muzieklijsten
 */

namespace muzieklijsten;

require_once __DIR__.'/../vendor/autoload.php';

set_env();

try {
    DB::disableAutocommit();
    if ( $_SERVER['CONTENT_TYPE'] === 'application/json' ) {
        $request = json_decode(file_get_contents('php://input'));
    } else {
        $request = json_decode(json_encode($_POST));
    }
    $ajax = new Ajax($request);
    $functie = filter_var($request->functie);
    try {
        $respons = [
            'data' => $ajax->$functie(),
            'error' => false
        ];
    } catch ( \Error $e ) {
        if ( \str_starts_with($e->getMessage(), 'Call to private method') ) {
            throw new Muzieklijsten_Exception("Onbekende ajax-functie: \"{$functie}\"");
        } else {
            throw $e;
        }
    }
    DB::commit();
} catch ( GebruikersException $e ) {
    $respons = [
        'error' => true,
        'errordata' => $e->getMessage()
    ];
} catch ( \Throwable $e ) {
    Log::err($e);
    $respons = [
        'error' => true,
        'errordata' => is_dev()
            ? $e->getMessage()
            : 'fout'
    ];
}
header('Content-Type: application/json');
echo json_encode($respons);

<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

use DI\FactoryInterface;
use gldstdlib\exception\GLDException;
use gldstdlib\Log;
use gldstdlib\LogFactory;
use Invoker\InvokerInterface;
use Psr\Container\ContainerInterface;

use function gldstdlib\exception_error_handler_plus;
use function gldstdlib\get_tijdzone;

/**
 * Geef aan of dit de ontwikkelingsversie is.
 *
 * @return bool Of het programma in ontwikkelingsmodus draait.
 */
function is_dev(): bool
{
    return gethostname() === 'og-webdev1';
}

/**
 * Geeft de naam van de developer op wiens omgeving het project nu draait.
 *
 * @throws GLDException Als het project niet op een ontwikkelingsomgeving draait.
 */
function get_developer(): string
{
    if (!is_dev()) {
        throw new GLDException();
    }
    $res = preg_match('~^/home/([^/]+)/~i', __DIR__, $m);
    if ($res !== 1) {
        throw new GLDException();
    }
    return $m[1];
}

/**
 * Anonimiseert een waarde door er een hash van te maken.
 * Lege strings en null worden onveranderd teruggegeven.
 *
 * @param mixed $waarde Invoer
 *
 * @return ?string uitvoer
 */
function anonimiseer(mixed $waarde): ?string
{
    if ($waarde === null || $waarde === '') {
        return $waarde;
    } else {
        return hash('sha224', $waarde);
    }
}

/**
 * Checkt en format een postcode.
 *
 * @param mixed $postcode Ruwe input.
 *
 * @return ?string De geparste postcode of null als de invoer leeg is.
 */
function filter_postcode(mixed $postcode): ?string
{
    if ($postcode === false) {
        throw new OngeldigeInvoer('Ongeldige postcode');
    } elseif ($postcode === null || $postcode === '') {
        return null;
    } elseif (preg_match('~([0-9]{4}).*([a-zA-Z]{2})~', $postcode, $m) === 1) {
        return sprintf(
            '%s %s',
            $m[1],
            strtoupper($m[2])
        );
    } else {
        throw new OngeldigeInvoer("Ongeldige postcode: \"{$postcode}\"");
    }
}

/**
 * Checkt en format een telefoonnummer.
 * Leestekens worden weggefilterd.
 * Lokale nummers worden internationaal gemaakt.
 * Nederlands nummers worden gecheckt op geldige prefix en lengte.
 *
 * @param mixed $telefoonnummer Ruwe input.
 *
 * @return ?string Het geparste telefoonnummer of null als de invoer leeg is.
 */
function filter_telefoonnummer(mixed $telefoonnummer): ?string
{
    if ($telefoonnummer === false) {
        throw new OngeldigeInvoer('Ongeldig telefoonnummer');
    } elseif ($telefoonnummer === null || $telefoonnummer === '') {
        return null;
    }
    if (strlen($telefoonnummer) < 4) {
        throw new OngeldigeInvoer("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
    }
    $telefoonnummer = preg_replace('~[^+0-9]~', '', $telefoonnummer);
    if (substr($telefoonnummer, 0, 2) === '00') {
        // Handmatige uitbelcode
        $telefoonnummer = '+' . substr($telefoonnummer, 2);
    }
    if ($telefoonnummer[0] === '0') {
        // Lokaal Nederlands nummer
        $telefoonnummer = '+31' . substr($telefoonnummer, 1);
    }
    if (substr($telefoonnummer, 0, 4) === '+310') {
        // Onnodige nul na landcode
        $telefoonnummer = '+31' . substr($telefoonnummer, 4);
    }
    if (substr($telefoonnummer, 0, 3) === '+31') {
        // Nederlands nummer
        if (preg_match('~^\+31([1-5][0-9]|6[1-5]|68|7[0-9]|80|82|84|85|87|88|91)[0-9]{7}$~', $telefoonnummer) === 1) {
            return $telefoonnummer;
        } else {
            throw new OngeldigeInvoer("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
        }
    }
    if (preg_match('~^\+[0-9]{8,15}$~', $telefoonnummer) === 1) {
            // Internationaal nummer
            return $telefoonnummer;
    } else {
        throw new OngeldigeInvoer("Ongeldig telefoonnummer: \"{$telefoonnummer}\"");
    }
}

function filter_van_tot(string $waarde): ?\DateTime
{
    $str = filter_var($waarde);
    if ($str !== '') {
        $dt = new \DateTime($str);
        $dt->setTimezone(get_tijdzone());
        return $dt;
    } else {
        return null;
    }
}

function set_env(): void
{
    \set_error_handler(exception_error_handler_plus(...), error_reporting());
    \locale_set_default('nl_NL');
    \setlocale(\LC_TIME, 'nl', 'nl_NL', 'Dutch');
    \date_default_timezone_set('Europe/Amsterdam');

    if (is_dev()) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(\E_ALL);
    }
}

/**
 * Maak de dependency injection container. Voer dit niet meer dan één keer uit
 * per script.
 */
function get_di_container(): FactoryInterface & ContainerInterface & InvokerInterface
{
    $config = [
        'log.dir' => __DIR__ . '/../../data/log/',
        'log.level' => fn() => is_dev() ? \Monolog\Level::Debug : \Monolog\Level::Info,
        Log::class => \DI\autowire()->constructor(
            \DI\get('log.level'),
            \DI\get('log.dir'),
            null,
            \DI\get('log.mailinfo'),
        ),
        LogFactory::class => \DI\autowire()->constructor(
            \DI\get('log.level'),
            \DI\get('log.dir'),
            null,
            \DI\get('log.mailinfo'),
        ),
    ];
    $builder = new \DI\ContainerBuilder();
    $builder->addDefinitions($config);
    return $builder->build();
}

/**
 * Verwijdert dubbele nummers.
 *
 * Lijsten waarin de duplicaten voorkomen worden aangepast naar het juiste
 * nummer.
 *
 * Stemmen worden ook bijgewerkt naar het juiste nummer.
 *
 * @param $id int Het id van het nummer dat overblijft.
 * @param list<int> $duplicaten_ids Een lijst van id's van de nummers die
 * verwijderd moeten worden.
 */
function nummers_samenvoegen(DB $db, int $id, array $duplicaten_ids): void
{
    $i_duplicaten_ids = \implode(',', $duplicaten_ids);
    $db->query(<<<EOT
    UPDATE IGNORE `lijsten_nummers`
    SET `nummer_id` = {$id}
    WHERE `nummer_id` IN ({$i_duplicaten_ids});
    EOT);
    $db->query(<<<EOT
    UPDATE IGNORE `stemmers_nummers`
    SET `nummer_id` = {$id}
    WHERE `nummer_id` IN ({$i_duplicaten_ids});
    EOT);
    $db->query("DELETE FROM nummers WHERE id IN ({$i_duplicaten_ids})");
}

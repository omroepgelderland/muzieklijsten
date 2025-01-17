<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

declare(strict_types=1);

namespace muzieklijsten;

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
 * @throws Muzieklijsten_Exception Als het project niet op een ontwikkelingsomgeving draait.
 */
function get_developer(): string
{
    if (!is_dev()) {
        throw new Muzieklijsten_Exception();
    }
    $res = preg_match('~^/home/([^/]+)/~i', __DIR__, $m);
    if ($res !== 1) {
        throw new Muzieklijsten_Exception();
    }
    return $m[1];
}

/**
 * Geeft aan of het script handmatig (niet via cron o.i.d.) via de commandline is aangeroepen.
 */
function is_cli(): bool
{
    return php_sapi_name() === 'cli' && isset($_SERVER['TERM']);
}

/**
 * Geeft alle muzieklijsten.
 *
 * @return list<Lijst>
 */
function get_muzieklijsten(): array
{
    return DB::selectObjectLijst('SELECT id FROM lijsten ORDER BY naam', Lijst::class);
}

/**
 * Geeft alle nummers.
 *
 * @return list<Nummer>
 */
function get_nummers(): array
{
    return DB::selectObjectLijst('SELECT id FROM nummers', Nummer::class);
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
 * Overkoepelende error handler voor aanroep met exception_error_handler.
 * Maakt een echte PHP exception bij fouten.
 */
function exception_error_handler(
    int $errno,
    string $errstr,
    string $errfile,
    int $errline
): never {
    switch ($errno) {
        case \E_ERROR:
            throw new ErrorErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_WARNING:
            if (strpos($errstr, 'No such file or directory') !== false) {
                throw new PadBestaatNiet($errstr, 0, $errno, $errfile, $errline);
            } elseif (strpos($errstr, 'Undefined array key') !== false) {
                throw new IndexException($errstr, 0, $errno, $errfile, $errline);
            } elseif (strpos($errstr, 'Undefined property: ') === 0) {
                throw new UndefinedPropertyException($errstr, 0, $errno, $errfile, $errline);
            } else {
                throw new WarningErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        case \E_PARSE:
            throw new ParseErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_NOTICE:
            if (strpos($errstr, 'Undefined index: ') === 0 || strpos($errstr, 'Undefined offset: ') === 0) {
                throw new IndexException($errstr, 0, $errno, $errfile, $errline);
            } elseif (strpos($errstr, 'Trying to get property ') === 0) {
                throw new UndefinedPropertyException($errstr, 0, $errno, $errfile, $errline);
            } elseif (strpos($errstr, 'Undefined property: ') === 0) {
                throw new UndefinedPropertyException($errstr, 0, $errno, $errfile, $errline);
            } else {
                throw new NoticeErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        case \E_CORE_ERROR:
            throw new CoreErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_CORE_WARNING:
            throw new CoreWarningErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_COMPILE_ERROR:
            throw new CompileErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_COMPILE_WARNING:
            throw new CompileWarningErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_ERROR:
            throw new UserErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_WARNING:
            throw new UserWarningErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_NOTICE:
            throw new UserNoticeErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_STRICT:
            throw new StrictErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_RECOVERABLE_ERROR:
            throw new RecoverableErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_DEPRECATED:
            throw new DeprecatedErrorException($errstr, 0, $errno, $errfile, $errline);
        case \E_USER_DEPRECATED:
            throw new UserDeprecatedErrorException($errstr, 0, $errno, $errfile, $errline);
        default:
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}

function is_captcha_ok(string $g_recaptcha_response): bool
{
    $recaptcha = Config::get_recaptcha();
    $resp = $recaptcha->verify($g_recaptcha_response, $_SERVER['REMOTE_ADDR']);
    return $resp->isSuccess();
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

/**
 * Verwijder stemmers die geen stemmen meer hebben.
 */
function verwijder_stemmers_zonder_stemmen(): void
{
    $query = <<<EOT
    DELETE
    FROM stemmers
    WHERE id NOT IN (
        SELECT stemmer_id
        FROM stemmers_nummers
    )
    EOT;
    DB::query($query);
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

/**
 * Verstuur een mail
 *
 * @param list<string>|string $aan Lijst met ontvangers
 * @param list<string>|string $cc Lijst met ontvangers
 * @param $van Adres van afzender
 * @param $onderwerp Onderwerp
 * @param $tekst_bericht Het bericht in plaintext
 * @param $html_bericht Het bericht in HTML (optioneel)
 * @param $bijlage Pad naar mee te sturen bijlage (optioneel, alleen pdf)
 */
function stuur_mail(
    $aan,
    $cc,
    string $van,
    string $onderwerp,
    string $tekst_bericht,
    ?string $html_bericht = null,
    ?string $bijlage = null
): void {
    if (!is_array($aan)) {
        $aan = [$aan];
    }
    if (!is_array($cc)) {
        $cc = [$cc];
    }
    $ontvangers = array_merge($aan, $cc);
    $crlf = "\n";
    $headers = [
        'From' => $van,
        'To' => implode(',', $aan),
        'Cc' => implode(',', $cc),
        'Subject' => $onderwerp,
    ];
    $mime = new \Mail_mime($crlf);
    $mime->setTXTBody($tekst_bericht);
    if (isset($html_bericht)) {
        $mime->setHTMLBody($html_bericht);
    }

    if (isset($bijlage)) {
        $mime->addAttachment($bijlage, 'application/pdf');
    }

    $mime_params = [
        'text_encoding' => '7bit',
        'text_charset' => 'UTF-8',
        'html_charset' => 'UTF-8',
        'head_charset' => 'UTF-8',
    ];
    $body = $mime->get($mime_params);
    $headers = $mime->headers($headers);

    $params = [
        'sendmail_path' => Config::get_instelling('mail', 'sendmail_path'),
    ];
    $mail_obj = \Mail::factory('sendmail', $params);
    set_error_handler('\muzieklijsten\exception_error_handler', \E_ALL & ~\E_DEPRECATED);
    error_reporting(\E_ALL & ~\E_DEPRECATED);
    $mail_obj->send($ontvangers, $headers, $body);
    error_reporting(\E_ALL);
    set_error_handler('\muzieklijsten\exception_error_handler', \E_ALL);
}

/**
 * Plakt paden aan elkaar met slashes.
 *
 * @param $paths,... Meerdere paden die aan elkaar geplakt worden. Het
 * aantal parameters is variabel.
 *
 * @return string Het resulterende pad.
 */
function path_join(string ...$paths): string
{
    $path = '';
    foreach ($paths as $i => $arg) {
        if ($i == 0) {
            // eerste parameter
            $path = rtrim($arg, '/') . '/';
        } elseif ($i == count($paths) - 1) {
            // laatste parameter
            $path .= ltrim($arg, '/');
        } else {
            $path .= trim($arg, '/') . '/';
        }
    }
    return $path;
}

/**
 * Geeft het pad naar het eerst gestartte script, los van de class van waaruit deze functie wordt aangeroepen.
 */
function get_hoofdscript_pad(): string
{
    return get_included_files()[0];
}

/**
 * Geeft een error als het IP van de gebruiker op de blacklist staat.
 *
 * @param $ip
 *
 * @throws BlacklistException
 */
function check_ip_blacklist(string $ip): void
{
    if (DB::recordBestaat("SELECT ip FROM blacklist WHERE ip = \"{$ip}\"")) {
        throw new BlacklistException();
    }
}

/**
 * @return list<Veld>
 */
function get_velden(): array
{
    $velden = [];
    $query = 'SELECT * FROM velden ORDER BY id';
    foreach (DB::query($query) as $entry) {
        $velden[] = new Veld($entry['id'], $entry);
    }
    return $velden;
}

/**
 * Verwijdert alle vrije keuzenummers uit de tabel nummers waar geen stemmen
 * op zijn.
 */
function verwijder_ongekoppelde_vrije_keuze_nummers(): void
{
    DB::query(<<<EOT
    DELETE n
    FROM nummers n
    WHERE
        n.is_vrijekeuze = 1
        AND n.id NOT IN (
            SELECT nummer_id
            FROM stemmers_nummers
        )
    EOT);
}

/**
 * Werkt de database bij naar de nieuwste versie. Zie docs in bin/update.php.
 */
function db_update(): void
{
    DB::disableAutocommit();
    try {
        $versie = (int)DB::selectSingle('SELECT versie FROM versie') + 1;
    } catch (SQLException) {
        // De database heeft nog geen versienummer-tabel.
        $versie = 1;
    }
    while (method_exists('\muzieklijsten\DBUpdates', "update_{$versie}")) {
        call_user_func("\\muzieklijsten\\DBUpdates::update_{$versie}");
        DB::updateMulti('versie', ['versie' => $versie], "TRUE");
        DB::commit();
        $versie++;
    }
}

function set_env(): void
{
    set_error_handler(\muzieklijsten\exception_error_handler(...), error_reporting());
    locale_set_default('nl_NL');
    setlocale(\LC_TIME, 'nl', 'nl_NL', 'Dutch');
    date_default_timezone_set('Europe/Amsterdam');

    if (is_dev()) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(\E_ALL);
    }

    // Voor PHP excel reader
    define('NUM_BIG_BLOCK_DEPOT_BLOCKS_POS', 0x2c);
    define('SMALL_BLOCK_DEPOT_BLOCK_POS', 0x3c);
    define('ROOT_START_BLOCK_POS', 0x30);
    define('BIG_BLOCK_SIZE', 0x200);
    define('SMALL_BLOCK_SIZE', 0x40);
    define('EXTENSION_BLOCK_POS', 0x44);
    define('NUM_EXTENSION_BLOCK_POS', 0x48);
    define('PROPERTY_STORAGE_BLOCK_SIZE', 0x80);
    define('BIG_BLOCK_DEPOT_BLOCKS_POS', 0x4c);
    define('SMALL_BLOCK_THRESHOLD', 0x1000);
    // property storage offsets
    define('SIZE_OF_NAME_POS', 0x40);
    define('TYPE_POS', 0x42);
    define('START_BLOCK_POS', 0x74);
    define('SIZE_POS', 0x78);
    define('IDENTIFIER_OLE', pack("CCCCCCCC", 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1));

    define('SPREADSHEET_EXCEL_READER_BIFF8', 0x600);
    define('SPREADSHEET_EXCEL_READER_BIFF7', 0x500);
    define('SPREADSHEET_EXCEL_READER_WORKBOOKGLOBALS', 0x5);
    define('SPREADSHEET_EXCEL_READER_WORKSHEET', 0x10);

    define('SPREADSHEET_EXCEL_READER_TYPE_BOF', 0x809);
    define('SPREADSHEET_EXCEL_READER_TYPE_EOF', 0x0a);
    define('SPREADSHEET_EXCEL_READER_TYPE_BOUNDSHEET', 0x85);
    define('SPREADSHEET_EXCEL_READER_TYPE_DIMENSION', 0x200);
    define('SPREADSHEET_EXCEL_READER_TYPE_ROW', 0x208);
    define('SPREADSHEET_EXCEL_READER_TYPE_DBCELL', 0xd7);
    define('SPREADSHEET_EXCEL_READER_TYPE_FILEPASS', 0x2f);
    define('SPREADSHEET_EXCEL_READER_TYPE_NOTE', 0x1c);
    define('SPREADSHEET_EXCEL_READER_TYPE_TXO', 0x1b6);
    define('SPREADSHEET_EXCEL_READER_TYPE_RK', 0x7e);
    define('SPREADSHEET_EXCEL_READER_TYPE_RK2', 0x27e);
    define('SPREADSHEET_EXCEL_READER_TYPE_MULRK', 0xbd);
    define('SPREADSHEET_EXCEL_READER_TYPE_MULBLANK', 0xbe);
    define('SPREADSHEET_EXCEL_READER_TYPE_INDEX', 0x20b);
    define('SPREADSHEET_EXCEL_READER_TYPE_SST', 0xfc);
    define('SPREADSHEET_EXCEL_READER_TYPE_EXTSST', 0xff);
    define('SPREADSHEET_EXCEL_READER_TYPE_CONTINUE', 0x3c);
    define('SPREADSHEET_EXCEL_READER_TYPE_LABEL', 0x204);
    define('SPREADSHEET_EXCEL_READER_TYPE_LABELSST', 0xfd);
    define('SPREADSHEET_EXCEL_READER_TYPE_NUMBER', 0x203);
    define('SPREADSHEET_EXCEL_READER_TYPE_NAME', 0x18);
    define('SPREADSHEET_EXCEL_READER_TYPE_ARRAY', 0x221);
    define('SPREADSHEET_EXCEL_READER_TYPE_STRING', 0x207);
    define('SPREADSHEET_EXCEL_READER_TYPE_FORMULA', 0x406);
    define('SPREADSHEET_EXCEL_READER_TYPE_FORMULA2', 0x6);
    define('SPREADSHEET_EXCEL_READER_TYPE_FORMAT', 0x41e);
    define('SPREADSHEET_EXCEL_READER_TYPE_XF', 0xe0);
    define('SPREADSHEET_EXCEL_READER_TYPE_BOOLERR', 0x205);
    define('SPREADSHEET_EXCEL_READER_TYPE_UNKNOWN', 0xffff);
    define('SPREADSHEET_EXCEL_READER_TYPE_NINETEENFOUR', 0x22);
    define('SPREADSHEET_EXCEL_READER_TYPE_MERGEDCELLS', 0xE5);

    define('SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS', 25569);
    define('SPREADSHEET_EXCEL_READER_UTCOFFSETDAYS1904', 24107);
    define('SPREADSHEET_EXCEL_READER_MSINADAY', 86400);
    define('SPREADSHEET_EXCEL_READER_DEF_NUM_FORMAT', "%s");
}

/**
 * Geeft de tijdzone van de server.
 *
 * @return \DateTimeZone
 */
function get_tijdzone(): \DateTimeZone
{
    return new \DateTimeZone(date_default_timezone_get());
}

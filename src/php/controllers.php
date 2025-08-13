<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

use gldstdlib\exception\GLDException;
use gldstdlib\exception\SQLException;
use gldstdlib\Log;

use function gldstdlib\path_join;
use function gldstdlib\readline_met_default;

/**
 * Anonimiseert persoonlijke data van stemmers.
 */
function anonimiseer_stemmers(DB $db, Factory $factory): void
{
    $db->disableAutocommit();

    $toen = (new \DateTime())->sub(new \DateInterval('P3M'));
    $query = <<<EOT
        SELECT id
        FROM stemmers
        WHERE
            is_geanonimiseerd = 0
            AND timestamp < "{$toen->format('Y-m-d H:i:s')}"
    EOT;
    foreach ($factory->select_objecten(Stemmer::class, $query) as $stemmer) {
        $stemmer->anonimiseer();
    }
    $db->verwijder_ongekoppelde_vrije_keuze_nummers();
    $db->verwijder_stemmers_zonder_stemmen();

    $db->commit();
}

/**
 * Interactief script.
 * Dupliceert een lijst met alle gekoppelde nummer en velden.
 * Gebruik dit om een lijst die periodiek gebruikt wordt te resetten.
 * De bestaande lijst is de actieve; de gedupliceerde lijst is het archief.
 * De oude resultaten worden dan bewaard in een archieflijst.
 */
function dupliceer_lijst(Factory $factory, DB $db): void
{
    $db->disableAutocommit();

    $origineel_id = filter_var(readline('id van de te dupliceren lijst: '), \FILTER_VALIDATE_INT);

    $lijst = $factory->create_lijst($origineel_id);
    echo "We gaan de lijst \"{$lijst->get_naam()}\" ({$origineel_id}) dupliceren\n";

    $nieuwe_naam = filter_var(readline('nieuwe naam: '));

    $lijst->dupliceer($nieuwe_naam);

    $db->commit();
}

/**
 * Werkt de database bij naar de nieuwste versie. Zie docs in bin/update.php.
 */
function db_update(DB $db, Log $log, DBUpdates $dbupdates): void
{
    try {
        $db->disableAutocommit();
        try {
            $versie = (int)$db->selectSingle('SELECT versie FROM versie') + 1;
        } catch (SQLException) {
            // De database heeft nog geen versienummer-tabel.
            $versie = 1;
        }
        while (\method_exists('\muzieklijsten\DBUpdates', "update_{$versie}")) {
            $functie = "update_{$versie}";
            $dbupdates->$functie();
            $db->updateMulti('versie', ['versie' => $versie], "TRUE");
            $db->commit();
            $versie++;
        }
    } catch (\Throwable $e) {
        $log->crit((string)$e);
        exit(1);
    }
}

function ajax(Factory $factory, DB $db, Log $log): void
{
    try {
        $db->disableAutocommit();
        if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
            $request = json_decode(file_get_contents('php://input'));
        } else {
            $request = json_decode(json_encode($_POST));
        }
        $ajax = $factory->create_ajax($request);
        $functie = filter_var($request->functie);
        try {
            $respons = [
                'data' => $ajax->$functie(),
                'error' => false,
            ];
        } catch (\Error $e) {
            if (\str_starts_with($e->getMessage(), 'Call to private method')) {
                throw new GLDException("Onbekende ajax-functie: \"{$functie}\"");
            } else {
                throw $e;
            }
        }
        $db->commit();
    } catch (GebruikersException $e) {
        $respons = [
            'error' => true,
            'errordata' => $e->getMessage(),
        ];
    } catch (\Throwable $e) {
        $log->err((string)$e);
        $respons = [
            'error' => true,
            'errordata' => is_dev()
                ? $e->getMessage()
                : 'fout',
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($respons);
}

/**
 * @return object{
 *     jaar: string,
 *     nummers_html_str: string,
 *     nummers_meta_str: string,
 *     og_image: string,
 *     og_url: string
 * }
 */
function get_fbshare_data(Factory $factory, Config $config, Log $log, DB $db): object
{
    try {
        $stemmer = $factory->create_stemmer_uit_request((object)$_GET);

        $query = <<<EOT
        SELECT n.artiest, n.titel
        FROM nummers n
        INNER JOIN stemmers_nummers sn ON
            sn.nummer_id = n.id
            AND sn.stemmer_id = {$stemmer->get_id()}
        ORDER BY n.artiest, n.titel
        EOT;
        $res = $db->query($query);
        $nummers_meta = [];
        $nummers_html_str = '';
        $i = 1;
        foreach ($res as $r) {
            $nummers_meta[] = sprintf(
                '%d) %s - %s',
                $i,
                \htmlspecialchars($r['artiest']),
                \htmlspecialchars($r['titel'])
            );
            $nummers_html_str .= sprintf(
                '<li>%s - %s</li>',
                \htmlspecialchars($r['artiest']),
                \htmlspecialchars($r['titel'])
            );
            $i++;
        }
        $nummers_meta_str = implode("\n", $nummers_meta);

        $root_url = $config->get_instelling('root_url');
        $og_url = "{$root_url}fbshare.php?stemmer={$stemmer->get_id()}";
        $og_image = "{$root_url}afbeeldingen/fbshare_top100.jpg";
        $jaar = (new \DateTime())->format('Y');
        return (object)[
            'jaar' => $jaar,
            'nummers_html_str' => $nummers_html_str,
            'nummers_meta_str' => $nummers_meta_str,
            'og_image' => $og_image,
            'og_url' => $og_url,
        ];
    } catch (\Throwable $e) {
        $log->err((string)$e);
        throw $e;
    }
}

/**
 * Interactief installatiescript.
 */
function install(Config $config, DB $db, Log $log, DBUpdates $dbupdates): void
{
    // Configuratiebestand genereren.
    $root_url = readline_met_default('Root-URL naar deze installatie van muzieklijsten', mag_leeg: false);
    echo "Vul de gegevens van de Google Recaptcha in. Je hebt de legacy-keys nodig. "
    . "(https://cloud.google.com/recaptcha-enterprise/docs/create-key#find-key)\n";
    $recaptcha_sitekey = readline_met_default('Recaptcha site key', mag_leeg: false);
    $recaptcha_secret = readline_met_default('Recaptcha secret', mag_leeg: false);
    $root_url = rtrim($root_url, '/') . '/';
    $config_json = [
        'organisatie' => readline_met_default('Naam organisatie/bedrijf'),
        'root_url' => $root_url,
        'privacy_url' => readline_met_default('URL naar het privacybeleid'),
        'nimbus_url' => readline_met_default('URL naar Nimbus'),
        'sql' => [
            'server' => $sql_server = readline_met_default('SQL-server', 'localhost'),
            'database' => $sql_database = readline_met_default('Naam van de database', 'muzieklijsten'),
            'user' => $sql_user = readline_met_default('SQL gebruikersnaam', 'muzieklijsten'),
            'password' => $sql_password = readline_met_default(
                'Wachtwoord van de SQL-gebruiker (alleen als de gebruiker nog niet bestaat)',
                mag_leeg: false
            ),
        ],
        'php_auth' => [
            'user' => readline_met_default('Gebruikersnaam voor de beheerdersinterface', mag_leeg: false),
            'password' => readline_met_default('Wachtwoord voor de beheerdersinterface', mag_leeg: false),
        ],
        'mail' => [
            'sendmail_path' => '/usr/sbin/sendmail',
            'afzender' => readline_met_default('Afzender voor e-mails naar de redactie', mag_leeg: false),
        ],
        'recaptcha' => [
            'sitekey' => $recaptcha_sitekey,
            'secret' => $recaptcha_secret,
        ],
    ];

    $configdir = path_join(__DIR__, '..', 'config');
    if (!is_dir($configdir)) {
        mkdir($configdir, 0770, true);
    }
    $configdir = \realpath($configdir);
    $config_pad = path_join($configdir, 'config.json');
    file_put_contents(
        $config_pad,
        json_encode($config_json, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)
    );
    echo "De configuratie is opgeslagen in {$config_pad}. Je kunt dit bestand zo nodig aanpassen.\n";

    $public_pad = realpath(path_join(__DIR__, '..', 'public'));
    $root_host = parse_url($root_url, \PHP_URL_HOST);
    $root_url_pad = parse_url($root_url, \PHP_URL_PATH);
    $root_url_pad = rtrim($root_url_pad, '/');
    $https_root_url = rtrim("https://{$root_host}/{$root_url_pad}/", '/') . '/';
    if ($root_url_pad === '') {
        $alias_regel = '';
    } else {
        $alias_regel = "Alias {$root_url_pad} {$public_pad}\n\n";
    }
    echo "Maak een regel in je webserver zodat {$root_url} verwijst naar {$public_pad}\n";

    $apache_template = <<<EOT
    {$alias_regel}<IfModule mod_ssl.c>
        <VirtualHost *:80>
            ServerName {$root_host}
            Redirect / {$https_root_url}
        </VirtualHost>
    
        <VirtualHost *:443>
            ServerName {$root_host}
            DocumentRoot {$public_pad}
            SSLEngine on
            SSLCertificateFile /.../cert.pem
            SSLCertificateKeyFile /.../privkey.pem
            SSLCertificateChainFile /.../chain.pem
        </VirtualHost>
    </IfModule>
    <IfModule !mod_ssl.c>
        <VirtualHost *:80>
            ServerName {$root_host}
            DocumentRoot {$public_pad}
        </VirtualHost>
    </IfModule>
    
    <Directory {$public_pad}>
        AllowOverride None
        Require all granted
    </Directory>
    EOT;
    echo "Voorbeeld apache configuratie:\n{$apache_template}\n";

    // Database initialiseren.
    $sql_server = $config->get_instelling('sql', 'server');
    $sql_database = $config->get_instelling('sql', 'database');
    $sql_user = $config->get_instelling('sql', 'user');
    $sql_password = $config->get_instelling('sql', 'password');
    $db_root_user = readline_met_default('MySQL user met rechten voor maken van databases en gebruikers', 'root');
    $db_root_password = readline_met_default('Wachtwoord (leeglaten bij authenticatie met auth_socket)');
    $root_password_param = $db_root_password === '' ? '' : "-p '{$db_root_password}'";
    $root_queries = <<<EOT
    CREATE DATABASE IF NOT EXISTS `{$sql_database}`;
    CREATE USER IF NOT EXISTS "{$sql_user}"@"{$sql_server}" IDENTIFIED BY "{$sql_password}";
    GRANT ALL PRIVILEGES ON `{$sql_database}`.* TO "{$sql_user}"@"{$sql_server}";
    EOT;
    exec("sudo mysql -u {$db_root_user} {$root_password_param} -e '{$root_queries}'", $output, $result_code);
    if ($result_code !== 0) {
        echo implode('\n', $output) . "\n";
        echo <<<EOT
        Kan niet inloggen als root op MySQL. Voer de volgende queries zelf uit op je MySQL-server en druk daarna op enter.
        {$root_queries}
        EOT;
        readline();
    }

    $db->getDB()->multi_query(file_get_contents(path_join(__DIR__, 'install.sql')));
    do {
    } while ($db->getDB()->next_result());

    db_update($db, $log, $dbupdates);

    echo <<<EOT
    Installatie voltooid. Nadat je je webserver hebt ingesteld zijn de volgende interfaces bereikbaar:
    
    Stemmodule voor publiek:
    {$root_url}?lijst=[lijst id]
    {$root_url}index.php?lijst=[lijst id]
    
    Beheer van lijsten, plaatsen van nummers op een lijst, bekijken en beheren van resultaten:
    {$root_url}admin.html
    
    Losse nummers toevoegen aan de database, buiten Powergold om:
    {$root_url}los_toevoegen.html
    
    EOT;
}

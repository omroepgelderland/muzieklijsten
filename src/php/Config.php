<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

use gldstdlib\exception\GLDException;

use function gldstdlib\exception_error_handler_plus;

/**
 * Hiermee kunnen instellingen worden opgehaald uit de serverconfiguratie.
 *
 * @phpstan-type ConfigData array{
 *     organisatie: string,
 *     root_url: string,
 *     privacy_url: string,
 *     nimbus_url: string,
 *     sql: array{
 *         server: string,
 *         database: string,
 *         user: string,
 *         password: string
 *     },
 *     recaptcha: array{
 *         sitekey: string,
 *         secret: string
 *     },
 *     php_auth: array{
 *         user: string,
 *         password: string
 *     },
 *     mail: array{
 *         sendmail_path: string,
 *         afzender: string
 *     }
 * }
 */
class Config
{
    /** @var ?ConfigData */
    private ?array $data;

    /**
     * Maakt een nieuw object. Mag alleen vanuit deze class worden gedaan
     */
    public function __construct()
    {
    }

    /**
     * Haalt de JSON inhoud op.
     *
     * @return ConfigData Inhoud van het configuratiebestand
     *
     * @throws GLDException Als het configuratiebestand niet kan worden geladen.
     */
    public function get_data(): array
    {
        if (!isset($this->data)) {
            try {
                $pad = __DIR__ . '/../../config/config.json';
                $this->data = json_decode(file_get_contents($pad), true);
            } catch (\Throwable $e) {
                throw new GLDException('Kan config.json niet laden.', 0, $e);
            }
        }
        return $this->data;
    }

    /**
     * Haalt een instelling op die in een sectie staat.
     * Instellingen staan onder een variabel aantal niveaus, die met de functieparameters worden aangegeven.
     *
     * @param $args,... Secties waaronder de instelling staat.
     *
     * @return mixed De waarde van de instelling
     *
     * @throws ConfigException Als de instelling niet kan worden gevonden.
     */
    public function get_instelling(string ...$args)
    {
        $sectie = $this->get_data();
        foreach (func_get_args() as $param) {
            if (!is_array($sectie) || !array_key_exists($param, $sectie)) {
                throw new ConfigException(sprintf(
                    'De instelling %s kan niet worden gevonden.',
                    implode('->', func_get_args())
                ));
            }
            $sectie = $sectie[$param];
        }
        return $sectie;
    }

    /**
     * Geeft het Google Recaptcha object
     *
     * @return \ReCaptcha\ReCaptcha
     */
    public function get_recaptcha(): \ReCaptcha\ReCaptcha
    {
        return new \ReCaptcha\ReCaptcha($this->get_instelling('recaptcha', 'secret'));
    }

    public function is_captcha_ok(string $g_recaptcha_response): bool
    {
        $recaptcha = $this->get_recaptcha();
        $resp = $recaptcha->verify($g_recaptcha_response, $_SERVER['REMOTE_ADDR']);
        return $resp->isSuccess();
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
    public function stuur_mail(
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
            'sendmail_path' => $this->get_instelling('mail', 'sendmail_path'),
        ];
        $mail_obj = \Mail::factory('sendmail', $params);
        set_error_handler(exception_error_handler_plus(...), \E_ALL & ~\E_DEPRECATED);
        error_reporting(\E_ALL & ~\E_DEPRECATED);
        $mail_obj->send($ontvangers, $headers, $body);
        error_reporting(\E_ALL);
        set_error_handler(exception_error_handler_plus(...), \E_ALL);
    }
}

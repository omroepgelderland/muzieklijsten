<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

use gldstdlib\exception\GLDException;

/**
 * Hiermee kunnen instellingen worden opgehaald uit de serverconfiguratie.
 *
 * @phpstan-import-type DBConfigType from DB as DBConfigType
 * @phpstan-type        ConfigData array{
 *     organisatie: string,
 *     root_url: string,
 *     privacy_url: string,
 *     nimbus_url: string,
 *     database: DBConfigType,
 *     recaptcha?: array{
 *         sitekey: string,
 *         secret: string,
 *     },
 *     php_auth: array{
 *         user: string,
 *         password: string,
 *     },
 *     mail: array{
 *         afzender: string,
 *     },
 *     openai?: array{
 *         api_key: string,
 *     },
 * }
 */
class Config
{
    /** @var ?ConfigData */
    private ?array $data;

    /**
     * Haalt de JSON inhoud op.
     *
     * @return ConfigData Inhoud van het configuratiebestand
     *
     * @throws GLDException Als het configuratiebestand niet kan worden geladen.
     */
    public function get(): array
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
     * Geeft aan of er recaptcha keys zijn ingesteld in de configuratie.
     */
    public function heeft_recaptcha_keys(): bool
    {
        return isset($this->get()['recaptcha']);
    }

    /**
     * Geeft het Google Recaptcha object
     *
     * @throws ConfigException Als recaptcha niet is geconfigureerd.
     */
    public function get_recaptcha(): \ReCaptcha\ReCaptcha
    {
        if (!isset($this->get()['recaptcha'])) {
            throw new ConfigException('Recaptcha is niet geconfigureerd.');
        }
        return new \ReCaptcha\ReCaptcha($this->get()['recaptcha']['secret']);
    }

    /**
     * Controleert of de captcha geldig is.
     *
     * @param $g_recaptcha_response De g-recaptcha-response uit het formulier
     *
     * @throws ConfigException Als recaptcha niet is geconfigureerd.
     */
    public function is_captcha_ok(string $g_recaptcha_response): bool
    {
        $recaptcha = $this->get_recaptcha();
        $resp = $recaptcha->verify($g_recaptcha_response, $_SERVER['REMOTE_ADDR']);
        return $resp->isSuccess();
    }

    public function get_openai_api_key(): ?string
    {
        return isset($this->get()['openai']) ? $this->get()['openai']['api_key'] : null;
    }

    /**
     * Geeft de logingegevens voor de database.
     *
     * @return DBConfigType
     */
    public function get_db_config(): array
    {
        return $this->get()['database'];
    }
}

<?php

declare(strict_types=1);

namespace gldstdlib;

use gldstdlib\exception\YourlsException;
use gldstdlib\exception\YourlsTooManyRequestsException;

/**
 * Communicatie met de short URL generator.
 */
class Yourls
{
    private \GuzzleHttp\Client $client;

    /**
     * @param $url URL naar de Yourls API.
     * @param $log Logobject voor meldingen (optioneel)
     */
    public function __construct(
        string $url,
        private readonly ?Log $log = null,
    ) {
        $this->client = new \GuzzleHttp\Client([
            'base_uri' => $url,
        ]);
    }

    /**
     * Haalt de short URL uit de API
     *
     * @param array<string, mixed> $params Associatieve array met queryparameters
     * @param $pogingen Aantal pogingen bij HTTP errors. Alleen voor recursie.
     *
     * @return string Short url
     *
     * @throws YourlsException
     * @throws YourlsTooManyRequestsException
     */
    private function get_query(
        array $params,
        int $pogingen = 3
    ): string {
        try {
            $res = $this->client->post('', [
                'form_params' => $params,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            if ($e->getCode() === 400) {
                // Bij short-urls die al bestaan geeft de api een error 400 met
                // de short-url in het respons.
                $res = $e->getResponse();
            } elseif ($e->getCode() === 429 && $pogingen > 0) {
                $this->log?->notice("Yourls too many requests");
                \sleep(10);
                return $this->get_query($params, $pogingen - 1);
            } elseif ($e->getCode() === 429) {
                throw new YourlsTooManyRequestsException(
                    $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            } else {
                throw new YourlsException(
                    $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            }
        }
        $response = $res->getBody()->getContents();
        $http_code = $res->getStatusCode();
        /**
         * @var object{
         *     status: 'success'|'fail',
         *     code: ''|'error:url',
         *     message: string,
         *     errorCode: ''|numeric-string,
         *     statusCode: int|numeric-string,
         *     url: object{
         *         keyword: string,
         *         url: string,
         *         title: string,
         *         date: string,
         *         ip: string,
         *         clicks?: int
         *     },
         *     title: string,
         *     shorturl: string
         * }|mixed $respons_data
         */
        $respons_data = json_decode($response);
        if (!\is_object($respons_data)) {
            $respons_data = new \stdClass();
        }
        if (\property_exists($respons_data, 'shorturl')) {
            return $respons_data->shorturl;
        }

        // Errors
        if ($http_code === 400 && \property_exists($respons_data, 'message')) {
            throw new YourlsException($respons_data->message);
        }
        if ($pogingen > 0) {
            \sleep(1);
            return $this->get_query($params, $pogingen - 1);
        } elseif (isset($e)) {
            throw $e;
        } else {
            throw new YourlsException("{$http_code} {$response}", $http_code);
        }
    }

    /**
     * Genereert een short-URL of haalt de bestaande short-URL op.
     *
     * @param $url De URL zonder querystring.
     * @param array<string, mixed> $url_params Optionale querystring-parameters.
     *
     * @return string De verkorte URL
     *
     * @throws YourlsException
     * @throws YourlsTooManyRequestsException
     */
    public function get_shorturl(string $url, array $url_params = []): string
    {
        $url_met_params = maak_url_met_querystring($url, $url_params);
        $params = [
            'action' => 'shorturl',
            'format' => 'json',
            'url' => $url_met_params,
        ];
        $shorturl = $this->get_query($params);
        return \str_replace('http://', 'https://', $shorturl);
    }
}

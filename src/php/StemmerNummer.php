<?php

declare(strict_types=1);

namespace muzieklijsten;

/**
 * @phpstan-type StemmerNummerData array{
 *     toelichting: string|null,
 *     behandeld: bool,
 *     is_vrijekeuze: bool,
 * }
 */
class StemmerNummer
{
    /** @var ?StemmerNummerData */
    private ?array $data = null;

    public function __construct(
        private DB $db,
        public readonly Nummer $nummer,
        public readonly Stemmer $stemmer,
    ) {
    }

    public function get_toelichting(): ?string
    {
        return $this->get_data()['toelichting'];
    }

    public function is_behandeld(): bool
    {
        return $this->get_data()['behandeld'];
    }

    public function is_vrijekeuze(): bool
    {
        return $this->get_data()['is_vrijekeuze'];
    }

    /**
     * @return StemmerNummerData
     */
    private function get_data(): array
    {
        return $this->data ?? $this->set_db_data(
            $this->db->selectSingleRow(<<<SQL
            SELECT *
            FROM stemmers_nummers
            WHERE {$this->get_where_voorwaarden()}
            SQL),
        );
    }

    /**
     * @param StemmerNummerData $data De nieuwe data voor de stem.
     */
    public function set_data(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param array<string, string|null> $raw_db_data
     *
     * @return StemmerNummerData
     */
    public function set_db_data(array $raw_db_data): array
    {
        return $this->data = [
            'toelichting' => $raw_db_data['toelichting'],
            'behandeld' => (bool)$raw_db_data['behandeld'],
            'is_vrijekeuze' => (bool)$raw_db_data['is_vrijekeuze'],
        ];
    }

    private function get_where_voorwaarden(): string
    {
        return <<<SQL
            nummer_id = {$this->nummer->id}
            AND stemmer_id = {$this->stemmer->id}
        SQL;
    }

    /**
     * Stelt in of de stem al dan niet behandeld is.
     *
     * @param $waarde aan of uit.
     */
    public function set_behandeld(bool $waarde): void
    {
        $this->db->updateMulti('stemmers_nummers', [
            'behandeld' => $waarde,
        ], $this->get_where_voorwaarden());
    }

    /**
     * Verwijdert de stem.
     */
    public function verwijderen(): void
    {
        $this->db->execute("DELETE FROM stemmers_nummers WHERE {$this->get_where_voorwaarden()}");
        $this->db->verwijder_stemmers_zonder_stemmen();
        $this->data = null;
    }
}

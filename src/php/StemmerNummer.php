<?php

/**
 * @author Remy Glaser <rglaser@gld.nl>
 */

namespace muzieklijsten;

/**
 * @phpstan-type DBData array{
 *     nummer_id: positive-int,
 *     stemmer_id: positive-int,
 *     toelichting: ?string,
 *     behandeld: positive-int,
 *     is_vrijekeuze: positive-int
 * }
 */
class StemmerNummer
{
    public Nummer $nummer;
    public Stemmer $stemmer;
    private ?string $toelichting;
    private bool $behandeld;
    private bool $is_vrijekeuze;
    private bool $db_props_set;

    /**
     * @param $nummer
     * @param $stemmer
     * @param ?DBData $data Metadata uit de databasevelden (optioneel).
     */
    public function __construct(
        private DB $db,
        Nummer $nummer,
        Stemmer $stemmer,
        ?array $data = null
    ) {
        $this->nummer = $nummer;
        $this->stemmer = $stemmer;
        $this->db_props_set = false;
        if (isset($data)) {
            $this->set_data($data);
        }
    }

    /**
     * Geeft aan of twee stemmen dezelfde zijn. Wanneer $obj geen Stem is wordt
     * false gegeven.
     *
     * @param $obj Object om deze instantie mee te vergelijken
     *
     * @return bool Of $obj dezelfde stem is als deze instantie
     */
    public function equals(mixed $obj): bool
    {
        return
            $obj instanceof StemmerNummer
            && $this->nummer->equals($obj->nummer)
            && $this->stemmer->equals($obj->stemmer);
    }

    public function get_toelichting(): ?string
    {
        $this->set_db_properties();
        return $this->toelichting;
    }

    public function is_behandeld(): bool
    {
        $this->set_db_properties();
        return $this->behandeld;
    }

    public function is_vrijekeuze(): bool
    {
        $this->set_db_properties();
        return $this->is_vrijekeuze;
    }

    /**
     * Vul het object met velden uit de database.
     */
    private function set_db_properties(): void
    {
        if (!$this->db_props_set) {
            $query = <<<EOT
                SELECT *
                FROM stemmers_nummers
                WHERE {$this->get_where_voorwaarden()}
            EOT;
            $this->set_data($this->db->selectSingleRow($query));
        }
    }

    /**
     * Plaatst metadata in het object
     *
     * @param DBData $data Data.
     */
    private function set_data(array $data): void
    {
        $this->toelichting = $data['toelichting'];
        $this->behandeld = (bool)$data['behandeld'];
        $this->is_vrijekeuze = (bool)$data['is_vrijekeuze'];
        $this->db_props_set = true;
    }

    private function get_where_voorwaarden(): string
    {
        return <<<EOT
            nummer_id = {$this->nummer->get_id()}
            AND stemmer_id = {$this->stemmer->get_id()}
        EOT;
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
        $this->db->query("DELETE FROM stemmers_nummers WHERE {$this->get_where_voorwaarden()}");
        $this->db->verwijder_stemmers_zonder_stemmen();
        $this->db_props_set = false;
    }
}

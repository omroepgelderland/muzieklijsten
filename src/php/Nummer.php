<?php

declare(strict_types=1);

namespace muzieklijsten;

use function gldstdlib\ns;

/**
 * @phpstan-type NummerData array{
 *     muziek_id: string|null,
 *     titel: string,
 *     artiest: string,
 *     jaar: int|null,
 *     categorie: string|null,
 *     map: string|null,
 *     opener: bool,
 *     duur: int|null,
 *     is_vrijekeuze: int,
 *     vgl_titel: string,
 *     vgl_artiest: string,
 * }
 * @phpstan-type AISuggestieType array{
 *     is_correct: bool,
 *     suggestie?: array{
 *         artiest: string,
 *         titel: string,
 *     },
 * }
 * @phpstan-type ModVrijeKeuzeData array{
 *     id: int,
 *     artiest: string,
 *     titel: string,
 *     ai_suggestie: AISuggestieType,
 * }
 */
class Nummer
{
    /** @var ?NummerData */
    private ?array $data = null;
    /** @var array<Lijst> */
    private array $lijsten;
    /** @var array<Stemmer> */
    private array $stemmers;

    /**
     * @param $id ID van het nummer.
     */
    public function __construct(
        private DB $db,
        private Factory $factory,
        public readonly int $id,
    ) {
    }

    /**
     * Geeft het Powergold ID
     */
    public function get_muziek_id(): ?string
    {
        return $this->get_data()['muziek_id'];
    }

    public function get_titel(): string
    {
        return $this->get_data()['titel'];
    }

    public function get_artiest(): string
    {
        return $this->get_data()['artiest'];
    }

    public function get_jaar(): ?int
    {
        return $this->get_data()['jaar'];
    }

    public function get_categorie(): ?string
    {
        return $this->get_data()['categorie'];
    }

    public function get_map(): ?string
    {
        return $this->get_data()['map'];
    }

    public function is_opener(): bool
    {
        return $this->get_data()['opener'];
    }

    public function get_duur(): ?int
    {
        return $this->get_data()['duur'];
    }

    public function is_vrijekeuze(): int
    {
        return $this->get_data()['is_vrijekeuze'];
    }

    public function get_vgl_artiest(): string
    {
        return $this->get_data()['vgl_artiest'];
    }

    public function get_vgl_titel(): string
    {
        return $this->get_data()['vgl_titel'];
    }

    /**
     * Geeft alle lijsten waar dit nummer op staat.
     *
     * @return array<Lijst>
     */
    public function get_lijsten(): array
    {
        if (!isset($this->lijsten)) {
            $query = <<<SQL
            SELECT lijst_id AS id
            FROM lijsten_nummers
            WHERE nummer_id = {$this->id}
            SQL;
            $this->lijsten = iterator_to_array($this->factory->select_objecten(Lijst::class, $query));
        }
        return $this->lijsten;
    }

    /**
     * Geeft alle stemmers op dit nummer (op verschillende lijsten)
     *
     * @return array<Stemmer>
     */
    public function get_stemmers(): array
    {
        if (!isset($this->stemmers)) {
            $query = <<<SQL
            SELECT stemmer_id AS id
            FROM stemmers_nummers
            WHERE nummer_id = {$this->id}
            SQL;
            $this->stemmers = iterator_to_array($this->factory->select_objecten(Stemmer::class, $query));
        }
        return $this->stemmers;
    }

    /**
     * @return NummerData
     */
    private function get_data(): array
    {
        return $this->data ?? $this->set_db_data(
            $this->db->selectSingleRow(<<<SQL
            SELECT *
            FROM nummers
            WHERE id = {$this->id}
            SQL),
        );
    }

    /**
     * @param NummerData $data De nieuwe data voor het nummer.
     */
    public function set_data(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param array<string, string|null> $raw_db_data
     *
     * @return NummerData
     */
    public function set_db_data(array $raw_db_data): array
    {
        return $this->data = [
            'muziek_id' => $raw_db_data['muziek_id'],
            'titel' => ns($raw_db_data['titel']),
            'artiest' => ns($raw_db_data['artiest']),
            'jaar' => $raw_db_data['jaar'] === null ? null : (int)$raw_db_data['jaar'],
            'categorie' => $raw_db_data['categorie'],
            'map' => $raw_db_data['map'],
            'opener' => (bool)$raw_db_data['opener'],
            'duur' => $raw_db_data['duur'] === null ? null : (int)$raw_db_data['duur'],
            'is_vrijekeuze' => (int)$raw_db_data['is_vrijekeuze'],
            'vgl_titel' => ns($raw_db_data['vgl_titel']),
            'vgl_artiest' => ns($raw_db_data['vgl_artiest']),
        ];
    }

    /**
     * Nadat een nummer met andere nummers is samengevoegd kan het gebeuren dat
     * er stemmen ongeldig zijn geworden volgens de restricties van de
     * stemlijst.
     *
     * Deze functie checkt en werkt alle stemmen bij van stemmers op dit nummer.
     */
    public function verwijder_ongeldige_stemmen(): void
    {
        foreach ($this->get_stemmers() as $stemmer) {
            $stemmer->verwijder_ongeldige_stemmen();
        }
    }
}

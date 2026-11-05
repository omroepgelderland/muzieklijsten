<?php

declare(strict_types=1);

namespace muzieklijsten;

use gldstdlib\exception\GLDException;

use function gldstdlib\ns;

/**
 * @phpstan-type VeldData array{
 *     label: string,
 *     leeg_feedback: string,
 *     max: int|null,
 *     maxlength: int|null,
 *     min: int|null,
 *     minlength: int|null,
 *     placeholder: string|null,
 *     type: string,
 * }
 */
class Veld
{
    /** @var ?VeldData */
    private ?array $data = null;
    /** @var array<Lijst> */
    private array $lijsten;

    /**
     * @param $id Database-id van het veld.
     * @param $verplicht Of het veld verplicht is (optioneel)
     */
    public function __construct(
        private Factory $factory,
        private DB $db,
        public readonly int $id,
        private ?bool $verplicht = null,
    ) {
    }

    public function get_label(): string
    {
        return $this->get_data()['label'];
    }

    /**
     * @throws ObjectEigenschapOntbreekt
     */
    public function get_placeholder(): string
    {
        $placeholder = $this->get_data()['placeholder'];
        if ($placeholder === null) {
            throw new ObjectEigenschapOntbreekt();
        }
        return $placeholder;
    }

    public function get_type(): string
    {
        return $this->get_data()['type'];
    }

    public function get_leeg_feedback(): string
    {
        return $this->get_data()['leeg_feedback'];
    }

    /**
     * Geeft aan of het invullen van dit veld verplicht is.
     * Dat is geen vaste eigenschap van het veld, maar van de combinatie veld en
     * lijst. Of het verplicht is moet bij de constructor worden meegegeven.
     *
     * @throws GLDException Als er bij de constructor niet is
     * aangegeven of het veld verplicht is.
     */
    public function is_verplicht(): bool
    {
        if (!isset($this->verplicht)) {
            throw new GLDException('Niet bekend of dit veld verplicht is.');
        }
        return $this->verplicht;
    }

    /**
     * Geeft alle lijsten waar dit een veld is.
     *
     * @return array<Lijst>
     */
    public function get_lijsten(): array
    {
        if (!isset($this->lijsten)) {
            $this->lijsten = [];
            $sql = <<<SQL
            SELECT lijst_id AS id
            FROM lijsten_velden
            WHERE veld_id = {$this->id}
            SQL;
            $this->lijsten = iterator_to_array($this->factory->select_objecten(Lijst::class, $sql));
        }
        return $this->lijsten;
    }

    /**
     * Geeft het id van het invoerveld in het HTML-formulier
     */
    public function get_html_id(): string
    {
        return \sprintf('veld-%d', $this->id);
    }

    /**
     * Geeft het antwoord dat een stemmer hier heeft ingevuld.
     *
     * @param $stemmer
     *
     * @return mixed Waarde
     *
     * @throws GLDException als er geen antwoord is.
     */
    public function get_stemmer_waarde(Stemmer $stemmer): mixed
    {
        try {
            $waarde = $this->db->selectSingle(\sprintf(
                'SELECT waarde FROM stemmers_velden WHERE stemmer_id = %d AND veld_id = %d',
                $stemmer->id,
                $this->id,
            ));
            if ($waarde === null) {
                throw new GLDException();
            }
        } catch (GLDException $e) {
            throw new GLDException(\sprintf(
                'Geen waarde ingevuld door stemmer %d in veld %d',
                $stemmer->id,
                $this->id,
            ), $e->getCode(), $e);
        }
        return $waarde;
    }

    /**
     * @return VeldData
     */
    private function get_data(): array
    {
        return $this->data ?? $this->set_db_data(
            $this->db->selectSingleRow(<<<SQL
            SELECT *
            FROM velden
            WHERE id = {$this->id}
            SQL),
        );
    }

    /**
     * @param VeldData $data De nieuwe data voor het veld.
     */
    public function set_data(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @param array<string, string|null> $raw_db_data
     *
     * @return VeldData
     */
    public function set_db_data(array $raw_db_data): array
    {
        return $this->data = [
            'label' => ns($raw_db_data['label']),
            'leeg_feedback' => ns($raw_db_data['leeg_feedback']),
            'max' => isset($raw_db_data['max']) ? (int)$raw_db_data['max'] : null,
            'maxlength' => isset($raw_db_data['maxlength']) ? (int)$raw_db_data['maxlength'] : null,
            'min' => isset($raw_db_data['min']) ? (int)$raw_db_data['min'] : null,
            'minlength' => isset($raw_db_data['minlength']) ? (int)$raw_db_data['minlength'] : null,
            'placeholder' => $raw_db_data['placeholder'],
            'type' => ns($raw_db_data['type']),
        ];
    }

    /**
     * Filtert de invoer van een veld en voegt het toe aan de database.
     * Als de combinatie stemmer–veld al bestaat dan wordt de waarde
     * overschreven.
     *
     * @throws GebruikersException Als de invoer leeg is maar niet leeg mag zijn.
     * @throws GLDException Als er bij de constructor niet is
     * aangegeven of het veld verplicht is.
     */
    public function set_waarde(Stemmer $stemmer, string $waarde): void
    {
        if ($this->get_type() === 'checkbox') {
            $gefilterde_waarde = filter_var($waarde, \FILTER_VALIDATE_BOOL);
        } elseif ($this->get_type() === 'date') {
            $gefilterde_waarde = (new \DateTime($waarde))->format('Y-m-d');
        } elseif ($this->get_type() === 'email') {
            $gefilterde_waarde = filter_var($waarde, \FILTER_SANITIZE_EMAIL);
            if ($gefilterde_waarde !== false) {
                $gefilterde_waarde = strtolower($gefilterde_waarde);
            }
        } elseif ($this->get_type() === 'month' || $this->get_type() === 'number' || $this->get_type() === 'week') {
            $gefilterde_waarde = filter_var($waarde, \FILTER_VALIDATE_INT);
        } elseif ($this->get_type() === 'postcode') {
            $gefilterde_waarde = filter_postcode($waarde);
        } elseif ($this->get_type() === 'tel') {
            $gefilterde_waarde = filter_telefoonnummer($waarde);
        } else {
            $gefilterde_waarde = filter_var($waarde);
        }

        if ($gefilterde_waarde !== false && $gefilterde_waarde !== null) {
            $this->db->insert_update_multi('stemmers_velden', [
                'stemmer_id' => $stemmer->id,
                'veld_id' => $this->id,
                'waarde' => $gefilterde_waarde,
            ]);
        } elseif ($this->is_verplicht()) {
            throw new GebruikersException(
                "Veld {$this->get_label()} mag niet leeg zijn",
            );
        }
    }

    /**
     * @throws ObjectEigenschapOntbreekt
     */
    public function get_max(): int
    {
        $max = $this->get_data()['max'];
        if ($max === null) {
            throw new ObjectEigenschapOntbreekt();
        }
        return $max;
    }

    /**
     * @throws ObjectEigenschapOntbreekt
     */
    public function get_maxlength(): int
    {
        $maxlength = $this->get_data()['maxlength'];
        if ($maxlength === null) {
            throw new ObjectEigenschapOntbreekt();
        }
        return $maxlength;
    }

    /**
     * @throws ObjectEigenschapOntbreekt
     */
    public function get_min(): int
    {
        $min = $this->get_data()['min'];
        if ($min === null) {
            throw new ObjectEigenschapOntbreekt();
        }
        return $min;
    }

    /**
     * @throws ObjectEigenschapOntbreekt
     */
    public function get_minlength(): int
    {
        $minlength = $this->get_data()['minlength'];
        if ($minlength === null) {
            throw new ObjectEigenschapOntbreekt();
        }
        return $minlength;
    }
}

<?php

namespace muzieklijsten;

use gldstdlib\exception\GLDException;
use gldstdlib\exception\IndexException;
use gldstdlib\exception\SQLException;

use function gldstdlib\readline_met_default;

/**
 * @phpstan-type KolomKeysType 'muziek_id'|'titel'|'artiest'|'jaar'|'categorie'|'map'|'opener'|'duur'
 * @phpstan-type KeysType array<KolomKeysType, int>
 */
class PowergoldImporter
{
    private readonly PhpExcelReader $excel;
    /** @var KeysType */
    private ?array $kolomtitels;

    public function __construct(
        private DB $db,
        string $filename,
    ) {
        $this->excel = new PhpExcelReader();
        $this->excel->read($filename);
        $this->kolomtitels = null;
    }

    public static function controller(
        Factory $factory,
        string $filename
    ): void {
        $factory->create_powergold_importer($filename)->import();
    }

    /**
     * Converteert een waarde uit Excel
     *
     * @param $waarde
     * @param $null_als_leeg Geef null terug als de string leeg is.
     */
    private static function filter_cell_string(string $waarde, bool $null_als_leeg): ?string
    {
        $res = trim(mb_convert_encoding($waarde, 'utf-8', 'windows-1252'));
        if ($null_als_leeg && $waarde === '') {
            $res = null;
        }
        return $res;
    }

    private static function filter_cell_int(string $waarde): int
    {
        $string = self::filter_cell_string($waarde, false);
        $res = filter_var($string, \FILTER_VALIDATE_INT);
        if ($res === false) {
            throw new GLDException("Ongeldige integer: {$string}");
        }
        return $res;
    }

    /**
     * Geeft per kolom key in welke kolom de data staat.
     *
     * @return KeysType Key is de naam van de kolom, waarde is de index van de kolom (A=1, B=2 etc.).
     */
    private function get_kolomtitels(): array
    {
        if ($this->kolomtitels === null) {
            $this->kolomtitels = [];
            /** @var list<string> $rij */
            $rij = $this->excel->sheets[0]['cells'][7];
            foreach ($rij as $i => $cel) {
                $waarde = self::filter_cell_string($cel, true);
                if ($waarde === 'Miscellaneous') {
                    $this->kolomtitels['muziek_id'] = $i;
                } elseif ($waarde === 'Title') {
                    $this->kolomtitels['titel'] = $i;
                } elseif ($waarde === 'Artist(s)') {
                    $this->kolomtitels['artiest'] = $i;
                } elseif ($waarde === 'Year') {
                    $this->kolomtitels['jaar'] = $i;
                } elseif ($waarde === 'Category') {
                    $this->kolomtitels['categorie'] = $i;
                } elseif ($waarde === 'Folder') {
                    $this->kolomtitels['map'] = $i;
                } elseif ($waarde === 'Opener') {
                    $this->kolomtitels['opener'] = $i;
                } elseif ($waarde === 'Run Time') {
                    $this->kolomtitels['duur'] = $i;
                }
            }
            $verplichte_kolommen = [
                'muziek_id',
                'titel',
                'artiest',
                'jaar',
            ];
            $ontbrekende_kolommen = array_diff($verplichte_kolommen, array_keys($this->kolomtitels));
            if (count($ontbrekende_kolommen) > 0) {
                $ontbrekende_kolommen_str = implode(', ', $ontbrekende_kolommen);
                throw new GLDException(
                    "De volgende verplichte velden ontbreken in de sheet: {$ontbrekende_kolommen_str}"
                );
            }
        }
        return $this->kolomtitels;
    }

    /**
     * @param array<int, string> $rij
     * @param KolomKeysType $key
     * @param 'string'|'int' $type
     *
     * @return ($type is 'string' ? ($null_als_leeg is true ? ?string : string) : ($null_als_leeg is true ? ?int : int))
     */
    private function get_cel(
        array $rij,
        string $key,
        string $type,
        bool $null_als_leeg
    ): null|string|int {
        $keys = $this->get_kolomtitels();
        try {
            if ($type === 'string') {
                return self::filter_cell_string($rij[$keys[$key]], $null_als_leeg);
            } elseif ($type === 'int') {
                return self::filter_cell_int($rij[$keys[$key]]);
            }
        } catch (IndexException) {
            return $null_als_leeg
                ? null
                : '';
        }
    }

    public function import(): void
    {
        $this->db->disableAutocommit();
        $this->toevoegen();
        $this->verwijderen();
        $this->db->commit();
    }

    /**
     * Verwijderen van nummers in de database die niet meer in de Excel sheet
     * staan.
     */
    private function verwijderen(): void
    {
        /** @var list<list<string>> $data */
        $data = $this->excel->sheets[0]['cells'];

        $data = array_slice($data, 2);
        $powergold_ids = [];
        foreach ($data as $rij) {
            $powergold_id = self::get_cel($rij, 'muziek_id', 'string', true);
            if (isset($powergold_id)) {
                $powergold_ids[] = $this->db->escape_string($powergold_id);
            }
        }
        $i_powergold_ids = \implode(
            '","',
            $powergold_ids
        );
        $aantal = (int)$this->db->selectSingle(<<<EOT
        SELECT COUNT(id)
        FROM `nummers`
        WHERE
            `muziek_id` IS NOT NULL
            AND `muziek_id` NOT IN ("{$i_powergold_ids}");
        EOT);
        if ($aantal > 0) {
            $ans = readline_met_default(
                "{$aantal} nummers staan wel in de database, maar niet in de "
                . "het importbestand. Wilt u deze verwijderen? (j/n)",
                'n'
            );
            if (\strtolower(\trim($ans)) === 'j') {
                $this->db->query(<<<EOT
                DELETE FROM `nummers`
                WHERE
                    `muziek_id` IS NOT NULL
                    AND `muziek_id` NOT IN ("{$i_powergold_ids}");
                EOT);
                echo "{$aantal} nummers verwijderd.\n";
            }
        }
    }

    /**
     * Toevoegen en updaten van nummers.
     */
    private function toevoegen(): void
    {
        /** @var list<list<string>> $data */
        $data = $this->excel->sheets[0]['cells'];

        $data = array_slice($data, 2);

        $db = $this->db->getDB();
        $insert_query = <<<EOT
        INSERT INTO `nummers` (`muziek_id`, `titel`, `artiest`, `jaar`, `categorie`, `map`, `opener`, `duur`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        EOT;
        $update_query = <<<EOT
        UPDATE nummers
        SET
            muziek_id = ?,
            titel = ?,
            artiest = ?,
            jaar = ?,
            categorie = ?,
            map = ?,
            opener = ?,
            duur = ?
        WHERE
            id = ?
        EOT;
        $insert = $db->prepare($insert_query);
        if ($insert === false) {
            throw new SQLException('Prepared statement mislukt: ' . $db->error, $db->errno);
        }
        $update = $db->prepare($update_query);
        if ($update === false) {
            throw new SQLException('Prepared statement mislukt: ' . $db->error, $db->errno);
        }
        $nummer_id =
            $powergold_id =
            $titel =
            $artiest =
            $jaar =
            $categorie =
            $map =
            $opener =
            $duur = null;
        $res = $insert->bind_param(
            'sssissii',
            $powergold_id,
            $titel,
            $artiest,
            $jaar,
            $categorie,
            $map,
            $opener,
            $duur,
        );
        if ($res === false) {
            throw new SQLException('Prepared statement mislukt: ' . $insert->error, $insert->errno);
        }
        $res = $update->bind_param(
            'sssissiii',
            $powergold_id,
            $titel,
            $artiest,
            $jaar,
            $categorie,
            $map,
            $opener,
            $duur,
            $nummer_id,
        );
        if ($res === false) {
            throw new SQLException('Prepared statement mislukt: ' . $update->error, $update->errno);
        }
        $aantal_toegevoegd = 0;
        $aantal_bijgewerkt = 0;
        foreach ($data as $rij) {
            $titel = self::get_cel($rij, 'titel', 'string', true);
            if ($titel === 'Title' || $titel === null) {
                // Rij zonder nummer of herhaalde kolomtitels.
                continue;
            }
            $powergold_id = self::get_cel($rij, 'muziek_id', 'string', true);
            if ($powergold_id !== null && preg_match('~[^0-9a-zA-Z\-]~', $powergold_id) === 1) {
                // Ongeldige ID's
                $powergold_id = null;
            }
            $artiest = self::get_cel($rij, 'artiest', 'string', false);
            $jaar = self::get_cel($rij, 'jaar', 'int', true);
            if ($jaar === 0) {
                $jaar = null;
            }
            $categorie = self::get_cel($rij, 'categorie', 'string', true);
            $map = self::get_cel($rij, 'map', 'string', true);
            $opener_str = self::get_cel($rij, 'opener', 'string', false);
            $opener = strtolower($opener_str) === 'yes';
            $duur_str = self::get_cel($rij, 'duur', 'string', false);
            $duur_array = \explode(':', $duur_str);
            if (count($duur_array) === 2) {
                // Duur in minuten en seconden
                $duur = (int)$duur_array[0] * 60 + (int)$duur_array[1];
            } elseif (count($duur_array) === 1 && $duur_array[0] !== '') {
                // Duur in seconden
                $duur = (int)$duur_array[0];
            } else {
                $duur = null;
            }

            // Query samenstellen om te kijken of het nummer al bestaat.
            $e_titel = $this->db->escape_string($titel);
            $e_artiest = $this->db->escape_string($artiest);
            $cond_jaar = $jaar === null ? 'jaar IS NULL' : "jaar = {$jaar}";
            if ($powergold_id === null) {
                $query = <<<EOT
                SELECT id
                FROM nummers
                WHERE
                    titel = "{$e_titel}"
                    AND artiest = "{$e_artiest}"
                    AND {$cond_jaar}
                ORDER BY id
                EOT;
            } else {
                $e_powergold_id = $this->db->escape_string($powergold_id);
                $query = <<<EOT
                SELECT id
                FROM nummers
                WHERE
                    muziek_id = "{$e_powergold_id}"
                    OR (
                        titel = "{$e_titel}"
                        AND artiest = "{$e_artiest}"
                        AND {$cond_jaar}
                    )
                ORDER BY id
                EOT;
            }

            $nummer_ids = \array_map(fn($i) => (int)$i, $this->db->selectSingleColumn($query));
            $nummer_id = \array_shift($nummer_ids);
            if ($nummer_id === null) {
                // Nummer bestaat nog niet, dus toevoegen.
                $res = $insert->execute();
                if ($res === false) {
                    throw new SQLException('Query mislukt: ' . $insert->error, $insert->errno);
                }
                $aantal_toegevoegd++;
            } else {
                // Nummer bestaat al, dus bijwerken.
                if (count($nummer_ids) > 0) {
                    // Er bestaat een nummer met dezelfde titel en artiest, maar een andere Powergold ID.
                    // Deze moeten worden samengevoegd voordat het powergold ID kan worden ge-update.
                    nummers_samenvoegen($this->db, $nummer_id, $nummer_ids);
                }
                $res = $update->execute();
                if ($res === false) {
                    throw new SQLException('Query mislukt: ' . $update->error, $update->errno);
                }
                $aantal_bijgewerkt += $db->affected_rows;
            }
        }
        $insert->close();
        $update->close();
        echo "{$aantal_toegevoegd} nummers geïmporteerd en {$aantal_bijgewerkt} bijgewerkt.\n";
    }
}

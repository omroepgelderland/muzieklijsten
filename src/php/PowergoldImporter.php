<?php

declare(strict_types=1);

namespace muzieklijsten;

use gldstdlib\exception\SQLException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PowergoldImporter
{
    private const HEADERS = [
        'muziek_id' => 'Miscellaneous',
        'titel' => 'Title',
        'artiest' => 'Artist(s)',
        'jaar' => 'Year',
        'categorie' => 'Category',
        'map' => 'Folder',
        'opener' => 'Opener',
        'duur' => 'Run Time',
    ];
    private const VERPLICHTE_HEADERS = [
        'muziek_id',
        'titel',
        'artiest',
        'jaar',
        'duur',
    ];

    private readonly Worksheet $worksheet;

    public function __construct(
        private DB $db,
        string $filename,
    ) {
        $spreadsheet = IOFactory::load($filename);
        $this->worksheet = $spreadsheet->getSheet(0);
    }

    /**
     * @return array{
     *     toegevoegd: int<0, max>,
     *     bijgewerkt: int<0, max>,
     * }
     */
    public function import(): array
    {
        $header_data = $this->vind_kolommen();
        $kolommen = $header_data['kolommen'];
        $vanaf_rij = $header_data['vanaf_rij'];

        $this->db->disableAutocommit();
        $import_res = $this->toevoegen_of_bijwerken($kolommen, $vanaf_rij);
        $this->db->commit();
        return [
            'toegevoegd' => $import_res['toegevoegd'],
            'bijgewerkt' => $import_res['bijgewerkt'],
        ];
    }

    /**
     * @return list<int>
     */
    public function get_verwijderbare_nummer_ids(): array
    {
        $header_data = $this->vind_kolommen();
        $kolommen = $header_data['kolommen'];
        $vanaf_rij = $header_data['vanaf_rij'];
        return $this->get_verwijderbare_nummer_ids_op_basis_van_import($kolommen['muziek_id'], $vanaf_rij);
    }

    /**
     * @return array{
     *     kolommen: array<string, int>,
     *     vanaf_rij: int
     * }
     */
    private function vind_kolommen(): array
    {
        $hoogste_kolom = $this->worksheet->getHighestDataColumn();
        $hoogste_kolom_index = Coordinate::columnIndexFromString($hoogste_kolom);
        $hoogste_rij = $this->worksheet->getHighestDataRow();
        $scan_tot_rij = $hoogste_rij;

        for ($rij = 1; $rij <= $scan_tot_rij; $rij++) {
            $kolommen = [];
            for ($kolom = 1; $kolom <= $hoogste_kolom_index; $kolom++) {
                $waarde = $this->normaliseer_string(
                    (string)$this->worksheet->getCell([$kolom, $rij])->getFormattedValue(),
                );
                if ($waarde === null) {
                    continue;
                }
                foreach (self::HEADERS as $key => $header) {
                    if ($waarde === $header) {
                        $kolommen[$key] = $kolom;
                    }
                }
            }
            $ontbrekende = array_diff(self::VERPLICHTE_HEADERS, array_keys($kolommen));
            if (\count($ontbrekende) === 0) {
                return [
                    'kolommen' => $kolommen,
                    'vanaf_rij' => $rij + 1,
                ];
            }
        }

        throw new GebruikersException(
            'De verplichte kolommen ontbreken. Vereist zijn: '
            . implode(', ', array_map(
                static fn(string $key): string => self::HEADERS[$key],
                self::VERPLICHTE_HEADERS,
            )),
        );
    }

    /**
     * @return list<int>
     */
    private function get_verwijderbare_nummer_ids_op_basis_van_import(int $kolom_muziek_id, int $vanaf_rij): array
    {
        $powergold_ids = $this->get_powergold_ids($kolom_muziek_id, $vanaf_rij);
        $niet_in_import_query = $this->get_niet_in_import_query($powergold_ids);
        return array_values(iterator_to_array($this->db->selectSingleColumnInt(<<<SQL
        SELECT id
        FROM `nummers`
        WHERE
            `muziek_id` IS NOT NULL
            {$niet_in_import_query}
        SQL)));
    }

    /**
     * @return list<string>
     */
    private function get_powergold_ids(int $kolom_muziek_id, int $vanaf_rij): array
    {
        $powergold_ids = [];
        $hoogste_rij = $this->worksheet->getHighestDataRow();
        for ($rij = $vanaf_rij; $rij <= $hoogste_rij; $rij++) {
            $powergold_id = $this->normaliseer_string(
                (string)$this->worksheet->getCell([$kolom_muziek_id, $rij])->getFormattedValue(),
            );
            if ($powergold_id !== null) {
                $powergold_ids[] = $powergold_id;
            }
        }
        return $powergold_ids;
    }

    /**
     * @param list<string> $powergold_ids
     */
    private function get_niet_in_import_query(array $powergold_ids): string
    {
        if (\count($powergold_ids) === 0) {
            return '';
        }
        $escaped_powergold_ids = array_map(
            fn(string $id): string => $this->db->escape_string($id),
            $powergold_ids,
        );
        $powergold_ids_str = implode('","', $escaped_powergold_ids);
        return "AND `muziek_id` NOT IN (\"{$powergold_ids_str}\")";
    }

    /**
     * @param array<string, int> $kolommen
     *
     * @return array{
     *     toegevoegd: int<0, max>,
     *     bijgewerkt: int<0, max>,
     * }
     */
    private function toevoegen_of_bijwerken(array $kolommen, int $vanaf_rij): array
    {
        $db = $this->db->getDB();
        $insert_query = <<<'SQL'
        INSERT INTO `nummers` (
            `muziek_id`,
            `titel`,
            `artiest`,
            `jaar`,
            `categorie`,
            `map`,
            `opener`,
            `duur`,
            `vgl_titel`,
            `vgl_artiest`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL;
        $update_query = <<<'SQL'
        UPDATE nummers
        SET
            muziek_id = ?,
            titel = ?,
            artiest = ?,
            jaar = ?,
            categorie = ?,
            map = ?,
            opener = ?,
            duur = ?,
            is_vrijekeuze = 0,
            vgl_titel = ?,
            vgl_artiest = ?
        WHERE
            id = ?
        SQL;
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
            $duur =
            $vgl_titel =
            $vgl_artiest = null;
        $res = $insert->bind_param(
            'sssissiiss',
            $powergold_id,
            $titel,
            $artiest,
            $jaar,
            $categorie,
            $map,
            $opener,
            $duur,
            $vgl_titel,
            $vgl_artiest,
        );
        if ($res === false) {
            throw new SQLException('Prepared statement mislukt: ' . $insert->error, $insert->errno);
        }
        $res = $update->bind_param(
            'sssissiissi',
            $powergold_id,
            $titel,
            $artiest,
            $jaar,
            $categorie,
            $map,
            $opener,
            $duur,
            $vgl_titel,
            $vgl_artiest,
            $nummer_id,
        );
        if ($res === false) {
            throw new SQLException('Prepared statement mislukt: ' . $update->error, $update->errno);
        }
        $aantal_toegevoegd = 0;
        $aantal_bijgewerkt = 0;
        $hoogste_rij = $this->worksheet->getHighestDataRow();
        for ($rij = $vanaf_rij; $rij <= $hoogste_rij; $rij++) {
            $titel = $this->get_string($kolommen, 'titel', $rij, true);
            if ($titel === null || $titel === self::HEADERS['titel']) {
                // Rij zonder nummer of herhaalde kolomtitels.
                continue;
            }
            $vgl_titel = get_vgl_string($titel, false);
            $powergold_id = $this->get_string($kolommen, 'muziek_id', $rij, true);
            if ($powergold_id !== null && preg_match('~[^0-9a-zA-Z\-]~', $powergold_id) === 1) {
                // Ongeldige ID's
                $powergold_id = null;
            }
            if ($powergold_id === null) {
                continue;
            }
            $artiest = $this->get_string($kolommen, 'artiest', $rij, false) ?? '';
            $vgl_artiest = get_vgl_string($artiest, true);
            $jaar = $this->get_year($kolommen, 'jaar', $rij);
            if ($jaar === 0) {
                $jaar = null;
            }
            $categorie = $this->get_string($kolommen, 'categorie', $rij, true);
            $map = $this->get_string($kolommen, 'map', $rij, true);
            $opener_str = $this->get_string($kolommen, 'opener', $rij, false) ?? '';
            $opener = strtolower($opener_str) === 'yes';
            $duur = $this->get_duur($kolommen, 'duur', $rij);

            // Query samenstellen om te kijken of het nummer al bestaat.
            $e_titel = $this->db->escape_string($vgl_titel);
            $e_artiest = $this->db->escape_string($vgl_artiest);
            // Bij geen jaar in de import zijn alle titels in de database
            // matches
            // Bij wel een jaar in de import zijn de titels zonder jaartal plus
            // de titels met dat jaartal matches.
            $cond_jaar = $jaar === null ? '' : "AND (`jaar` = {$jaar} OR `jaar` IS NULL)";
            $e_powergold_id = $this->db->escape_string($powergold_id);
            $query = <<<SQL
            SELECT id
            FROM nummers
            WHERE
                muziek_id = "{$e_powergold_id}"
                OR (
                    vgl_titel = "{$e_titel}"
                    AND vgl_artiest = "{$e_artiest}"
                    {$cond_jaar}
                )
            ORDER BY id
            SQL;

            $nummer_ids = iterator_to_array($this->db->selectSingleColumnInt($query));
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
                if (\count($nummer_ids) > 0) {
                    // Er bestaat een nummer met dezelfde titel en artiest, maar een andere Powergold ID.
                    // Deze moeten worden samengevoegd voordat het powergold ID kan worden ge-update.
                    nummers_samenvoegen($this->db, $nummer_id, $nummer_ids);
                }
                $res = $update->execute();
                if ($res === false) {
                    throw new SQLException('Query mislukt: ' . $update->error, $update->errno);
                }
                $aantal_bijgewerkt += (int)$db->affected_rows;
            }
        }
        $insert->close();
        $update->close();
        $aantal_bijgewerkt = max(0, $aantal_bijgewerkt);
        return [
            'toegevoegd' => $aantal_toegevoegd,
            'bijgewerkt' => $aantal_bijgewerkt,
        ];
    }

    private function normaliseer_string(string $waarde): ?string
    {
        $res = trim($waarde);
        return $res === '' ? null : $res;
    }

    /**
     * @param array<string, int> $kolommen
     */
    private function get_string(
        array $kolommen,
        string $key,
        int $rij,
        bool $null_als_leeg,
    ): ?string {
        if (!isset($kolommen[$key])) {
            return $null_als_leeg ? null : '';
        }
        $waarde = $this->normaliseer_string(
            (string)$this->worksheet->getCell([$kolommen[$key], $rij])->getFormattedValue(),
        );
        if ($waarde === null && !$null_als_leeg) {
            return '';
        }
        return $waarde;
    }

    /**
     * @param array<string, int> $kolommen
     */
    private function get_year(array $kolommen, string $key, int $rij): ?int
    {
        $string = $this->get_string($kolommen, $key, $rij, true);
        if ($string === null) {
            return null;
        }
        $res = filter_var($string, \FILTER_VALIDATE_INT);
        if ($res === false) {
            throw new GebruikersException("Ongeldig jaartal op rij {$rij}: {$string}");
        }
        return (int)$res;
    }

    /**
     * @param array<string, int> $kolommen
     */
    private function get_duur(array $kolommen, string $key, int $rij): ?int
    {
        if (!isset($kolommen[$key])) {
            return null;
        }
        $cel = $this->worksheet->getCell([$kolommen[$key], $rij]);
        $raw = $cel->getValue();
        if (\is_float($raw) || \is_int($raw)) {
            $duur = (float)$raw;
            if ($duur > 0 && $duur < 1) {
                return (int)round($duur * 86400);
            }
            if ($duur >= 1) {
                return (int)round($duur);
            }
            return null;
        }
        $duur_string = $this->normaliseer_string((string)$cel->getFormattedValue());
        if ($duur_string === null) {
            return null;
        }
        if (preg_match('/^([0-9]{1,2}):([0-5][0-9])(?::([0-5][0-9]))?$/', $duur_string, $m) === 1) {
            if (isset($m[3])) {
                return ((int)$m[1] * 3600) + ((int)$m[2] * 60) + (int)$m[3];
            }
            return ((int)$m[1] * 60) + (int)$m[2];
        }
        $res = filter_var($duur_string, \FILTER_VALIDATE_INT);
        if ($res !== false) {
            return (int)$res;
        }
        throw new GebruikersException("Ongeldige duur op rij {$rij}: {$duur_string}");
    }
}

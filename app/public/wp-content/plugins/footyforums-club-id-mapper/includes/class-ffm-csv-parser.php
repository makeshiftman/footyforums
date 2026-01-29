<?php
/**
 * CSV Parser for Football Data Provider Mappings
 *
 * Loads and parses the provider mapping CSV file, extracting provider IDs
 * and name variations for club matching operations.
 */

defined('ABSPATH') || exit;

class FFM_CSV_Parser {

    /**
     * Provider ID columns: CSV column name => database column name.
     */
    const PROVIDER_ID_COLUMNS = [
        'whoScoredId'    => 'w_id',
        'transfermarktId' => 't_id',
        'sofifaId'       => 'sf_id',
        'optaId'         => 'o_id',
        'fotmobId'       => 'fmob_id',
        'sportmonksId'   => 'sm_id',
        'inStatId'       => 'is_id',
        'skillCornerId'  => 'sc_id',
        'fmId'           => 'fmgr_id',
    ];

    /**
     * Name columns to extract (CSV column names).
     */
    const NAME_COLUMNS = [
        'name',
        'whoScoredName',
        'sofifaName',
        'statsName',
        'optaName',
        'inStatName',
        'transfermarktName',
        'footballDataName',
        'fmName',
        'skillCornerName',
        'fotmobName',
        'sportmonksName',
    ];

    /**
     * Path to the CSV file.
     *
     * @var string
     */
    private $file_path;

    /**
     * Parsed data rows.
     *
     * @var array
     */
    private $rows = [];

    /**
     * CSV header columns.
     *
     * @var array
     */
    private $headers = [];

    /**
     * Constructor.
     *
     * @param string|null $file_path Optional path to CSV file. Defaults to known CSV location.
     */
    public function __construct($file_path = null) {
        $this->file_path = $file_path ?? $this->get_default_csv_path();
    }

    /**
     * Get the default CSV file path.
     *
     * @return string Path to the provider mapping CSV.
     */
    private function get_default_csv_path() {
        return ABSPATH . '../docs/providers/mapping.teamsAlias.csv';
    }

    /**
     * Load and parse the CSV file.
     *
     * @return bool True on success, false on failure.
     */
    public function load() {
        $handle = fopen($this->file_path, 'r');

        if ($handle === false) {
            error_log('FFM_CSV_Parser: Failed to open file: ' . $this->file_path);
            return false;
        }

        // Read header row
        $this->headers = fgetcsv($handle);

        if ($this->headers === false) {
            error_log('FFM_CSV_Parser: Failed to read header row from: ' . $this->file_path);
            fclose($handle);
            return false;
        }

        // Read all data rows
        $this->rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (count($row) === 1 && empty($row[0])) {
                continue;
            }

            // Create associative array keyed by header names
            $assoc_row = [];
            foreach ($this->headers as $index => $header) {
                $assoc_row[$header] = isset($row[$index]) ? $row[$index] : '';
            }
            $this->rows[] = $assoc_row;
        }

        fclose($handle);
        return true;
    }

    /**
     * Check if data has been loaded.
     *
     * @return bool True if data is loaded.
     */
    public function is_loaded() {
        return !empty($this->headers) && !empty($this->rows);
    }

    /**
     * Get the count of data rows (excluding header).
     *
     * @return int Number of data rows.
     */
    public function get_row_count() {
        return count($this->rows);
    }

    /**
     * Get all rows as array of associative arrays.
     *
     * @return array All data rows.
     */
    public function get_rows() {
        return $this->rows;
    }

    /**
     * Get a single row by index (0-based).
     *
     * @param int $index Row index.
     * @return array|null Row data or null if index out of bounds.
     */
    public function get_row($index) {
        return isset($this->rows[$index]) ? $this->rows[$index] : null;
    }

    /**
     * Validate that required columns exist in the CSV.
     *
     * @return array Array of missing column names (empty if valid).
     */
    public function validate_structure() {
        $required = [
            'name',
            'country',
            'whoScoredId',
            'transfermarktId',
            'sofifaId',
            'optaId',
        ];

        $missing = [];
        foreach ($required as $column) {
            if (!in_array($column, $this->headers, true)) {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    /**
     * Extract provider IDs from a row.
     *
     * @param array $row Associative array (single CSV row).
     * @return array Array of ['db_column' => 'value'] for non-empty IDs.
     */
    public function get_provider_ids($row) {
        $provider_ids = [];

        foreach (self::PROVIDER_ID_COLUMNS as $csv_col => $db_col) {
            if (isset($row[$csv_col])) {
                $value = trim($row[$csv_col]);
                if ($value !== '') {
                    $provider_ids[$db_col] = $value;
                }
            }
        }

        return $provider_ids;
    }

    /**
     * Extract all name variations from a row.
     *
     * @param array $row Associative array (single CSV row).
     * @return array Array of unique, non-empty name strings.
     */
    public function get_name_variations($row) {
        $names = [];

        foreach (self::NAME_COLUMNS as $col) {
            if (isset($row[$col])) {
                $value = trim($row[$col]);
                if ($value !== '' && !in_array($value, $names, true)) {
                    $names[] = $value;
                }
            }
        }

        return $names;
    }

    /**
     * Extract the country field from a row.
     *
     * @param array $row Associative array (single CSV row).
     * @return string Trimmed country string or empty string if not set.
     */
    public function get_country($row) {
        if (isset($row['country'])) {
            return trim($row['country']);
        }
        return '';
    }

    /**
     * Get the primary/canonical name from a row.
     *
     * @param array $row Associative array (single CSV row).
     * @return string Trimmed name string or empty string if not set.
     */
    public function get_primary_name($row) {
        if (isset($row['name'])) {
            return trim($row['name']);
        }
        return '';
    }
}

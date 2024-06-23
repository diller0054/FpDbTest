<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    protected mysqli $mysqli;

    public function __construct(
        mysqli $mysqli
    )
    {
        $this->mysqli = $mysqli;
    }

    /**
     *
     * Working with the value identifier
     *
     * @param string $identifier
     *
     * @return string
     */
    protected function escapeIdentifier(
        string $identifier
    ): string
    {
        return "`$identifier`";
    }

    /**
     *
     * It works with the fill value
     *
     * @param int|bool|string|float|null $value
     *
     * @return string
     */
    protected function escapeValue(
        int|bool|null|string|float $value
    ): string
    {
        return match (gettype($value)) {
            'NULL' => 'NULL',
            'boolean' => $value ? '1' : '0',
            'integer', 'double' => (string)$value,
            default => "'$value'",
        };
    }

    /**
     *
     * Function specifier for working with arrays of values
     *
     * @param array $array
     *
     * @return string
     */
    protected function formatArray(
        array $array
    ): string
    {
        $formatted = [];

        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $formatted[] = $this->escapeValue($value);
            } else {
                $formatted[] = $this->escapeIdentifier($key) . ' = ' . $this->escapeValue($value);
            }
        }

        return implode(', ', $formatted);
    }

    /**
     *
     * Function specifier for working with a hash
     *
     * @param array $arg
     *
     * @return string
     */
    protected function processHashSpecifier(
        array $arg
    ): string
    {
        $identifiers = array_map([$this, 'escapeIdentifier'], $arg);
        return implode(', ', $identifiers);
    }

    /**
     *
     * The process specifier returns the filling of
     * parameters based on the specified specifier
     *
     * @param string $specifier
     * @param int|float|string|array|null $arg
     *
     * @return string
     */
    protected function processSpecifier(
        string $specifier,
        int|float|string|array|null $arg
    ):string
    {
        return match ($specifier) {
            'd' => is_null($arg) ? 'NULL' : (int)$arg,
            'f' => is_null($arg) ? 'NULL' : (float)$arg,
            'a' => $this->formatArray($arg),
            '#' => is_array($arg) ? $this->processHashSpecifier($arg) : $this->escapeIdentifier((string)$arg),
            default => $this->escapeValue($arg),
        };
    }

    /**
     *
     * Auxiliary function of working only with a conditional block
     *
     * @param string $block
     * @param array $args
     *
     * @return string
     * @throws Exception
     */
    protected function processConditionalBlock(
        string $block,
        array $args
    ): string
    {
        $sql = '';
        $length = strlen($block);
        $argIndex = count($args) - 1;

        for ($i = 0; $i < $length; $i++) {
            if ($block[$i] === '?') {

                /**
                 * Working with all the parameters that need to be filled in
                 */
                $specifier = $block[$i + 1] ?? '';

                if ($specifier === '') {
                    throw new Exception('Invalid query format: ? without specifier.');
                }

                $arg = ($argIndex >= 0) ? $args[$argIndex] : null;
                $argIndex--;

                $sql .= $this->processSpecifier($specifier, $arg);

                $i++;
            } else {

                /**
                 * Adds everything else
                 */
                $sql .= $block[$i];
            }
        }

        return $sql;
    }

    /**
     *
     * The main function of filling, assembling sql queries
     *
     * @param string $query
     * @param array $args
     *
     * @return string
     * @throws Exception
     */
    public function buildQuery(
        string $query,
        array $args = []
    ): string
    {
        $sql = '';

        $length = strlen($query);
        $argIndex = 0;

        for ($i = 0; $i < $length; $i++) {

            if ($query[$i] === '{') {

                /**
                 * Working with a conditional block
                 */
                $closingBracePos = strpos($query, '}', $i);

                if ($closingBracePos === false) {
                    throw new Exception('Unmatched { in query.');
                }

                if (end($args) !== $this->skip()) {
                    $blockContent = substr($query, $i + 1, $closingBracePos - $i - 1);
                    $processedBlock = $this->processConditionalBlock($blockContent, $args);

                    $sql .= $processedBlock;
                }

                $i = $closingBracePos;

            } elseif ($query[$i] === '?') {

                /**
                 * Working with all the parameters that need to be filled in
                 */
                $specifier = $query[$i + 1] ?? '';

                if ($specifier === '') {
                    throw new Exception('Invalid query format: ? without specifier.');
                }

                $arg = $args[$argIndex] ?? null;
                $argIndex++;

                $sql .= $this->processSpecifier($specifier, $arg);

                if($specifier === ' ') {
                    $sql .= ' ';
                }

                $i++;
            } else {

                /**
                 * Adds everything else
                 */
                $sql .= $query[$i];
            }
        }

        return $sql;
    }

    /**
     *
     * Skipping function
     *
     * @return string
     */
    public function skip(): string
    {
        return '__SKIP__';
    }

}

























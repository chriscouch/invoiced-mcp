<?php

namespace App\Core\Csv;

/**
 * Utility class for writing to CSV files
 * in a manner that protects against formula
 * injection.
 */
class CsvWriter
{
    // used to escape values that look like value Excel formulas
    const EXCEL_FORMULA_ESCAPE_CHAR = "'";

    public static array $dangerousExcelChars = [
        '=',
        '+',
        '-',
        '@',
    ];

    /**
     * This is a wrapper around fputcsv() that adds sanitization
     * to protect against formula injection vulnerabilities in MS Excel.
     *
     * @see http://php.net/manual/en/function.fputcsv.php
     *
     * @param resource $handle      the file pointer must be valid, and must point to a file successfully opened by fopen() or fsockopen() (and not yet closed by fclose())
     * @param array    $fields      <p>
     *                              An array of values.
     *                              </p>
     * @param string   $delimiter   [optional] <p>
     *                              The optional delimiter parameter sets the field
     *                              delimiter (one character only).
     *                              </p>
     * @param string   $enclosure   [optional] <p>
     *                              The optional enclosure parameter sets the field
     *                              enclosure (one character only).
     *                              </p>
     * @param string   $escape_char the optional escape_char parameter sets the escape character (one character only)
     *
     * @return int|false the length of the written string or false on failure
     *
     * @since 5.1.0
     */
    public static function write($handle, array $fields, string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\')
    {
        foreach ($fields as &$value) {
            if (is_string($value) && in_array(substr($value, 0, 1), self::$dangerousExcelChars)) {
                if (!preg_match('/^-\d+(\.\d+)?$/', $value)) // if the value is not a negative number like -123, otherwise add ' in the front to escape Excel format
                    $value = self::EXCEL_FORMULA_ESCAPE_CHAR.$value;
            }
        }

        return fputcsv($handle, $fields, $delimiter, $enclosure, $escape_char);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class CsvImportService
{
    const IMPORT_DIR = 'imports/';

    /**
     * Parse CSV file and execute a given callback on each line processed
     *
     * @param string $filePath Path to CSV file
     * @param callable $callback A function to execute on each row/line
     * 
     * @return array The CSV lines as array mapping each value to their header
     */
    public function parseCsv(string $filePath, callable $callback = null): array
    {
        $counter = 0;
        $parsedCsv = [];
        $csvHeaders = [];
        $csvFile = fopen($filePath, 'r');

        while(($data = fgetcsv($csvFile)) !== false) {
            //* extract csv headers
            if ($counter === 0) {
                //* remove all non-printable characters and trailing white space
                // '﻿Processor Type'
                $csvHeaders = array_map(
                    fn ($value) => preg_replace('/[[:^print:]]/', '', trim($value)),
                    $data
                );

                $counter++;

                continue;
            }

            //* map each value in a row to it's corresponding header
            //*     [Fullname(header) => Jhon Doe(value)]
            $row = array_combine($csvHeaders, $data);
            $parsedCsv[] = $row;
            
            //* execute user's callback passing it parsed csv line
            if ($callback !== null) {
                call_user_func($callback, $row, $counter);
            }

            $counter++;
        }

        fclose($csvFile);

        return $parsedCsv;
    }
}

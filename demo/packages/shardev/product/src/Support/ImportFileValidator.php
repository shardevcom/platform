<?php

declare(strict_types=1);

namespace Shardevcom\Product\Support;

use Illuminate\Support\Facades\Storage;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Exceptions\RequestEntityTooLarge;
use Shardevcom\Core\Exceptions\UnsupportedMediaType;
use ZipArchive;

/**
 * T-CASA-016 — transport-neutral validation of an uploaded import file.
 *
 * Runs on the STORED copy (disk+path), never on the multipart object or the
 * filename, so the same rules apply to REST, GraphQL and direct use-case
 * calls. Every check inspects the file's real content:
 *
 *  - declared format not xlsx/csv        -> 415 UnsupportedMediaType
 *  - size over the limit                 -> 413 RequestEntityTooLarge
 *  - content is not a real xlsx (a plain
 *    zip renamed to .xlsx, or garbage)   -> 415 UnsupportedMediaType
 *  - macros (vbaProject.bin)             -> 422 BusinessRuleViolation
 *  - formula cells in the raw content    -> 422 BusinessRuleViolation
 *
 * Nothing here parses the spreadsheet with a spreadsheet engine: xlsx checks
 * read the zip structure and raw worksheet XML, csv checks stream the bytes.
 * The full parse stays in the queue (PrepareProductImport). Formula rejection
 * is also defense in depth: PhpSpreadsheet imports read-only and never
 * evaluates a formula, so a formula cell is refused but can never run.
 */
final class ImportFileValidator
{
    private const SUPPORTED_FORMATS = ['xlsx', 'csv'];

    /**
     * Validate a stored file before it is queued for import.
     */
    public function validate(string $disk, string $path, string $format): void
    {
        $this->assertSizeWithinLimit($disk, $path);

        $extension = mb_strtolower($format);

        match ($extension) {
            'xlsx' => $this->validateXlsx($disk, $path),
            'csv' => $this->validateCsv($disk, $path),
            default => throw new UnsupportedMediaType(
                'Only '.implode(' and ', self::SUPPORTED_FORMATS).' files are supported.',
            ),
        };
    }

    private function assertSizeWithinLimit(string $disk, string $path): void
    {
        $limit = (int) config('platform.import.max_file_size', 10 * 1024 * 1024);

        if (Storage::disk($disk)->size($path) > $limit) {
            throw new RequestEntityTooLarge(
                'File exceeds the maximum import size of '.number_format($limit / 1024 / 1024, 1).' MB.',
            );
        }
    }

    private function validateXlsx(string $disk, string $path): void
    {
        $absolute = Storage::disk($disk)->path($path);

        if (! $this->hasZipMagic($this->firstBytes($absolute, 4))) {
            throw new UnsupportedMediaType('The uploaded file is not an xlsx spreadsheet.');
        }

        $zip = new ZipArchive();

        if ($zip->open($absolute) !== true) {
            throw new UnsupportedMediaType('The uploaded file is not a readable xlsx archive.');
        }

        try {
            if ($zip->locateName('xl/workbook.xml') === false) {
                throw new UnsupportedMediaType(
                    'The uploaded file is not a valid xlsx: it has no workbook (was it renamed from another archive?).',
                );
            }

            if ($zip->locateName('vbaProject.bin') !== false) {
                throw new BusinessRuleViolation(
                    'Macros are not allowed on imported spreadsheets.',
                    ['reason' => 'macro_detected'],
                );
            }

            $this->assertNoFormulas($zip);
        } finally {
            $zip->close();
        }
    }

    private function validateCsv(string $disk, string $path): void
    {
        $absolute = Storage::disk($disk)->path($path);

        $handle = fopen($absolute, 'rb');

        if ($handle === false) {
            throw new UnsupportedMediaType('The uploaded file cannot be read.');
        }

        try {
            $head = (string) fread($handle, 512);

            if ($this->hasZipMagic($head)) {
                throw new UnsupportedMediaType('The uploaded file is a binary archive, not a csv file.');
            }

            if (str_contains($head, "\0")) {
                throw new UnsupportedMediaType('The uploaded file is not a text csv file.');
            }
        } finally {
            fclose($handle);
        }

        $handle = fopen($absolute, 'rb');

        if ($handle === false) {
            throw new UnsupportedMediaType('The uploaded file cannot be read.');
        }

        try {
            $rowNumber = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                foreach ($row as $column => $cell) {
                    if ($this->isFormulaCell($cell)) {
                        throw new BusinessRuleViolation(
                            'Formula cells are not allowed on imported spreadsheets.',
                            ['reason' => 'formula_cell_detected', 'row' => $rowNumber, 'column' => (int) $column + 1],
                        );
                    }
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Reject any raw worksheet XML that declares a formula (an <f> node whose
     * compiled text starts with '='). Read-only analysis: the cells are never
     * evaluated, the text is never echoed back to the client.
     */
    private function assertNoFormulas(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (! preg_match('~^xl/worksheets/[^/]+\.xml$~', $name)) {
                continue;
            }

            $xml = $zip->getFromName($name);

            if ($xml === false) {
                continue;
            }

            if (preg_match_all('~<f(?:\s+[^>]*)?>(.*?)</f>~s', $xml, $matches)) {
                foreach ($matches[1] as $formula) {
                    if (str_starts_with(html_entity_decode(trim($formula), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), '=')) {
                        throw new BusinessRuleViolation(
                            'Formula cells are not allowed on imported spreadsheets.',
                            ['reason' => 'formula_cell_detected'],
                        );
                    }
                }
            }
        }
    }

    private function isFormulaCell(mixed $cell): bool
    {
        if ($cell === null) {
            return false;
        }

        $value = ltrim((string) $cell, "\t ");

        return str_starts_with($value, '=');
    }

    private function hasZipMagic(string $head): bool
    {
        return str_starts_with($head, "PK\x03\x04")
            || str_starts_with($head, "PK\x05\x06")
            || str_starts_with($head, "PK\x07\x08");
    }

    private function firstBytes(string $path, int $length): string
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new UnsupportedMediaType('The uploaded file cannot be read.');
        }

        try {
            return (string) fread($handle, $length);
        } finally {
            fclose($handle);
        }
    }
}
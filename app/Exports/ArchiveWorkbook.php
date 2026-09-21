<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * A generic multi-sheet workbook used to archive a semester's bulk data to Excel
 * before it is pruned from the database.
 */
class ArchiveWorkbook implements WithMultipleSheets
{
    /**
     * @param array<int, array{title:string, headings:array, rows:array}> $sheets
     */
    public function __construct(private array $sheets) {}

    public function sheets(): array
    {
        return array_map(
            fn (array $s) => new ArchiveSheet($s['title'], $s['headings'], $s['rows']),
            $this->sheets
        );
    }
}

class ArchiveSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private string $title,
        private array $headings,
        private array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }
}

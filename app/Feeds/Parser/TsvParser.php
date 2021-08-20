<?php

namespace App\Feeds\Parser;

class TsvParser extends TxtParser
{
    protected string $column_delimiter = "\t";

    public function parseRow($row): array
    {
        return explode($this->column_delimiter, $row);
    }
}

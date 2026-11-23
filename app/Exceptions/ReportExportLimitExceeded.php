<?php

namespace App\Exceptions;

use RuntimeException;

class ReportExportLimitExceeded extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("O relatório excede o limite seguro de {$limit} registros. Refine os filtros e tente novamente.");
    }
}

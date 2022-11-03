<?php

namespace App\Services\Analysis\Report\Spreadsheet;

abstract class AbstractConsolidation
{
    abstract protected function formatConsolidation($existingEnvironment, $targetEnvironment);
}
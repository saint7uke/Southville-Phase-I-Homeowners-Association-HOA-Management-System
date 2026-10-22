<?php

declare(strict_types=1);

namespace App\Support;

use setasign\Fpdi\Tcpdf\Fpdi;

final class OriginalLayoutPdf extends Fpdi
{
    public function __construct()
    {
        parent::__construct('P', 'pt', 'LETTER', true, 'UTF-8', false);
        // Preserve the source layout: no automatic vendor attribution in page content.
        $this->tcpdflink = false;
    }
}

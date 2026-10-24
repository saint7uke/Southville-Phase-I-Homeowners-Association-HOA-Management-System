<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ExportLetterheadUnavailable extends HttpException
{
    public function __construct()
    {
        parent::__construct(503, __('The export letterhead is unavailable. Please contact the administrator.'));
    }

    public function render(Request $request)
    {
        $returnUrl = match (true) {
            $request->is('admin/households/*') => '/admin/households',
            $request->user()?->hasRole('homeowner') => '/homeowner/my-household',
            default => '/admin/reports',
        };

        return response()->view('errors.export-letterhead', [
            'returnUrl' => $returnUrl,
        ], 503)->header('Cache-Control', 'private, no-store');
    }
}

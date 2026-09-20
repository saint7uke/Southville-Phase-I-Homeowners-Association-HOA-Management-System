<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use Illuminate\Support\Facades\DB;

final class NextCertificateNumber
{
    public function handle(int $year): string
    {
        DB::table('certificate_sequences')->insertOrIgnore([
            'year' => $year,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('certificate_sequences')->where('year', $year)->lockForUpdate()->first();
        $number = (int) $sequence->next_number;

        DB::table('certificate_sequences')->where('year', $year)->update([
            'next_number' => $number + 1,
            'updated_at' => now(),
        ]);

        return sprintf('CERT-%d-%04d', $year, $number);
    }
}

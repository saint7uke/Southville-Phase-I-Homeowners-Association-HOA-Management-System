<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Seeder;

final class RegistrationReceiptBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'phase23-browser-test')) {
            throw new \RuntimeException('Requires an isolated, explicitly named browser test database.');
        }
        if (! User::exists()) {
            $this->call(Phase23BrowserSeeder::class);
        }
        // Stable synthetic collector name fits the exact original receipt blank; never a real officer.
        User::where('email', 'admin@phase23.test')->firstOrFail()->update(['middle_name' => null]);
    }
}

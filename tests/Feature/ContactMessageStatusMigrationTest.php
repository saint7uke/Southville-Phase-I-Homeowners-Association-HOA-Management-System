<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class ContactMessageStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_column_round_trip_preserves_existing_short_statuses(): void
    {
        foreach (['New', 'In Progress', 'Closed'] as $status) {
            $this->message($status);
        }

        $migration = $this->migration();
        $migration->down();

        $this->assertEqualsCanonicalizing(
            ['New', 'In Progress', 'Closed'],
            ContactMessage::query()->pluck('status')->all(),
        );

        $migration->up();

        $this->assertEqualsCanonicalizing(
            ['New', 'In Progress', 'Closed'],
            ContactMessage::query()->pluck('status')->all(),
        );
    }

    public function test_rollback_refuses_to_truncate_awaiting_resident_status(): void
    {
        $message = $this->message('Awaiting Resident');

        try {
            $this->migration()->down();
            $this->fail('The migration unexpectedly narrowed a column containing a longer status.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Cannot narrow contact_messages.status while values longer than 16 characters exist.',
                $exception->getMessage(),
            );
        }

        $this->assertSame('Awaiting Resident', $message->refresh()->status);
    }

    private function message(string $status): ContactMessage
    {
        return ContactMessage::query()->create([
            'name' => 'Migration Test',
            'email' => 'migration@example.test',
            'subject' => 'Status migration',
            'message' => 'Verify that status values are preserved.',
            'status' => $status,
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_24_000003_expand_contact_message_status.php');
    }
}

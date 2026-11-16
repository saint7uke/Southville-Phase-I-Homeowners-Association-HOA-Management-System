<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AnnouncementEmailDelivery;
use App\Models\ContactMessageActivity;
use App\Models\EmailDelivery;
use App\Services\WorkflowMail;
use Illuminate\Console\Command;

final class RecoverWorkflowMail extends Command
{
    protected $signature = 'hoa:recover-workflow-mail {--retry-failed= : Explicit failed delivery ID, maximum three attempts}';

    protected $description = 'Dispatch durable queued mail and flag interrupted sends without resending uncertain outcomes';

    public function handle(WorkflowMail $mail): int
    {
        EmailDelivery::query()->where('status', 'Sending')->where('started_at', '<', now()->subMinutes(15))
            ->update(['status' => 'Uncertain', 'failure_code' => 'worker_interrupted', 'updated_at' => now()]);
        AnnouncementEmailDelivery::query()->where('status', 'Sending')->where('updated_at', '<', now()->subMinutes(15))
            ->update(['status' => 'Uncertain', 'failure_code' => 'worker_interrupted', 'failed_at' => now(), 'updated_at' => now()]);
        ContactMessageActivity::query()->where('delivery_status', 'Sending')->where('updated_at', '<', now()->subMinutes(15))
            ->update(['delivery_status' => 'Uncertain', 'failure_code' => 'worker_interrupted', 'failed_at' => now(), 'updated_at' => now()]);
        if ($id = $this->option('retry-failed')) {
            $changed = EmailDelivery::query()->whereKey($id)->where('status', 'Failed')->where('attempts', '<', 3)
                ->update(['status' => 'Queued', 'failure_code' => null, 'updated_at' => now()]);
            if (! $changed) {
                $this->error('Only a Failed delivery below the attempt limit can be retried. Uncertain deliveries require manual investigation.');

                return self::FAILURE;
            }
        }
        EmailDelivery::query()->where('status', 'Queued')->orderBy('id')->chunkById(100, function ($rows) use ($mail): void {
            foreach ($rows as $row) {
                $mail->dispatch($row->id);
            }
        });
        $this->info('Queued deliveries dispatched. Inbox delivery is not confirmed.');

        return self::SUCCESS;
    }
}

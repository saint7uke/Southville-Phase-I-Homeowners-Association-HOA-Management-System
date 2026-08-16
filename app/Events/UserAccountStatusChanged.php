<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserAccountStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly UserAccountStatus $from,
        public readonly UserAccountStatus $to,
        public readonly ?string $reason,
    ) {}
}

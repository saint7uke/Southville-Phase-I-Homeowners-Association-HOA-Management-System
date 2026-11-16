<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\ContactMessages\CreateResidentInquiry;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ResidentConcerns
{
    public function listing(User $user, string $type = '', string $status = '', int $page = 1): LengthAwarePaginator
    {
        abort_unless(CreateResidentInquiry::eligible($user), 403);
        $complaints = Complaint::query()->whereHas('homeowner', fn ($query) => $query->where('user_id', $user->id))
            ->selectRaw("id, ticket_number as reference, 'Complaint' as kind, subject, status, created_at");
        $inquiries = ContactMessage::query()->where('user_id', $user->id)
            ->selectRaw("id, reference, 'Inquiry' as kind, subject, status, created_at");
        $query = DB::query()->fromSub($complaints->unionAll($inquiries), 'concerns')
            ->when(in_array($type, ['Complaint', 'Inquiry'], true), fn ($query) => $query->where('kind', $type))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')->orderBy('kind')->orderByDesc('id');

        return $query->paginate(10, ['*'], 'page', max(1, $page));
    }

    public function detail(User $user, string $key): Complaint|ContactMessage
    {
        abort_unless(CreateResidentInquiry::eligible($user), 403);
        abort_unless(preg_match('/\A(Complaint|Inquiry):([1-9][0-9]*)\z/', $key, $parts), 404);
        if ($parts[1] === 'Complaint') {
            return Complaint::whereHas('homeowner', fn ($query) => $query->where('user_id', $user->id))->with('caseAttachments')->findOrFail($parts[2]);
        }

        return ContactMessage::where('user_id', $user->id)->with(['activities' => fn ($query) => $query
            ->where('type', 'outbound_reply')->where('delivery_status', 'Sent')])->findOrFail($parts[2]);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

final class SmsText
{
    public const TEMPLATES = [
        'approval' => 'Southville Phase I HOA: Your homeowner registration has been approved. You may now access your homeowner account.',
        'due' => 'Southville Phase I HOA: Reminder! Your monthly association dues are due soon. Please settle your payment before the deadline.',
        'overdue' => 'Southville Phase I HOA: Your association dues are overdue. Please check your homeowner account for your outstanding balance.',
        'payment' => 'Southville Phase I HOA: Your payment has been verified successfully. Thank you for settling your association dues!',
        'complaint' => 'Southville Phase I HOA: Your submitted complaint has been updated. Please log in to your homeowner account to view its status.',
        'announcement' => 'Southville Phase I HOA ADVISORY: Please be informed of an important community announcement. Log in to your homeowner account for details.',
        'test' => 'Southville Phase I HOA: This is a test SMS notification.',
    ];

    public static function segments(string $body): int
    {
        $basic = '@£$¥èéùìòÇØøÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'."\n\r";
        $extended = "\f^{}\\[~]|€";
        $units = 0;
        foreach (mb_str_split($body) as $char) {
            if (str_contains($basic, $char)) {
                $units++;
            } elseif (str_contains($extended, $char)) {
                $units += 2;
            } else {
                $units = intdiv(strlen(mb_convert_encoding($body, 'UTF-16BE', 'UTF-8')), 2);

                return $units <= 70 ? 1 : (int) ceil($units / 67);
            }
        }

        return $units <= 160 ? 1 : (int) ceil($units / 153);
    }
}

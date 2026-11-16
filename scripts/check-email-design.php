<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;

// Local synthetic rendering only. This script does not send or enqueue mail.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['app.url' => 'https://southvillehoams.com', 'mail.default' => 'array']);

$source = imagecreatefrompng(public_path('images/HOA.png'));
$logo = imagecreatetruecolor(160, 160);
imagealphablending($logo, false);
imagesavealpha($logo, true);
imagecopyresampled($logo, $source, 0, 0, 0, 0, 160, 160, imagesx($source), imagesy($source));
imagepng($logo, public_path('images/hoa-email-logo.png'), 9);
imagedestroy($source);
imagedestroy($logo);

$directory = storage_path('framework/testing/email-design');
if (! is_dir($directory)) {
    mkdir($directory, 0755, true);
}
foreach (['en', 'fil'] as $locale) {
    app()->setLocale($locale);
    $mail = (new MailMessage)
        ->subject(__('Payment proof rejected'))
        ->greeting(__('Hello :name,', ['name' => 'Synthetic Resident With A Long Name']))
        ->line(__('Please review the balance and contact the HOA office to pay in cash.'))
        ->markdown('notifications::email', ['details' => [
            __('Reference') => 'SYNTHETIC-2026-0001',
            __('Status') => __('Rejected'),
            __('Reason:') => "Synthetic review note.\nPlease provide a clearer copy. [Not a link](https://invalid.example)",
        ]])
        ->action(__('View payments'), 'https://southvillehoams.com/homeowner/payments');
    $renderer = app(Markdown::class);
    file_put_contents($directory.'/'.$locale.'.html', (string) $renderer->render('notifications::email', $mail->data()));
    file_put_contents($directory.'/'.$locale.'.txt', (string) $renderer->renderText('notifications::email', $mail->data()));
}
echo "Generated synthetic EN/FIL previews; no mail sent.\n";

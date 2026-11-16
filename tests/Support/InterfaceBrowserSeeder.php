<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\DuesSetting;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

final class InterfaceBrowserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || ! str_contains((string) config('database.connections.sqlite.database'), 'hoa-alert-browser-interface-')) {
            throw new \RuntimeException('Interface fixtures require the isolated interface runner.');
        }
        $this->call(AlertBrowserSeeder::class);
        DuesSetting::create(['name' => 'Synthetic monthly dues', 'amount' => '100.00', 'frequency' => 'Monthly', 'starts_on' => now()->startOfYear(), 'due_day' => 15, 'is_active' => true]);
        $resident = User::where('email', 'resident@alerts.example.test')->sole()->homeowner;
        $admin = User::where('email', 'admin@alerts.example.test')->sole();
        $complaint = Complaint::create(['homeowner_id' => $resident->id, 'subject' => 'Picture preview fixture', 'description' => 'Synthetic private picture evidence.', 'category' => 'Others', 'status' => 'Pending', 'priority' => 'Medium']);
        foreach (['jpg', 'png'] as $extension) {
            $image = imagecreatetruecolor($extension === 'jpg' ? 120 : 240, $extension === 'jpg' ? 240 : 120);
            imagefill($image, 0, 0, imagecolorallocate($image, 10, 100, 140));
            ob_start();
            $extension === 'jpg' ? imagejpeg($image) : imagepng($image);
            $bytes = ob_get_clean();
            imagedestroy($image);
            $path = 'synthetic-concern/proof.'.$extension;
            Storage::disk('local')->put($path, $bytes);
            $complaint->caseAttachments()->create(['disk' => 'local', 'path' => $path, 'original_name' => 'Synthetic proof.'.$extension, 'mime_type' => $extension === 'jpg' ? 'image/jpeg' : 'image/png', 'size_bytes' => strlen($bytes)]);
        }
        Storage::disk('local')->put('synthetic-concern/proof.pdf', '%PDF-1.4 synthetic download fixture');
        $complaint->caseAttachments()->create(['disk' => 'local', 'path' => 'synthetic-concern/proof.pdf', 'original_name' => 'Synthetic proof.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 35]);
        foreach ([1280, 390] as $width) {
            $request = ServiceRequest::create(['homeowner_id' => $resident->id, 'request_type' => 'Certificate of Residency', 'subject' => 'Synthetic pickup '.$width, 'details' => 'Isolated browser certificate tracking fixture.', 'status' => 'Completed']);
            Certificate::create(['homeowner_id' => $resident->id, 'service_request_id' => $request->id, 'certificate_number' => 'SYNTHETIC-PICKUP-'.$width, 'type' => 'Certificate of Residency', 'status' => 'Issued', 'issued_at' => now(), 'issued_by' => $admin->id, 'purpose' => 'Synthetic pickup test']);
        }
    }
}

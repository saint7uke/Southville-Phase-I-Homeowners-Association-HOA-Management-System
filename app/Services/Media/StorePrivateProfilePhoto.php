<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Homeowner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class StorePrivateProfilePhoto
{
    /** @return array{profile_photo: string, profile_photo_disk: string, profile_photo_original_name: string, profile_photo_mime_type: string, profile_photo_size: int, profile_photo_uploaded_at: Carbon} */
    public function store(Homeowner $homeowner, UploadedFile $file): array
    {
        $mimeType = (string) $file->getMimeType();
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('Unsupported profile photo type.'),
        };

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('The profile photo could not be read.');
        }

        $image = imagecreatefromstring($contents);
        if ($image === false) {
            throw new RuntimeException('The profile photo is not a valid image.');
        }

        try {
            $image = $this->applyJpegOrientation($image, $file, $mimeType);
            $encoded = $this->encodeWithoutMetadata($image, $mimeType);
        } finally {
            imagedestroy($image);
        }

        $disk = 'local';
        $path = sprintf(
            'homeowner-profile-photos/%d/%s/%s.%s',
            $homeowner->id,
            now()->format('Y/m'),
            Str::ulid(),
            $extension,
        );

        if (! Storage::disk($disk)->put($path, $encoded)) {
            throw new RuntimeException('The profile photo could not be stored.');
        }

        return [
            'profile_photo' => $path,
            'profile_photo_disk' => $disk,
            'profile_photo_original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
            'profile_photo_mime_type' => $mimeType,
            'profile_photo_size' => strlen($encoded),
            'profile_photo_uploaded_at' => now(),
        ];
    }

    private function applyJpegOrientation(\GdImage $image, UploadedFile $file, string $mimeType): \GdImage
    {
        if ($mimeType !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($file->getRealPath());
        $degrees = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $degrees, 0);
        if ($rotated === false) {
            throw new RuntimeException('The profile photo orientation could not be corrected.');
        }

        imagedestroy($image);

        return $rotated;
    }

    private function encodeWithoutMetadata(\GdImage $image, string $mimeType): string
    {
        ob_start();

        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, null, 88),
            'image/png' => $this->encodePng($image),
            'image/webp' => imagewebp($image, null, 88),
            default => false,
        };

        $contents = ob_get_clean();
        if (! $encoded || ! is_string($contents)) {
            throw new RuntimeException('The profile photo could not be processed.');
        }

        return $contents;
    }

    private function encodePng(\GdImage $image): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng($image, null, 7);
    }
}

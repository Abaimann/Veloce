<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use RuntimeException;

class SelfieWatermarker
{
    public function rawHash(UploadedFile $uploadedFile): string
    {
        $filePath = $this->resolveFilePath($uploadedFile);
        $hash = hash_file('sha256', $filePath);

        if ($hash === false) {
            throw new RuntimeException('Gagal menghitung hash SHA-256 foto selfie.');
        }

        return $hash;
    }

    /**
     * Memberikan watermark identitas, waktu server WIB, dan koordinat lalu menyimpan ke disk privat.
     *
     * @return array{path: string, raw_hash: string}
     */
    public function watermarkAndStore(
        UploadedFile $uploadedFile,
        Employee $employee,
        CarbonInterface $serverTime,
        float $lat,
        float $lng,
    ): array {
        $filePath = $this->resolveFilePath($uploadedFile);
        $rawHash = $this->rawHash($uploadedFile);

        $manager = ImageManager::usingDriver(Driver::class);
        $image = $manager->decodePath($filePath);
        $image->orient();
        $image->scaleDown(width: (int) config('attendance.selfie_max_width', 720));

        $width = $image->width();
        $height = $image->height();
        $barHeight = min($height, min(84, max(56, (int) round($height * 0.16))));
        $barTop = $height - $barHeight;

        $image->drawRectangle(function (RectangleFactory $rect) use ($width, $barTop, $barHeight): void {
            $rect->at(0, $barTop);
            $rect->size($width, $barHeight);
            $rect->background('rgba(12, 15, 81, 0.78)');
        });

        $jakartaTime = CarbonImmutable::instance($serverTime)->timezone('Asia/Jakarta');
        $employeeName = $employee->user?->name ?? 'Karyawan';

        $line1 = "{$employeeName} ({$employee->employee_number})";
        $line2 = "{$jakartaTime->format('d M Y H:i:s')} WIB • ".number_format($lat, 5).', '.number_format($lng, 5);

        $fontPath = resource_path('fonts/PlusJakartaSans-SemiBold.ttf');
        $resolvedFontPath = is_file($fontPath) ? $fontPath : null;

        $paddingX = max(12, (int) round($width * 0.03));
        $line1Y = $barTop + (int) round($barHeight * 0.33);
        $line2Y = $barTop + (int) round($barHeight * 0.68);
        $line1Size = $resolvedFontPath !== null ? max(11, min(16, (int) round($width * 0.023))) : 3;
        $line2Size = $resolvedFontPath !== null ? max(10, min(14, (int) round($width * 0.019))) : 2;

        $this->drawTextLine($image, $line1, $paddingX, $line1Y, $line1Size, $resolvedFontPath);
        $this->drawTextLine($image, $line2, $paddingX, $line2Y, $line2Size, $resolvedFontPath);

        $encoded = (string) $image->encodeUsingFileExtension(
            'jpg',
            quality: (int) config('attendance.selfie_jpeg_quality', 75),
        );

        $path = sprintf(
            'selfies/%s/%s/%s.jpg',
            $jakartaTime->format('Y'),
            $jakartaTime->format('m'),
            Str::uuid()->toString(),
        );

        Storage::disk((string) config('attendance.private_disk', 'local'))->put($path, $encoded);

        return [
            'path' => $path,
            'raw_hash' => $rawHash,
        ];
    }

    private function drawTextLine(
        ImageInterface $image,
        string $text,
        int $x,
        int $y,
        float $size,
        ?string $fontPath,
    ): void {
        $image->text($text, $x, $y, function (FontFactory $font) use ($size, $fontPath): void {
            if ($fontPath !== null) {
                $font->filename($fontPath);
            }

            $font->size($size)
                ->color('#FFFFFF')
                ->align('left', 'center');
        });
    }

    private function resolveFilePath(UploadedFile $uploadedFile): string
    {
        $realPath = $uploadedFile->getRealPath();

        if (is_string($realPath) && $realPath !== '') {
            return $realPath;
        }

        return $uploadedFile->getPathname();
    }
}

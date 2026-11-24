<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

test('file tema single-source tersedia dan tidak ada warna hardcode di seluruh view blade', function (): void {
    $themePath = resource_path('css/theme.css');
    expect(File::exists($themePath))->toBeTrue();

    $themeCss = File::get($themePath);
    expect($themeCss)
        ->toContain('--brand-navy:')
        ->toContain('--brand-ink:')
        ->toContain('--brand-green:')
        ->toContain('--brand-green-strong:');

    $bladeFiles = File::allFiles(resource_path('views'));
    expect($bladeFiles)->not->toBeEmpty();

    foreach ($bladeFiles as $file) {
        $content = File::get($file->getPathname());
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

        expect($content)->not->toMatch('/#[0-9a-fA-F]{3,8}\b/', "Warna hex ditemukan di {$relative}");
        expect($content)->not->toMatch('/\b(?:rgb|hsl)a?\s*\(/i', "Warna rgb/hsl ditemukan di {$relative}");
        expect($content)->not->toMatch(
            '/\b(?:bg|text|border|from|to|via|ring)-(?:white|black|slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|purple|fuchsia|pink|rose)(?:-\d+)?\b/',
            "Kelas palet default Tailwind ditemukan di {$relative}",
        );
    }
});

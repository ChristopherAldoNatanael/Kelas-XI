<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\Pet;
use App\Models\User;
use App\Support\ImageVariant;
use Illuminate\Console\Command;

class GenerateImageThumbnails extends Command
{
    protected $signature = 'media:thumbnails
        {--width=96,192,320 : Lebar thumbnail, dipisah koma}
        {--dry-run : Hitung saja tanpa membuat berkas}';

    protected $description = 'Buat thumbnail WebP untuk foto dokter, hewan, dan pengguna';

    public function handle(): int
    {
        $widths = collect(explode(',', (string) $this->option('width')))
            ->map(fn ($value) => (int) trim($value))
            ->filter(fn ($value) => $value >= 48 && $value <= 640)
            ->unique()
            ->values();

        if ($widths->isEmpty()) {
            $this->error('Tidak ada lebar valid (48–640 px).');

            return self::FAILURE;
        }

        $models = [
            'doctor' => Doctor::query()->whereNotNull('photo')->select('id', 'photo')->cursor(),
            'pet' => Pet::query()->whereNotNull('photo')->select('id', 'photo')->cursor(),
            'user' => User::query()->whereNotNull('photo')->select('id', 'photo')->cursor(),
        ];

        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $failed = 0;

        foreach ($models as $type => $rows) {
            foreach ($rows as $row) {
                foreach ($widths as $width) {
                    if ($dryRun) {
                        $this->line("would generate {$type}#{$row->id} {$width}px");
                        continue;
                    }

                    try {
                        if (ImageVariant::url($row->photo, $width)) {
                            $created++;
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->warn("gagal {$type}#{$row->id} {$width}px: {$e->getMessage()}");
                    }
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry run selesai.');

            return self::SUCCESS;
        }

        $this->info("Thumbnail diproses: {$created}, gagal: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}

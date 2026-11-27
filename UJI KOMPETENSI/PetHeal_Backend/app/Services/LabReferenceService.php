<?php

namespace App\Services;

use App\Models\LabReferenceRange;
use App\Models\LabResult;
use App\Models\Pet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pencocokan rentang rujukan laboratorium.
 *
 * Rentang hanya dipakai bila cocok dengan klinik, spesies, jenis kelamin,
 * umur, metode, analyzer, dan jenis spesimen. Bila tidak ada yang cocok,
 * hasil ditandai `reference_unavailable` dan tidak diberi nilai normal palsu.
 */
class LabReferenceService
{
    public function resolve(
        Pet $pet,
        int $clinicId,
        string $testCode,
        string $parameter,
        ?string $method = null,
        ?string $analyzer = null,
        ?string $specimenType = null
    ): array {
        $today = today();

        $candidates = LabReferenceRange::query()
            ->forClinic($clinicId)
            ->active()
            ->where('species', $pet->species)
            ->where('test_code', $testCode)
            ->where('parameter', $parameter)
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->get();

        $matched = $candidates
            ->filter(fn (LabReferenceRange $range) => $this->matches(
                $range,
                $pet,
                $method,
                $analyzer,
                $specimenType
            ))
            ->sort(fn (LabReferenceRange $a, LabReferenceRange $b) => [
                $this->specificity($b),
                optional($b->effective_from)->timestamp ?? 0,
                $b->id,
            ] <=> [
                $this->specificity($a),
                optional($a->effective_from)->timestamp ?? 0,
                $a->id,
            ])
            ->first();

        if (! $matched) {
            return [
                'reference_range_id' => null,
                'reference_low' => null,
                'reference_high' => null,
                'reference_text' => null,
                'reference_range' => null,
                'unit' => null,
                'reference_status' => LabResult::REFERENCE_UNAVAILABLE,
                'flag' => null,
            ];
        }

        return $this->evaluate($matched, $pet);
    }

    public function evaluate(LabReferenceRange $range, Pet $pet): array
    {
        $low = $range->reference_low !== null ? (float) $range->reference_low : null;
        $high = $range->reference_high !== null ? (float) $range->reference_high : null;
        $text = $range->reference_text;
        $unit = $range->unit;

        return [
            'reference_range_id' => $range->id,
            'reference_low' => $low,
            'reference_high' => $high,
            'reference_text' => $text,
            'reference_range' => $text ?: $this->rangeText($low, $high, $unit),
            'unit' => $unit,
            'reference_status' => LabResult::REFERENCE_MATCHED,
            'flag' => null,
        ];
    }

    public function flagForValue(mixed $value, ?float $low, ?float $high): ?string
    {
        if ($value === null || $value === '' || $low === null && $high === null) {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $value));

        if (! is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;

        if ($low !== null && $number < $low) {
            return 'low';
        }

        if ($high !== null && $number > $high) {
            return 'high';
        }

        return 'normal';
    }

    private function matches(
        LabReferenceRange $range,
        Pet $pet,
        ?string $method,
        ?string $analyzer,
        ?string $specimenType
    ): bool {
        if (! $this->matchesText($range->breed, $pet->breed, true)) {
            return false;
        }

        if (! $this->matchesSex($range->sex, $pet->gender)) {
            return false;
        }

        $ageDays = $this->ageInDays($pet);

        if ($range->age_min_days !== null && ($ageDays === null || $ageDays < $range->age_min_days)) {
            return false;
        }

        if ($range->age_max_days !== null && ($ageDays === null || $ageDays > $range->age_max_days)) {
            return false;
        }

        if (! $this->matchesText($range->method, $method)) {
            return false;
        }

        if (! $this->matchesText($range->analyzer, $analyzer)) {
            return false;
        }

        if (! $this->matchesText($range->specimen_type, $specimenType)) {
            return false;
        }

        return true;
    }

    private function matchesText(?string $rangeValue, ?string $actual, bool $contains = false): bool
    {
        if ($rangeValue === null || trim($rangeValue) === '') {
            return true;
        }

        if ($actual === null || trim($actual) === '') {
            return false;
        }

        $left = mb_strtolower(trim($rangeValue));
        $right = mb_strtolower(trim($actual));

        return $contains
            ? str_contains($right, $left) || str_contains($left, $right)
            : $left === $right;
    }

    private function matchesSex(?string $rangeSex, ?string $petSex): bool
    {
        if ($rangeSex === null || trim($rangeSex) === '') {
            return true;
        }

        if ($petSex === null || trim($petSex) === '') {
            return false;
        }

        $map = [
            'male' => ['male', 'm', 'jantan', 'laki-laki'],
            'female' => ['female', 'f', 'betina', 'perempuan'],
            'neutered_male' => ['neutered_male', 'male_neutered', 'kastrasi', 'kastrasi_jantan'],
            'spayed_female' => ['spayed_female', 'female_spayed', 'steril', 'steril_betina'],
        ];

        $normalize = fn (string $value) => mb_strtolower(trim($value));
        $range = $normalize($rangeSex);
        $pet = $normalize($petSex);

        if ($range === $pet) {
            return true;
        }

        foreach ($map as $values) {
            if (in_array($range, $values, true) && in_array($pet, $values, true)) {
                return true;
            }
        }

        return false;
    }

    private function ageInDays(Pet $pet): ?int
    {
        if ($pet->date_of_birth) {
            return max(0, Carbon::parse($pet->date_of_birth)->diffInDays(today()));
        }

        if ($pet->age !== null && $pet->age !== '') {
            return max(0, (int) $pet->age * 365);
        }

        return null;
    }

    private function specificity(LabReferenceRange $range): int
    {
        $score = 0;

        foreach (['breed', 'sex', 'age_min_days', 'age_max_days', 'method', 'analyzer', 'specimen_type'] as $field) {
            if ($range->{$field} !== null && $range->{$field} !== '') {
                $score++;
            }
        }

        return $score;
    }

    private function rangeText(?float $low, ?float $high, ?string $unit): ?string
    {
        if ($low === null && $high === null) {
            return null;
        }

        $format = fn (float $value) => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

        $text = match (true) {
            $low !== null && $high !== null => $format($low) . ' - ' . $format($high),
            $low !== null => '>= ' . $format($low),
            default => '<= ' . $format((float) $high),
        };

        return trim($text . ' ' . $unit);
    }
}

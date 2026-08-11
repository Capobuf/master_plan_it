<?php

namespace App\Domain\MasterData\Data;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class CreatePlanningYearData
{
    public function __construct(public int $yearLabel)
    {
        Validator::make(['year_label' => $yearLabel], [
            'year_label' => ['required', 'integer', 'between:1000,9999'],
        ])->validate();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromImportedRow(array $row): self
    {
        $allowedFields = ['year_label', 'start_date', 'end_date'];
        $unexpectedFields = array_values(array_diff(array_keys($row), $allowedFields));

        if ($unexpectedFields !== []) {
            throw ValidationException::withMessages(
                array_fill_keys($unexpectedFields, 'This field is not allowed for planning-year import.'),
            );
        }

        $values = Validator::make($row, [
            'year_label' => ['required', 'integer', 'between:1000,9999'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();

        $data = new self((int) $values['year_label']);
        $hasBoundary = array_key_exists('start_date', $row) || array_key_exists('end_date', $row);

        if (! $hasBoundary) {
            return $data;
        }

        $errors = [];
        if (($values['start_date'] ?? null) !== $data->startDate()) {
            $errors['start_date'] = 'The planning year start date must be the derived January 1 boundary.';
        }
        if (($values['end_date'] ?? null) !== $data->endDate()) {
            $errors['end_date'] = 'The planning year end date must be the derived December 31 boundary.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    public function startDate(): string
    {
        return sprintf('%04d-01-01', $this->yearLabel);
    }

    public function endDate(): string
    {
        return sprintf('%04d-12-31', $this->yearLabel);
    }
}

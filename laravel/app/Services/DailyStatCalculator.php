<?php

namespace App\Services;

class DailyStatCalculator
{
    /**
     * @return array{population_change: int|null, days_without_change: int} days_without_change ukladaj na model Village
     */
    public static function compute(
        ?int $previousPopulation,
        int $currentPopulation,
        ?int $previousDaysWithoutChange
    ): array {
        if ($previousPopulation === null) {
            return [
                'population_change' => null,
                'days_without_change' => 0,
            ];
        }

        $populationChange = $currentPopulation - $previousPopulation;

        if ($currentPopulation === $previousPopulation) {
            $days = ($previousDaysWithoutChange ?? 0) + 1;
        } else {
            $days = 0;
        }

        return [
            'population_change' => $populationChange,
            'days_without_change' => $days,
        ];
    }
}

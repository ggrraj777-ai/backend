<?php

if (!function_exists('isNighttime')) {
    /**
     * Check if current time is within nighttime hours
     * 
     * @param string|null $currentTime Time in H:i format (optional, defaults to now)
     * @return bool
     */
    function isNighttime(?string $currentTime = null): bool
    {
        // Check if nighttime fare is enabled
        $enabled = businessConfig('nighttime_fare_status')?->value ?? 0;
        if (!$enabled) {
            return false;
        }

        $currentTime = $currentTime ?? date('H:i');
        $nightStart = businessConfig('nighttime_start_time')?->value ?? '22:00';
        $nightEnd = businessConfig('nighttime_end_time')?->value ?? '06:00';

        // Convert to comparable format
        $current = strtotime($currentTime);
        $start = strtotime($nightStart);
        $end = strtotime($nightEnd);

        // Handle overnight period (e.g., 22:00 PM to 06:00 AM)
        if ($start > $end) {
            // Nighttime crosses midnight
            return ($current >= $start) || ($current < $end);
        } else {
            // Nighttime is within same day
            return ($current >= $start) && ($current < $end);
        }
    }
}

if (!function_exists('getNighttimeFarePercentage')) {
    /**
     * Get nighttime fare increase percentage
     * 
     * @return float
     */
    function getNighttimeFarePercentage(): float
    {
        if (!isNighttime()) {
            return 0;
        }

        return (float)(businessConfig('nighttime_fare_percentage')?->value ?? 20);
    }
}

if (!function_exists('applyNighttimeFare')) {
    /**
     * Apply nighttime fare hike to base fare
     * 
     * @param float $baseFare Original fare amount
     * @param string|null $currentTime Optional time to check (defaults to now)
     * @return array ['fare' => adjusted fare, 'nighttime_applied' => bool, 'nighttime_amount' => increase]
     */
    function applyNighttimeFare(float $baseFare, ?string $currentTime = null): array
    {
        if (!isNighttime($currentTime)) {
            return [
                'fare' => $baseFare,
                'nighttime_applied' => false,
                'nighttime_amount' => 0,
                'nighttime_percentage' => 0,
            ];
        }

        $percentage = getNighttimeFarePercentage();
        $nighttimeAmount = ($baseFare * $percentage) / 100;
        $totalFare = $baseFare + $nighttimeAmount;

        return [
            'fare' => $totalFare,
            'nighttime_applied' => true,
            'nighttime_amount' => round($nighttimeAmount, 2),
            'nighttime_percentage' => $percentage,
        ];
    }
}

if (!function_exists('getNighttimeFareDetails')) {
    /**
     * Get nighttime fare configuration details
     * 
     * @return array
     */
    function getNighttimeFareDetails(): array
    {
        return [
            'enabled' => (bool)(businessConfig('nighttime_fare_status')?->value ?? 0),
            'start_time' => businessConfig('nighttime_start_time')?->value ?? '22:00',
            'end_time' => businessConfig('nighttime_end_time')?->value ?? '06:00',
            'percentage' => (float)(businessConfig('nighttime_fare_percentage')?->value ?? 20),
            'is_nighttime_now' => isNighttime(),
        ];
    }
}


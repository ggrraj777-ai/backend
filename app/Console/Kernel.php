<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('trip-request:cancel')->everyMinute();
        
        // Process daily fee deductions at 11:59 PM every day
        $schedule->command('driver:process-daily-fees')
            ->dailyAt('23:59')
            ->timezone('Asia/Kolkata')
            ->onSuccess(function () {
                \Log::info('Daily fee processing completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Daily fee processing failed');
            });
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

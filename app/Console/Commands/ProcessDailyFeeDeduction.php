<?php

namespace App\Console\Commands;

use App\Services\DailyFeeDeductionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessDailyFeeDeduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'driver:process-daily-fees {--date= : Process for specific date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process daily fee deductions for drivers based on trip targets';

    private DailyFeeDeductionService $feeService;

    /**
     * Create a new command instance.
     */
    public function __construct(DailyFeeDeductionService $feeService)
    {
        parent::__construct();
        $this->feeService = $feeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        
        $this->info("========================================");
        $this->info("  GAUVA Daily Fee Processing");
        $this->info("========================================");
        $this->info("");
        $this->info("Processing date: " . $date->format('Y-m-d'));
        $this->info("");

        $this->info("Fetching driver activities...");
        $results = $this->feeService->processAllDrivers($date);

        $this->info("");
        $this->info("========================================");
        $this->info("  Processing Summary");
        $this->info("========================================");
        $this->table(
            ['Metric', 'Count/Amount'],
            [
                ['Total Drivers', $results['total_drivers']],
                ['Fees Deducted', $results['fees_deducted']],
                ['Free Access Achieved', $results['free_access']],
                ['Welcome Period', $results['welcome_period']],
                ['No Activity', $results['no_activity']],
                ['Insufficient Balance', $results['insufficient_balance']],
                ['Total Amount Deducted', '₹' . number_format($results['total_amount_deducted'], 2)],
            ]
        );

        if (count($results['errors']) > 0) {
            $this->error("");
            $this->error("Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->error("  Driver {$error['driver_id']}: {$error['error']}");
            }
        }

        $this->info("");
        
        if ($results['fees_deducted'] > 0 || $results['free_access'] > 0) {
            $this->info("✅ Processing completed successfully!");
        } else {
            $this->warn("⚠️  No fees processed. Check if drivers had any activity.");
        }

        return Command::SUCCESS;
    }
}


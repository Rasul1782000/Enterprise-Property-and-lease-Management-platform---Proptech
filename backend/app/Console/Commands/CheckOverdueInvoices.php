<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'invoices:overdue-check';
    protected $description = 'Mark pending invoices past due_date as overdue and apply late fee';

    public function handle(): int
    {
        $graceDays = (int) env('INVOICE_OVERDUE_GRACE_DAYS', 3);
        $cutoff = now()->subDays($graceDays)->toDateString();

        $invoices = Invoice::where('status','pending')
            ->where('due_date','<', $cutoff)
            ->with('lease')
            ->get();

        foreach ($invoices as $inv) {
            $latePercent = $inv->lease->late_fee_percent ?? (float) env('LATE_FEE_PERCENT', 2.5);
            $lateFee = round($inv->amount * $latePercent / 100, 2);
            $inv->update([
                'status' => 'overdue',
                'late_fee' => $lateFee,
                'total_amount' => $inv->amount + $lateFee,
            ]);
            $this->line("Marked overdue {$inv->invoice_number} + late fee \${$lateFee}");
        }

        $this->info("Checked overdue. Updated: {$invoices->count()}");
        return self::SUCCESS;
    }
}

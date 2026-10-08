<?php

namespace App\Console\Commands;

use App\Mail\RentInvoiceMail;
use App\Models\Invoice;
use App\Models\Lease;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate {--test-date= : YYYY-MM-DD for manual testing} {--dry-run : simulate without creating}';

    protected $description = 'Generate monthly rent invoices for all active leases (run daily via scheduler, creates once per month on 1st)';

    public function handle(): int
    {
        $today = $this->option('test-date') ? Carbon::parse($this->option('test-date')) : now();
        $periodStart = $today->copy()->startOfMonth();
        $periodEnd = $today->copy()->endOfMonth();
        $dueDay = (int) config('app.invoice_due_day', env('INVOICE_DUE_DAY', 5));
        $dueDate = $periodStart->copy()->day(min($dueDay, $periodStart->daysInMonth));

        $this->info("Generating invoices for period {$periodStart->toDateString()} - {$periodEnd->toDateString()} (due {$dueDate->toDateString()})");

        $leases = Lease::with(['tenant', 'unit'])
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->get();

        $created = 0;
        $skipped = 0;
        $dryRun = $this->option('dry-run');

        foreach ($leases as $lease) {
            $exists = Invoice::where('lease_id', $lease->id)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line("[DRY] Would create invoice for lease {$lease->lease_number} - {$lease->tenant->full_name} - \${$lease->rent_amount}");
                $created++;

                continue;
            }

            $invoice = Invoice::create([
                'lease_id' => $lease->id,
                'tenant_id' => $lease->tenant_id,
                'unit_id' => $lease->unit_id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'amount' => $lease->rent_amount,
                'late_fee' => 0,
                'total_amount' => $lease->rent_amount,
                'status' => 'pending',
            ]);


            try {
                Mail::to($lease->tenant->email)->send(new RentInvoiceMail($invoice));
                $invoice->update(['sent_at' => now()]);
            } catch (\Throwable $e) {
                $this->warn("Invoice {$invoice->invoice_number} created but email failed: ".$e->getMessage());
            }

            $created++;
            $this->line("Created {$invoice->invoice_number} for {$lease->lease_number}");
        }

        $this->info("Done. Created: {$created}, Skipped (already exists): {$skipped}, Total active leases: {$leases->count()}");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\LeaseExpiryMail;
use App\Models\Lease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyLeaseExpiry extends Command
{
    protected $signature = 'leases:expiry-notify {--days=30 : Notify leases expiring within N days}';
    protected $description = 'Send email notifications for leases expiring soon';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $leases = Lease::with(['tenant','unit.building.property'])
            ->where('status','active')
            ->whereBetween('end_date', [now(), now()->addDays($days)])
            ->get();

        foreach ($leases as $lease) {
            try {
                Mail::to($lease->tenant->email)->send(new LeaseExpiryMail($lease));
                $this->line("Notified {$lease->tenant->email} for lease {$lease->lease_number} expiring {$lease->end_date->toDateString()}");
            } catch (\Throwable $e) {
                $this->warn("Failed for {$lease->lease_number}: ".$e->getMessage());
            }
        }

        $this->info("Expiry notifications sent for {$leases->count()} leases.");
        return self::SUCCESS;
    }
}

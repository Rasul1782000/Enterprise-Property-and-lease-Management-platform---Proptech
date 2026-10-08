<?php

namespace App\Services\Metrics;

use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BusinessMetricsCollector
{
    public function __construct(private MetricsRegistry $registry) {}

    public function collect(): void
    {
        if (! $this->shouldRefresh()) {
            return;
        }

        try {
            $this->refresh();
        } catch (Throwable $e) {


            Log::warning('Failed to collect business metrics.', ['error' => $e->getMessage()]);
        }
    }

    private function refresh(): void
    {
        $this->registry->recordCount('properties', Property::query()->count());
        $this->registry->recordCount('tenants', Tenant::query()->count());

        $this->recordUnits();
        $this->recordLeases();
        $this->recordInvoices();

        $this->registry->recordCount('payments', Payment::query()->count());
    }

    private function recordUnits(): void
    {
        $units = Unit::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $this->registry->recordCountByStatus('units', $units);

        $total = array_sum($units);
        $occupied = (int) ($units['occupied'] ?? 0);

        $this->registry->recordGauge(
            'occupancy_ratio',
            'Share of units that are currently occupied, between 0 and 1.',
            $total > 0 ? $occupied / $total : 0.0
        );

        $this->registry->recordGauge(
            'units_total_unlabelled',
            'Total number of units.',
            (float) $total
        );
    }

    private function recordLeases(): void
    {
        $leases = Lease::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $this->registry->recordCountByStatus('leases', $leases);

        $expiring = Lease::query()
            ->where('status', 'active')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->count();

        $this->registry->recordGauge(
            'leases_expiring_soon',
            'Active leases ending within the next 30 days.',
            (float) $expiring
        );




        $monthlyRent = Lease::query()
            ->where('status', 'active')
            ->sum('rent_amount');

        $this->registry->recordGauge(
            'leases_active_monthly_rent',
            'Total monthly rent across all active leases.',
            (float) $monthlyRent
        );
    }

    private function recordInvoices(): void
    {
        $invoices = Invoice::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $this->registry->recordCountByStatus('invoices', $invoices);



        $byStatus = fn (string $status) => (float) (Invoice::query()
            ->where('status', $status)
            ->sum('total_amount'));

        $this->registry->recordGauge(
            'invoices_overdue_amount',
            'Outstanding value of invoices that are past their due date.',
            $byStatus('overdue')
        );

        $this->registry->recordGauge(
            'invoices_pending_amount',
            'Outstanding value of invoices that are not yet overdue.',
            $byStatus('pending')
        );

        $this->registry->recordGauge(
            'invoices_paid_amount',
            'Value of invoices that have been settled in full.',
            $byStatus('paid')
        );
    }


    private function shouldRefresh(): bool
    {
        $ttl = (int) config('prometheus.business_metrics_ttl', 60);

        if ($ttl <= 0) {
            return true;
        }



        return Cache::add('prometheus:business-metrics:lock', 1, $ttl);
    }
}

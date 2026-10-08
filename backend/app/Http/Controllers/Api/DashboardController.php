<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $unitCounts = Unit::select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $leaseCounts = Lease::select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $invoiceCounts = Invoice::select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $collectedThisMonth = (float) DB::table('payments')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $collectedLastMonth = (float) DB::table('payments')
            ->whereBetween('paid_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])
            ->sum('amount');

        $outstanding = (float) Invoice::whereIn('status', ['sent', 'partial', 'overdue'])
            ->selectRaw('coalesce(sum(total_amount), 0) - coalesce((select sum(amount) from payments where payments.invoice_id = invoices.id), 0) as due')
            ->value('due');

        return response()->json([
            'properties' => [
                'total' => Property::count(),
                'active' => Property::where('status', 'active')->count(),
                'inactive' => Property::where('status', '!=', 'active')->count(),
            ],
            'buildings' => [
                'total' => Building::count(),
                'active' => Building::where('status', 'active')->count(),
                'under_maintenance' => Building::where('status', 'under_maintenance')->count(),
            ],
            'units' => [
                'total' => (int) $unitCounts->sum(),
                'occupied' => (int) ($unitCounts['occupied'] ?? 0),
                'vacant' => (int) ($unitCounts['vacant'] ?? 0),
                'reserved' => (int) ($unitCounts['reserved'] ?? 0),
            ],
            'tenants' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('status', 'active')->count(),
                'prospects' => Tenant::where('status', 'prospect')->count(),
            ],
            'leases' => [
                'total' => (int) $leaseCounts->sum(),
                'active' => (int) ($leaseCounts['active'] ?? 0),
                'expiring_30' => Lease::expiringSoon(30)->count(),
                'expiring_90' => Lease::expiringSoon(90)->count(),
                'expired' => (int) ($leaseCounts['expired'] ?? 0),
            ],
            'invoices' => [
                'total' => (int) $invoiceCounts->sum(),
                'paid' => (int) ($invoiceCounts['paid'] ?? 0),
                'pending' => (int) (($invoiceCounts['sent'] ?? 0) + ($invoiceCounts['draft'] ?? 0)),
                'overdue' => (int) ($invoiceCounts['overdue'] ?? 0),
                'total_outstanding' => $outstanding,
            ],
            'revenue' => [
                'this_month' => $collectedThisMonth,
                'last_month' => $collectedLastMonth,
                'ytd' => (float) DB::table('payments')->whereYear('paid_at', now()->year)->sum('amount'),
                'outstanding' => $outstanding,
            ],
        ]);
    }

    public function revenueTrend(Request $request)
    {
        $months = $request->integer('months', 6);
        abort_if($months < 1 || $months > 24, 422, 'The months parameter must be between 1 and 24.');

        $collected = $this->monthlyTotals(
            Payment::query()->where('paid_at', '>=', now()->subMonths($months)->startOfMonth()),
            'paid_at',
            'amount'
        );

        $invoiced = $this->monthlyTotals(
            Invoice::query()->where('created_at', '>=', now()->subMonths($months)->startOfMonth()),
            'created_at',
            'total_amount'
        );

        return response()->json(['collected' => $collected, 'invoiced' => $invoiced]);
    }

    private function monthlyTotals($query, string $dateColumn, string $amountColumn): array
    {
        return $query->get([$dateColumn, $amountColumn])
            ->groupBy(fn ($row) => $row->{$dateColumn}->format('Y-m'))
            ->map(fn ($group, $month) => [
                'month' => $month,
                'total' => (float) $group->sum($amountColumn),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    public function expiringLeases()
    {
        $leases = Lease::with(['tenant', 'unit.building.property'])
            ->expiringSoon(60)->orderBy('end_date')->limit(10)->get();

        return response()->json($leases);
    }

    public function occupancyChart(Request $request)
    {
        $months = $request->integer('months', 12);
        abort_if($months < 1 || $months > 24, 422, 'The months parameter must be between 1 and 24.');

        $labels = [];
        $occupied = [];
        $vacant = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('Y-m');

            $counts = Unit::where('created_at', '<=', $date)
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $occupied[] = (int) ($counts['occupied'] ?? 0);
            $vacant[] = (int) ($counts['vacant'] ?? 0);
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Occupied', 'data' => $occupied],
                ['label' => 'Vacant', 'data' => $vacant],
            ],
        ]);
    }

    public function revenueChart(Request $request)
    {
        $months = $request->integer('months', 12);
        abort_if($months < 1 || $months > 24, 422, 'The months parameter must be between 1 and 24.');

        $collectedByMonth = Payment::query()
            ->where('paid_at', '>=', now()->subMonths($months)->startOfMonth())
            ->get(['paid_at', 'amount'])
            ->groupBy(fn ($payment) => $payment->paid_at->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('amount'));

        $outstandingByMonth = Invoice::query()
            ->whereIn('status', ['sent', 'partial', 'overdue'])
            ->where('due_date', '>=', now()->subMonths($months)->startOfMonth())
            ->get(['due_date', 'total_amount'])
            ->groupBy(fn ($invoice) => $invoice->due_date->format('Y-m'))
            ->map(fn ($group) => (float) $group->sum('total_amount'));

        $labels = [];
        $collected = [];
        $outstanding = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $labels[] = $key;
            $collected[] = (float) ($collectedByMonth[$key] ?? 0);
            $outstanding[] = (float) ($outstandingByMonth[$key] ?? 0);
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Collected', 'data' => $collected],
                ['label' => 'Outstanding', 'data' => $outstanding],
            ],
        ]);
    }

    public function expiringLeasesChart(Request $request)
    {
        $months = $request->integer('months', 6);
        abort_if($months < 1 || $months > 24, 422, 'The months parameter must be between 1 and 24.');

        $labels = [];
        $expiring = [];

        for ($i = 0; $i < $months; $i++) {
            $date = now()->addMonths($i);
            $labels[] = $date->format('Y-m');
            $expiring[] = Lease::where('status', 'active')
                ->whereYear('end_date', $date->year)
                ->whereMonth('end_date', $date->month)
                ->count();
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Leases expiring', 'data' => $expiring],
            ],
        ]);
    }

    public function recentActivity(Request $request)
    {
        $limit = $request->integer('limit', 10);
        abort_if($limit < 1 || $limit > 50, 422, 'The limit parameter must be between 1 and 50.');
        $activities = [];

        $recentLeases = Lease::with(['tenant', 'unit'])
            ->latest()->limit($limit)->get()
            ->map(function ($lease) use (&$activities) {
                $activities[] = [
                    'id' => $lease->id,
                    'type' => $lease->status === 'active' ? 'lease_created' : 'lease_expiring',
                    'title' => $lease->lease_number,
                    'description' => 'Lease for '.($lease->tenant ? $lease->tenant->first_name.' '.$lease->tenant->last_name : 'N/A'),
                    'timestamp' => $lease->created_at->toDateTimeString(),
                    'related_id' => $lease->id,
                    'related_type' => 'lease',
                ];
            });

        $recentPayments = Payment::with(['tenant', 'invoice'])
            ->latest()->limit($limit)->get()
            ->map(function ($payment) use (&$activities) {
                $activities[] = [
                    'id' => $payment->id,
                    'type' => 'payment_received',
                    'title' => $payment->payment_number,
                    'description' => 'Payment of '.$payment->amount.' from '.($payment->tenant ? $payment->tenant->first_name.' '.$payment->tenant->last_name : 'N/A'),
                    'timestamp' => $payment->created_at->toDateTimeString(),
                    'related_id' => $payment->invoice_id,
                    'related_type' => 'invoice',
                ];
            });

        $recentInvoices = Invoice::with(['tenant'])
            ->latest()->limit($limit)->get()
            ->map(function ($invoice) use (&$activities) {
                $activities[] = [
                    'id' => $invoice->id,
                    'type' => $invoice->status === 'overdue' ? 'invoice_overdue' : 'lease_created',
                    'title' => $invoice->invoice_number,
                    'description' => 'Invoice for '.($invoice->tenant ? $invoice->tenant->first_name.' '.$invoice->tenant->last_name : 'N/A'),
                    'timestamp' => $invoice->created_at->toDateTimeString(),
                    'related_id' => $invoice->id,
                    'related_type' => 'invoice',
                ];
            });

        usort($activities, fn ($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));

        return response()->json(array_slice($activities, 0, $limit));
    }

    public function propertyPerformance(Property $property)
    {
        $totalUnits = $property->units()->count();
        $occupiedUnits = $property->units()->where('status', 'occupied')->count();
        $occupancyRate = $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100, 2) : 0;

        $activeLeases = $property->units()->whereHas('activeLease')->count();
        $totalRevenue = Invoice::whereHas('unit.building', fn ($q) => $q->where('property_id', $property->id))
            ->where('status', 'paid')->sum('total_amount');
        $overdueAmount = Invoice::whereHas('unit.building', fn ($q) => $q->where('property_id', $property->id))
            ->where('status', 'overdue')->sum('total_amount');

        return response()->json([
            'property_id' => $property->id,
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'occupancy_rate' => $occupancyRate,
            'active_leases' => $activeLeases,
            'total_revenue' => $totalRevenue,
            'overdue_amount' => $overdueAmount,
        ]);
    }
}

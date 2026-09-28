<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats()
    {
        $totalProperties = Property::count();
        $totalUnits = Unit::count();
        $occupied = Unit::where('status', 'occupied')->count();
        $vacant = Unit::where('status', 'vacant')->count();
        $occupancy = $totalUnits ? round($occupied / $totalUnits * 100, 2) : 0;

        $activeLeases = Lease::where('status', 'active')->count();
        $expiringSoon = Lease::expiringSoon(30)->count();

        $overdueInvoices = Invoice::where('status', 'overdue')->count();
        $pendingAmount = Invoice::where('status', 'pending')->sum('total_amount');
        $overdueAmount = Invoice::where('status', 'overdue')->sum('total_amount');
        $collectedThisMonth = DB::table('payments')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return response()->json([
            'properties' => $totalProperties,
            'units' => ['total' => $totalUnits, 'occupied' => $occupied, 'vacant' => $vacant, 'occupancy_rate' => $occupancy],
            'leases' => ['active' => $activeLeases, 'expiring_soon' => $expiringSoon],
            'invoices' => ['overdue_count' => $overdueInvoices, 'pending_amount' => $pendingAmount, 'overdue_amount' => $overdueAmount],
            'revenue' => ['collected_this_month' => $collectedThisMonth],
        ]);
    }

    public function revenueTrend(Request $request)
    {
        $months = $request->integer('months', 6);
        abort_if($months < 1 || $months > 24, 422, 'The months parameter must be between 1 and 24.');
        $trend = DB::table('payments')
            ->select(DB::raw("DATE_FORMAT(paid_at,'%Y-%m') as month"), DB::raw('SUM(amount) as total'))
            ->where('paid_at', '>=', now()->subMonths($months))
            ->groupBy('month')->orderBy('month')->get();

        $invoiced = Invoice::select(DB::raw("DATE_FORMAT(created_at,'%Y-%m') as month"), DB::raw('SUM(total_amount) as total'))
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('month')->orderBy('month')->get();

        return response()->json(['collected' => $trend, 'invoiced' => $invoiced]);
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
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $totalUnits = Unit::where('created_at', '<=', $date)->count();
            $occupiedUnits = Unit::where('status', 'occupied')
                ->where('created_at', '<=', $date)->count();
            $rate = $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100, 2) : 0;
            $data[] = [
                'month' => $date->format('Y-m'),
                'occupancy_rate' => $rate,
                'total_units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
            ];
        }

        return response()->json($data);
    }

    public function revenueChart(Request $request)
    {
        $months = $request->integer('months', 12);
        abort_if($months < 1 || $months > 24, 422, 'The months parameter must be between 1 and 24.');
        $collected = DB::table('payments')
            ->select(DB::raw("DATE_FORMAT(paid_at,'%Y-%m') as month"), DB::raw('SUM(amount) as total'))
            ->where('paid_at', '>=', now()->subMonths($months))
            ->groupBy('month')->orderBy('month')->get();

        $invoiced = Invoice::select(DB::raw("DATE_FORMAT(created_at,'%Y-%m') as month"), DB::raw('SUM(total_amount) as total'))
            ->where('created_at', '>=', now()->subMonths($months))
            ->groupBy('month')->orderBy('month')->get();

        $labels = [];
        $collectedData = [];
        $invoicedData = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $labels[] = $date->format('Y-m');
            $collectedData[] = $collected->where('month', $date->format('Y-m'))->first()->total ?? 0;
            $invoicedData[] = $invoiced->where('month', $date->format('Y-m'))->first()->total ?? 0;
        }

        return response()->json([
            'labels' => $labels,
            'collected' => $collectedData,
            'invoiced' => $invoicedData,
        ]);
    }

    public function expiringLeasesChart(Request $request)
    {
        $months = $request->integer('months', 6);
        abort_if($months < 1 || $months > 24, 422, 'The months parameter must be between 1 and 24.');
        $data = [];
        for ($i = 0; $i < $months; $i++) {
            $date = now()->addMonths($i);
            $count = Lease::where('status', 'active')
                ->whereYear('end_date', $date->year)
                ->whereMonth('end_date', $date->month)
                ->count();
            $data[] = [
                'month' => $date->format('Y-m'),
                'count' => $count,
            ];
        }

        return response()->json($data);
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
                    'type' => 'lease',
                    'description' => "Lease {$lease->lease_number} created for ".($lease->tenant ? $lease->tenant->first_name.' '.$lease->tenant->last_name : 'N/A'),
                    'created_at' => $lease->created_at->toDateTimeString(),
                ];
            });

        $recentPayments = Payment::with(['tenant', 'invoice'])
            ->latest()->limit($limit)->get()
            ->map(function ($payment) use (&$activities) {
                $activities[] = [
                    'type' => 'payment',
                    'description' => "Payment of {$payment->amount} received from ".($payment->tenant ? $payment->tenant->first_name.' '.$payment->tenant->last_name : 'N/A'),
                    'created_at' => $payment->created_at->toDateTimeString(),
                ];
            });

        $recentInvoices = Invoice::with(['tenant'])
            ->latest()->limit($limit)->get()
            ->map(function ($invoice) use (&$activities) {
                $activities[] = [
                    'type' => 'invoice',
                    'description' => "Invoice {$invoice->invoice_number} generated for ".($invoice->tenant ? $invoice->tenant->first_name.' '.$invoice->tenant->last_name : 'N/A'),
                    'created_at' => $invoice->created_at->toDateTimeString(),
                ];
            });

        usort($activities, fn ($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

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

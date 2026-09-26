<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Lease;
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
        $occupied = Unit::where('status','occupied')->count();
        $vacant = Unit::where('status','vacant')->count();
        $occupancy = $totalUnits ? round($occupied / $totalUnits * 100, 2) : 0;

        $activeLeases = Lease::where('status','active')->count();
        $expiringSoon = Lease::expiringSoon(30)->count();

        $overdueInvoices = Invoice::where('status','overdue')->count();
        $pendingAmount = Invoice::where('status','pending')->sum('total_amount');
        $overdueAmount = Invoice::where('status','overdue')->sum('total_amount');
        $collectedThisMonth = DB::table('payments')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return response()->json([
            'properties' => $totalProperties,
            'units' => ['total'=>$totalUnits,'occupied'=>$occupied,'vacant'=>$vacant,'occupancy_rate'=>$occupancy],
            'leases' => ['active'=>$activeLeases,'expiring_soon'=>$expiringSoon],
            'invoices' => ['overdue_count'=>$overdueInvoices,'pending_amount'=>$pendingAmount,'overdue_amount'=>$overdueAmount],
            'revenue' => ['collected_this_month'=>$collectedThisMonth],
        ]);
    }

    public function revenueTrend(Request $request)
    {
        $months = $request->get('months', 6);
        $trend = DB::table('payments')
            ->select(DB::raw("DATE_FORMAT(paid_at,'%Y-%m') as month"), DB::raw('SUM(amount) as total'))
            ->where('paid_at','>=', now()->subMonths($months))
            ->groupBy('month')->orderBy('month')->get();

        $invoiced = Invoice::select(DB::raw("DATE_FORMAT(created_at,'%Y-%m') as month"), DB::raw('SUM(total_amount) as total'))
            ->where('created_at','>=', now()->subMonths($months))
            ->groupBy('month')->orderBy('month')->get();

        return response()->json(['collected'=>$trend,'invoiced'=>$invoiced]);
    }

    public function expiringLeases()
    {
        $leases = Lease::with(['tenant','unit.building.property'])
            ->expiringSoon(60)->orderBy('end_date')->limit(10)->get();
        return response()->json($leases);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VaultAsset;
use Illuminate\Http\Request;

class VaultAssetController extends Controller
{
    public function index(Request $request)
    {
        $query = VaultAsset::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('holder', 'like', "%{$search}%")
                    ->orWhere('safe_id', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $assets = $query->with('property:id,name')->latest()->paginate($perPage);

        return response()->json($assets);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:vault_assets,code',
            'asset_type' => 'required|string',
            'title' => 'required|string',
            'property_id' => 'required|exists:properties,id',
            'unit_code' => 'nullable|string',
            'holder' => 'required|string',
            'safe_id' => 'required|string',
            'safe_location' => 'required|string',
            'status' => 'nullable|in:sealed,active,released,expired',
            'deposited_on' => 'required|date',
            'expires_on' => 'nullable|date',
            'value' => 'nullable|numeric',
            'document_count' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $asset = VaultAsset::create($validated);

        return response()->json($asset->load('property:id,name'), 201);
    }

    public function show(VaultAsset $vaultAsset)
    {
        return response()->json($vaultAsset->load('property:id,name'));
    }

    public function update(Request $request, VaultAsset $vaultAsset)
    {
        $validated = $request->validate([
            'asset_type' => 'sometimes|string',
            'title' => 'sometimes|string',
            'property_id' => 'sometimes|exists:properties,id',
            'unit_code' => 'nullable|string',
            'holder' => 'sometimes|string',
            'safe_id' => 'sometimes|string',
            'safe_location' => 'sometimes|string',
            'status' => 'sometimes|in:sealed,active,released,expired',
            'deposited_on' => 'sometimes|date',
            'expires_on' => 'nullable|date',
            'value' => 'sometimes|numeric',
            'document_count' => 'sometimes|integer',
            'notes' => 'nullable|string',
        ]);

        $vaultAsset->update($validated);

        return response()->json($vaultAsset->load('property:id,name'));
    }

    public function destroy(VaultAsset $vaultAsset)
    {
        $vaultAsset->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => VaultAsset::count(),
            'by_status' => VaultAsset::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_type' => VaultAsset::select('asset_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('asset_type')
                ->pluck('count', 'asset_type')
                ->toArray(),
            'total_value' => VaultAsset::sum('value'),
            'attention' => VaultAsset::whereIn('status', ['sealed', 'expired'])->count(),
        ]);
    }

    public function release(Request $request, VaultAsset $vaultAsset)
    {
        $vaultAsset->update(['status' => 'released', 'notes' => $request->input('notes', $vaultAsset->notes)]);
        return response()->json($vaultAsset->load('property:id,name'));
    }
}
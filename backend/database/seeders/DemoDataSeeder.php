<?php

namespace Database\Seeders;

use App\Models\BouncedCheque;
use App\Models\Building;
use App\Models\EjariContract;
use App\Models\EscrowAccount;
use App\Models\FitOutRequest;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\LeaseAgreement;
use App\Models\LeaseRenewal;
use App\Models\Payment;
use App\Models\PostDatedCheque;
use App\Models\Property;
use App\Models\ServiceCharge;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Models\Unit;
use App\Models\User;
use App\Models\VaultAsset;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production') || ! filter_var(env('DEMO_SEED_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Demo seeding is disabled. Enable DEMO_SEED_ENABLED only for a non-production database.');
        }

        $this->seedUsers();
        $this->seedProperties();
        $this->seedBuildings();
        $this->seedUnits();
        $this->seedTenants();
        $this->seedLeases();
        $this->seedInvoices();
        $this->seedPayments();
        $this->seedTenantDocuments();
        $this->seedInvoiceLineItems();
        $this->seedVaultAssets();
        $this->seedPostDatedCheques();
        $this->seedLeaseAgreements();
        $this->seedLeaseRenewals();
        $this->seedServiceCharges();
        $this->seedEscrowAccounts();
        $this->seedEjariContracts();
        $this->seedFitOutRequests();
        $this->seedBouncedCheques();

        $this->command->info(sprintf(
            'Demo data seeded: %d users, %d properties, %d buildings, %d units, %d tenants, %d leases, %d invoices, %d payments, %d documents, %d vault assets, %d post-dated cheques, %d lease agreements, %d lease renewals, %d service charges, %d escrow accounts, %d ejari contracts, %d fit-out requests, %d bounced cheques',
            User::count(),
            Property::count(),
            Building::count(),
            Unit::count(),
            Tenant::count(),
            Lease::count(),
            Invoice::count(),
            Payment::count(),
            TenantDocument::count(),
            VaultAsset::count(),
            PostDatedCheque::count(),
            LeaseAgreement::count(),
            LeaseRenewal::count(),
            ServiceCharge::count(),
            EscrowAccount::count(),
            EjariContract::count(),
            FitOutRequest::count(),
            BouncedCheque::count()
        ));
    }

    // ------------------------------------------------------------------
    // Users
    // ------------------------------------------------------------------

    private function seedUsers(): void
    {
        $staff = [
            ['name' => 'John Smith', 'email' => 'admin@gaarbejana.com', 'role' => 'admin'],
            ['name' => 'Maria Lopez', 'email' => 'manager@gaarbejana.com', 'role' => 'manager'],
            ['name' => 'Sam Carter', 'email' => 'agent@gaarbejana.com', 'role' => 'agent'],
            ['name' => 'Sarah Johnson', 'email' => 'sarah@propertylease.com', 'role' => 'manager'],
            ['name' => 'David Kim', 'email' => 'david@propertylease.com', 'role' => 'accountant'],
            ['name' => 'Emma Wilson', 'email' => 'emma@propertylease.com', 'role' => 'agent'],
            ['name' => 'Ahmed Hassan', 'email' => 'ahmed@propertylease.com', 'role' => 'manager'],
            ['name' => 'Lisa Chen', 'email' => 'lisa@propertylease.com', 'role' => 'accountant'],
        ];

        foreach ($staff as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make(Str::random(64)), 'role' => $u['role']]
            );
        }
    }

    // ------------------------------------------------------------------
    // Properties
    // ------------------------------------------------------------------

    private function seedProperties(): void
    {
        if (Property::count() > 0) {
            $this->command->info('Properties already exist — skipping property seed.');
            return;
        }

        $manager = User::where('role', 'manager')->first();

        $properties = [
            ['name' => 'Harbor Point Residences', 'code' => 'PRP-1001', 'type' => 'multi_family', 'address' => '1200 Harbor View Blvd', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'country' => 'USA', 'total_area_sqft' => 125000, 'year_built' => 2015, 'status' => 'active', 'description' => 'Waterfront residential complex with panoramic views of Puget Sound. Features 48 units across three towers.'],
            ['name' => 'Cedar Grove Apartments', 'code' => 'PRP-1002', 'type' => 'multi_family', 'address' => '845 Cedar Grove Ln', 'city' => 'Portland', 'state' => 'OR', 'zip' => '97205', 'country' => 'USA', 'total_area_sqft' => 82000, 'year_built' => 2010, 'status' => 'active', 'description' => 'Garden-style apartment community with mature landscaping. 32 units in two low-rise buildings.'],
            ['name' => 'Meridian Business Park', 'code' => 'PRP-1003', 'type' => 'commercial', 'address' => '4800 Meridian Pkwy', 'city' => 'Bellevue', 'state' => 'WA', 'zip' => '98004', 'country' => 'USA', 'total_area_sqft' => 210000, 'year_built' => 2018, 'status' => 'active', 'description' => 'Class A office park with 24 premium office suites. LEED Gold certified.'],
            ['name' => 'Sunset Retail Center', 'code' => 'PRP-1004', 'type' => 'commercial', 'address' => '77 Sunset Highway', 'city' => 'Tacoma', 'state' => 'WA', 'zip' => '98402', 'country' => 'USA', 'total_area_sqft' => 65000, 'year_built' => 2008, 'status' => 'active', 'description' => 'Neighborhood retail center with 12 commercial units including anchor grocery tenant.'],
            ['name' => 'Northgate Industrial', 'code' => 'PRP-1005', 'type' => 'commercial', 'address' => '2250 Northgate Way', 'city' => 'Everett', 'state' => 'WA', 'zip' => '98201', 'country' => 'USA', 'total_area_sqft' => 180000, 'year_built' => 2020, 'status' => 'active', 'description' => 'Modern industrial warehouse facility with 8 high-bay units. Fully leased to logistics operators.'],
            ['name' => 'Lakeside Villas', 'code' => 'PRP-1006', 'type' => 'multi_family', 'address' => '9 Lakeshore Terrace', 'city' => 'Bellingham', 'state' => 'WA', 'zip' => '98225', 'country' => 'USA', 'total_area_sqft' => 45000, 'year_built' => 2005, 'status' => 'inactive', 'description' => 'Boutique villa community on Lake Whatcom. Currently held off-market pending renovation.'],
            ['name' => 'Summit Plaza Offices', 'code' => 'PRP-1007', 'type' => 'commercial', 'address' => '310 Summit Plaza', 'city' => 'Spokane', 'state' => 'WA', 'zip' => '99201', 'country' => 'USA', 'total_area_sqft' => 145000, 'year_built' => 2019, 'status' => 'active', 'description' => 'Mid-rise office building in downtown Spokane. 16 office floors with ground-floor retail.'],
            ['name' => 'Marina Bay Waterfront', 'code' => 'PRP-1008', 'type' => 'mixed_use', 'address' => '500 Marina Bay Dr', 'city' => 'San Francisco', 'state' => 'CA', 'zip' => '94123', 'country' => 'USA', 'total_area_sqft' => 195000, 'year_built' => 2021, 'status' => 'active', 'description' => 'Mixed-use waterfront development with 36 residential units over ground-floor retail and restaurant space.'],
            ['name' => 'Pine Valley Townhomes', 'code' => 'PRP-1009', 'type' => 'multi_family', 'address' => '340 Pine Valley Rd', 'city' => 'Denver', 'state' => 'CO', 'zip' => '80202', 'country' => 'USA', 'total_area_sqft' => 280000, 'year_built' => 2017, 'status' => 'active', 'description' => 'Gated townhome community with 64 residences across four buildings. Community clubhouse and pool.'],
            ['name' => 'Tech Ridge Corporate Center', 'code' => 'PRP-1010', 'type' => 'commercial', 'address' => '1500 Tech Ridge Blvd', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78758', 'country' => 'USA', 'total_area_sqft' => 320000, 'year_built' => 2022, 'status' => 'active', 'description' => 'State-of-the-art corporate campus with 42 tech office suites. Powered by renewable energy.'],
            ['name' => 'Palm Court Shopping Plaza', 'code' => 'PRP-1011', 'type' => 'commercial', 'address' => '2200 Palm Court', 'city' => 'Phoenix', 'state' => 'AZ', 'zip' => '85004', 'country' => 'USA', 'total_area_sqft' => 95000, 'year_built' => 2012, 'status' => 'active', 'description' => 'Open-air shopping plaza with 18 retail units. Strong anchor mix including national pharmacy and grocery.'],
            ['name' => 'Riverbend Logistics Hub', 'code' => 'PRP-1012', 'type' => 'commercial', 'address' => '8900 Riverbend Pkwy', 'city' => 'Memphis', 'state' => 'TN', 'zip' => '38118', 'country' => 'USA', 'total_area_sqft' => 250000, 'year_built' => 2023, 'status' => 'active', 'description' => 'Class A logistics hub with 14 warehouse units. Direct highway access and rail spur.'],
        ];

        foreach ($properties as $p) {
            $p['manager_id'] = $manager?->id;
            Property::create($p);
        }
    }

    // ------------------------------------------------------------------
    // Buildings
    // ------------------------------------------------------------------

    private function seedBuildings(): void
    {
        if (Building::count() > 0) {
            $this->command->info('Buildings already exist — skipping building seed.');
            return;
        }

        $buildings = [
            // Harbor Point Residences
            ['property_id' => 1, 'name' => 'Harbor Tower A', 'code' => 'HB-A', 'floors' => 12, 'year_built' => 2015, 'construction_type' => 'concrete', 'address' => '1200 Harbor View Blvd', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'status' => 'active', 'description' => 'Main residential tower with elevator and underground parking.'],
            ['property_id' => 1, 'name' => 'Harbor Tower B', 'code' => 'HB-B', 'floors' => 8, 'year_built' => 2015, 'construction_type' => 'concrete', 'address' => '1200 Harbor View Blvd', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'status' => 'active', 'description' => 'Secondary residential tower with waterfront balconies.'],
            ['property_id' => 1, 'name' => 'Harbor Annex', 'code' => 'HB-C', 'floors' => 4, 'year_built' => 2016, 'construction_type' => 'steel_frame', 'address' => '1200 Harbor View Blvd', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'status' => 'under_maintenance', 'description' => 'Annex building undergoing facade renovation.'],
            // Cedar Grove Apartments
            ['property_id' => 2, 'name' => 'Cedar Grove North', 'code' => 'CG-N', 'floors' => 6, 'year_built' => 2010, 'construction_type' => 'wood_frame', 'address' => '845 Cedar Grove Ln', 'city' => 'Portland', 'state' => 'OR', 'zip' => '97205', 'status' => 'active', 'description' => 'North apartment building with 18 units.'],
            ['property_id' => 2, 'name' => 'Cedar Grove South', 'code' => 'CG-S', 'floors' => 4, 'year_built' => 2010, 'construction_type' => 'wood_frame', 'address' => '845 Cedar Grove Ln', 'city' => 'Portland', 'state' => 'OR', 'zip' => '97205', 'status' => 'active', 'description' => 'South apartment building with 14 units and courtyard.'],
            // Meridian Business Park
            ['property_id' => 3, 'name' => 'Meridian Tower', 'code' => 'MD-T', 'floors' => 9, 'year_built' => 2018, 'construction_type' => 'steel_glass', 'address' => '4800 Meridian Pkwy', 'city' => 'Bellevue', 'state' => 'WA', 'zip' => '98004', 'status' => 'active', 'description' => 'Premium office tower with floor-to-ceiling glass.'],
            ['property_id' => 3, 'name' => 'Meridian Flex', 'code' => 'MD-F', 'floors' => 3, 'year_built' => 2019, 'construction_type' => 'steel_frame', 'address' => '4800 Meridian Pkwy', 'city' => 'Bellevue', 'state' => 'WA', 'zip' => '98004', 'status' => 'active', 'description' => 'Flexible office/lab building for tech tenants.'],
            // Sunset Retail Center
            ['property_id' => 4, 'name' => 'Sunset Plaza', 'code' => 'SP-1', 'floors' => 2, 'year_built' => 2008, 'construction_type' => 'stucco', 'address' => '77 Sunset Highway', 'city' => 'Tacoma', 'state' => 'WA', 'zip' => '98402', 'status' => 'active', 'description' => 'Single-story retail strip with 12 units.'],
            // Northgate Industrial
            ['property_id' => 5, 'name' => 'Northgate Shed', 'code' => 'NG-1', 'floors' => 1, 'year_built' => 2020, 'construction_type' => 'pre_eng_metal', 'address' => '2250 Northgate Way', 'city' => 'Everett', 'state' => 'WA', 'zip' => '98201', 'status' => 'active', 'description' => 'Pre-engineered metal warehouse with 8 dock-high doors.'],
            // Lakeside Villas
            ['property_id' => 6, 'name' => 'Lakeside Lodge', 'code' => 'LK-1', 'floors' => 3, 'year_built' => 2005, 'construction_type' => 'wood_frame', 'address' => '9 Lakeshore Terrace', 'city' => 'Bellingham', 'state' => 'WA', 'zip' => '98225', 'status' => 'inactive', 'description' => 'Lakeside villa building currently held off-market.'],
            // Summit Plaza Offices
            ['property_id' => 7, 'name' => 'Summit Plaza', 'code' => 'SM-1', 'floors' => 5, 'year_built' => 2019, 'construction_type' => 'concrete', 'address' => '310 Summit Plaza', 'city' => 'Spokane', 'state' => 'WA', 'zip' => '99201', 'status' => 'active', 'description' => 'Mid-rise office with ground-floor retail.'],
            // Marina Bay Waterfront
            ['property_id' => 8, 'name' => 'Marina East Tower', 'code' => 'MB-E', 'floors' => 10, 'year_built' => 2021, 'construction_type' => 'concrete_glass', 'address' => '500 Marina Bay Dr', 'city' => 'San Francisco', 'state' => 'CA', 'zip' => '94123', 'status' => 'active', 'description' => 'Luxury residential tower with bay views.'],
            ['property_id' => 8, 'name' => 'Marina West Tower', 'code' => 'MB-W', 'floors' => 7, 'year_built' => 2021, 'construction_type' => 'concrete_glass', 'address' => '500 Marina Bay Dr', 'city' => 'San Francisco', 'state' => 'CA', 'zip' => '94123', 'status' => 'active', 'description' => 'Residential tower with ground-floor retail.'],
            // Pine Valley Townhomes
            ['property_id' => 9, 'name' => 'Pine Valley North', 'code' => 'PV-N', 'floors' => 8, 'year_built' => 2017, 'construction_type' => 'concrete', 'address' => '340 Pine Valley Rd', 'city' => 'Denver', 'state' => 'CO', 'zip' => '80202', 'status' => 'active', 'description' => 'North townhome building with 24 residences.'],
            ['property_id' => 9, 'name' => 'Pine Valley South', 'code' => 'PV-S', 'floors' => 8, 'year_built' => 2017, 'construction_type' => 'concrete', 'address' => '340 Pine Valley Rd', 'city' => 'Denver', 'state' => 'CO', 'zip' => '80202', 'status' => 'active', 'description' => 'South townhome building with 20 residences.'],
            ['property_id' => 9, 'name' => 'Pine Valley East', 'code' => 'PV-E', 'floors' => 6, 'year_built' => 2018, 'construction_type' => 'concrete', 'address' => '340 Pine Valley Rd', 'city' => 'Denver', 'state' => 'CO', 'zip' => '80202', 'status' => 'active', 'description' => 'East townhome building with 18 residences.'],
            ['property_id' => 9, 'name' => 'Pine Valley Clubhouse', 'code' => 'PV-C', 'floors' => 4, 'year_built' => 2017, 'construction_type' => 'wood_frame', 'address' => '340 Pine Valley Rd', 'city' => 'Denver', 'state' => 'CO', 'zip' => '80202', 'status' => 'active', 'description' => 'Community clubhouse with leasing office and amenities.'],
            // Tech Ridge Corporate Center
            ['property_id' => 10, 'name' => 'Tech Ridge One', 'code' => 'TR-1', 'floors' => 12, 'year_built' => 2022, 'construction_type' => 'steel_glass', 'address' => '1500 Tech Ridge Blvd', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78758', 'status' => 'active', 'description' => 'Flagship office tower with 16 premium floors.'],
            ['property_id' => 10, 'name' => 'Tech Ridge Two', 'code' => 'TR-2', 'floors' => 9, 'year_built' => 2022, 'construction_type' => 'steel_glass', 'address' => '1500 Tech Ridge Blvd', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78758', 'status' => 'active', 'description' => 'Second office tower with collaborative workspace.'],
            ['property_id' => 10, 'name' => 'Tech Ridge Labs', 'code' => 'TR-3', 'floors' => 6, 'year_built' => 2023, 'construction_type' => 'steel_frame', 'address' => '1500 Tech Ridge Blvd', 'city' => 'Austin', 'state' => 'TX', 'zip' => '78758', 'status' => 'under_maintenance', 'description' => 'R&D lab building undergoing HVAC upgrade.'],
            // Palm Court Shopping Plaza
            ['property_id' => 11, 'name' => 'Palm Court Center', 'code' => 'PC-1', 'floors' => 2, 'year_built' => 2012, 'construction_type' => 'stucco', 'address' => '2200 Palm Court', 'city' => 'Phoenix', 'state' => 'AZ', 'zip' => '85004', 'status' => 'active', 'description' => 'Open-air shopping center with 18 retail units.'],
            // Riverbend Logistics Hub
            ['property_id' => 12, 'name' => 'Riverbend Warehouse A', 'code' => 'RB-A', 'floors' => 5, 'year_built' => 2023, 'construction_type' => 'pre_eng_metal', 'address' => '8900 Riverbend Pkwy', 'city' => 'Memphis', 'state' => 'TN', 'zip' => '38118', 'status' => 'active', 'description' => 'Class A warehouse with 8 dock-high doors.'],
            ['property_id' => 12, 'name' => 'Riverbend Warehouse B', 'code' => 'RB-B', 'floors' => 3, 'year_built' => 2023, 'construction_type' => 'pre_eng_metal', 'address' => '8900 Riverbend Pkwy', 'city' => 'Memphis', 'state' => 'TN', 'zip' => '38118', 'status' => 'active', 'description' => 'Secondary warehouse with 6 dock-high doors.'],
        ];

        foreach ($buildings as $b) {
            Building::create($b);
        }
    }

    // ------------------------------------------------------------------
    // Units
    // ------------------------------------------------------------------

    private function seedUnits(): void
    {
        $residentialTypes = ['apartment', 'apartment', 'apartment', 'studio'];
        $commercialTypes = ['office', 'retail', 'warehouse'];

        $unitDefs = [
            // Harbor Point Residences (48 units)
            ['building_id' => 1, 'count' => 20, 'type' => 'residential', 'base_sqft' => 620, 'rent' => 1450, 'bedrooms' => 1, 'bathrooms' => 1],
            ['building_id' => 2, 'count' => 16, 'type' => 'residential', 'base_sqft' => 580, 'rent' => 1350, 'bedrooms' => 1, 'bathrooms' => 1],
            ['building_id' => 3, 'count' => 12, 'type' => 'residential', 'base_sqft' => 520, 'rent' => 1250, 'bedrooms' => 1, 'bathrooms' => 1],
            // Cedar Grove Apartments (32 units)
            ['building_id' => 4, 'count' => 18, 'type' => 'residential', 'base_sqft' => 650, 'rent' => 1500, 'bedrooms' => 2, 'bathrooms' => 1],
            ['building_id' => 5, 'count' => 14, 'type' => 'residential', 'base_sqft' => 600, 'rent' => 1400, 'bedrooms' => 1, 'bathrooms' => 1],
            // Meridian Business Park (24 units)
            ['building_id' => 6, 'count' => 14, 'type' => 'commercial', 'base_sqft' => 2800, 'rent' => 4500, 'bedrooms' => 0, 'bathrooms' => 2],
            ['building_id' => 7, 'count' => 10, 'type' => 'commercial', 'base_sqft' => 3200, 'rent' => 5200, 'bedrooms' => 0, 'bathrooms' => 2],
            // Sunset Retail Center (12 units)
            ['building_id' => 8, 'count' => 12, 'type' => 'retail', 'base_sqft' => 1800, 'rent' => 3200, 'bedrooms' => 0, 'bathrooms' => 2],
            // Northgate Industrial (8 units)
            ['building_id' => 9, 'count' => 8, 'type' => 'warehouse', 'base_sqft' => 8000, 'rent' => 12000, 'bedrooms' => 0, 'bathrooms' => 2],
            // Lakeside Villas (10 units)
            ['building_id' => 10, 'count' => 10, 'type' => 'residential', 'base_sqft' => 700, 'rent' => 1600, 'bedrooms' => 2, 'bathrooms' => 2],
            // Summit Plaza Offices (16 units)
            ['building_id' => 11, 'count' => 16, 'type' => 'commercial', 'base_sqft' => 2200, 'rent' => 3800, 'bedrooms' => 0, 'bathrooms' => 2],
            // Marina Bay Waterfront (36 units)
            ['building_id' => 12, 'count' => 18, 'type' => 'residential', 'base_sqft' => 750, 'rent' => 2800, 'bedrooms' => 2, 'bathrooms' => 2],
            ['building_id' => 13, 'count' => 14, 'type' => 'residential', 'base_sqft' => 680, 'rent' => 2500, 'bedrooms' => 1, 'bathrooms' => 1],
            ['building_id' => 13, 'count' => 4, 'type' => 'retail', 'base_sqft' => 2000, 'rent' => 4200, 'bedrooms' => 0, 'bathrooms' => 2],
            // Pine Valley Townhomes (64 units)
            ['building_id' => 14, 'count' => 24, 'type' => 'residential', 'base_sqft' => 1100, 'rent' => 2200, 'bedrooms' => 3, 'bathrooms' => 2],
            ['building_id' => 15, 'count' => 20, 'type' => 'residential', 'base_sqft' => 1050, 'rent' => 2100, 'bedrooms' => 3, 'bathrooms' => 2],
            ['building_id' => 16, 'count' => 18, 'type' => 'residential', 'base_sqft' => 980, 'rent' => 1950, 'bedrooms' => 2, 'bathrooms' => 2],
            ['building_id' => 17, 'count' => 2, 'type' => 'commercial', 'base_sqft' => 3000, 'rent' => 5000, 'bedrooms' => 0, 'bathrooms' => 2],
            // Tech Ridge Corporate Center (42 units)
            ['building_id' => 18, 'count' => 16, 'type' => 'commercial', 'base_sqft' => 3500, 'rent' => 6500, 'bedrooms' => 0, 'bathrooms' => 2],
            ['building_id' => 19, 'count' => 14, 'type' => 'commercial', 'base_sqft' => 3000, 'rent' => 5800, 'bedrooms' => 0, 'bathrooms' => 2],
            ['building_id' => 20, 'count' => 12, 'type' => 'commercial', 'base_sqft' => 4200, 'rent' => 7800, 'bedrooms' => 0, 'bathrooms' => 3],
            // Palm Court Shopping Plaza (18 units)
            ['building_id' => 21, 'count' => 18, 'type' => 'retail', 'base_sqft' => 2200, 'rent' => 3800, 'bedrooms' => 0, 'bathrooms' => 2],
            // Riverbend Logistics Hub (14 units)
            ['building_id' => 22, 'count' => 8, 'type' => 'warehouse', 'base_sqft' => 12000, 'rent' => 18000, 'bedrooms' => 0, 'bathrooms' => 2],
            ['building_id' => 23, 'count' => 6, 'type' => 'warehouse', 'base_sqft' => 10000, 'rent' => 15000, 'bedrooms' => 0, 'bathrooms' => 2],
        ];

        $statuses = ['occupied', 'occupied', 'occupied', 'vacant', 'reserved', 'occupied', 'maintenance'];
        $unitOffsets = [];

        foreach ($unitDefs as $def) {
            $building = Building::find($def['building_id']);
            if (! $building) continue;

            $unitOffset = $unitOffsets[$def['building_id']] ?? 0;
            $existingUnitIndexes = Unit::query()
                ->where('building_id', $def['building_id'])
                ->pluck('unit_number')
                ->map(fn ($number) => (int) $number % 100)
                ->all();
            $isResidential = in_array($def['type'], ['residential', 'studio'], true);
            $unitType = $isResidential
                ? $residentialTypes[array_rand($residentialTypes)]
                : $commercialTypes[array_rand($commercialTypes)];

            for ($i = 1; $i <= $def['count']; $i++) {
                $status = $building->status === 'inactive'
                    ? 'vacant'
                    : ($building->status === 'under_maintenance' && $i <= 2 ? 'maintenance' : $statuses[array_rand($statuses)]);

                $unitIndex = $unitOffset + $i;
                if (in_array($unitIndex, $existingUnitIndexes, true)) {
                    continue;
                }

                $unitFloor = $isResidential ? (($unitIndex % $building->floors) + 1) : 1;
                $unitNumber = $building->floors > 1
                    ? sprintf('%d%02d', $unitFloor, $unitIndex)
                    : (100 + $unitIndex);

                $sqft = $def['base_sqft'] + ($i % 5) * ($isResidential ? 90 : 260);
                $rent = $def['rent'] + ($i % 6) * ($isResidential ? 175 : 450);

                Unit::firstOrCreate([
                    'building_id' => $def['building_id'],
                    'unit_number' => $unitNumber,
                ], [
                    'name' => $isResidential ? "Unit {$i}" : "Suite {$i}",
                    'floor' => $unitFloor,
                    'sqft' => $sqft,
                    'bedrooms' => $isResidential ? (1 + ($i % 3)) : 0,
                    'bathrooms' => $isResidential ? (1 + ($i % 2)) : 2,
                    'rent_amount' => $rent,
                    'unit_type' => $unitType,
                    'status' => $status,
                    'amenities' => $isResidential ? json_encode(['AC', 'Dishwasher', 'Balcony']) : json_encode(['Loading Dock', 'HVAC', 'Security']),
                    'description' => null,
                ]);
                $existingUnitIndexes[] = $unitIndex;
            }

            $unitOffsets[$def['building_id']] = $unitOffset + $def['count'];
        }
    }

    // ------------------------------------------------------------------
    // Tenants
    // ------------------------------------------------------------------

    private function seedTenants(): void
    {
        if (Tenant::count() > 0) {
            $this->command->info('Tenants already exist — skipping tenant seed.');
            return;
        }

        $tenantDefs = [
            ['first_name' => 'Amelia', 'last_name' => 'Hartley', 'company_name' => 'Hartley Legal Group', 'status' => 'active'],
            ['first_name' => 'Noah', 'last_name' => 'Bennett', 'company_name' => 'Bennett & Co Consulting', 'status' => 'active'],
            ['first_name' => 'Priya', 'last_name' => 'Raman', 'company_name' => 'Raman Diagnostics', 'status' => 'active'],
            ['first_name' => 'Lucas', 'last_name' => 'Moreau', 'company_name' => 'Moreau Design Studio', 'status' => 'active'],
            ['first_name' => 'Sofia', 'last_name' => 'Alvarez', 'company_name' => 'Alvarez Logistics', 'status' => 'active'],
            ['first_name' => 'Ethan', 'last_name' => 'Whitfield', 'company_name' => 'Whitfield Dental', 'status' => 'active'],
            ['first_name' => 'Grace', 'last_name' => 'Lindqvist', 'company_name' => 'Lindqvist Imports', 'status' => 'active'],
            ['first_name' => 'Omar', 'last_name' => 'Haddad', 'company_name' => 'Haddad Textiles', 'status' => 'active'],
            ['first_name' => 'Maya', 'last_name' => 'Kowalski', 'company_name' => 'Kowalski Bakery', 'status' => 'active'],
            ['first_name' => 'Daniel', 'last_name' => 'Osei', 'company_name' => 'Osei Fitness', 'status' => 'active'],
            ['first_name' => 'Hannah', 'last_name' => 'Feldman', 'company_name' => 'Feldman Legal', 'status' => 'active'],
            ['first_name' => 'Tobias', 'last_name' => 'Nguyen', 'company_name' => 'Nguyen Motors', 'status' => 'active'],
            ['first_name' => 'Isabel', 'last_name' => 'Ferreira', 'company_name' => 'Ferreira Cafe', 'status' => 'active'],
            ['first_name' => 'Julian', 'last_name' => 'Ashworth', 'company_name' => 'Ashworth Ventures', 'status' => 'inactive'],
            ['first_name' => 'Clara', 'last_name' => 'Novak', 'company_name' => 'Novak Interiors', 'status' => 'active'],
            ['first_name' => 'Rashid', 'last_name' => 'Khan', 'company_name' => 'Khan Pharmacy', 'status' => 'active'],
            ['first_name' => 'Elena', 'last_name' => 'Petrova', 'company_name' => 'Petrova Media', 'status' => 'active'],
            ['first_name' => 'Marcus', 'last_name' => 'Delgado', 'company_name' => 'Delgado Construction', 'status' => 'active'],
            ['first_name' => 'Yuki', 'last_name' => 'Tanaka', 'company_name' => 'Tanaka Robotics', 'status' => 'active'],
            ['first_name' => 'Fiona', 'last_name' => 'Gallagher', 'company_name' => 'Gallagher Accounting', 'status' => 'active'],
            ['first_name' => 'Samuel', 'last_name' => 'Okafor', 'company_name' => 'Okafor Logistics', 'status' => 'active'],
            ['first_name' => 'Nadia', 'last_name' => 'Rahman', 'company_name' => 'Rahman Consulting', 'status' => 'inactive'],
            ['first_name' => 'Victor', 'last_name' => 'Larsen', 'company_name' => 'Larsen Marine', 'status' => 'active'],
            ['first_name' => 'Alice', 'last_name' => 'Moreau', 'company_name' => 'Moreau Design Studio', 'status' => 'active'],
            ['first_name' => 'Raj', 'last_name' => 'Patel', 'company_name' => 'Patel Software Solutions', 'status' => 'active'],
            ['first_name' => 'Ingrid', 'last_name' => 'Bergstrom', 'company_name' => 'Bergstrom Architecture', 'status' => 'active'],
            ['first_name' => 'Carlos', 'last_name' => 'Mendoza', 'company_name' => 'Mendoza Restaurants Group', 'status' => 'active'],
            ['first_name' => 'Aisha', 'last_name' => 'Diallo', 'company_name' => 'Diallo Fashion House', 'status' => 'active'],
            ['first_name' => 'Henrik', 'last_name' => 'Johansson', 'company_name' => 'Johansson Trading Co', 'status' => 'active'],
            ['first_name' => 'Leila', 'last_name' => 'Ahmadi', 'company_name' => 'Ahmadi Health Clinic', 'status' => 'active'],
            ['first_name' => 'Gabriel', 'last_name' => 'Santos', 'company_name' => 'Santos Coffee Roasters', 'status' => 'active'],
            ['first_name' => 'Mei', 'last_name' => 'Chen', 'company_name' => 'Chen Tech Innovations', 'status' => 'active'],
            ['first_name' => 'Dmitri', 'last_name' => 'Volkov', 'company_name' => 'Volkov Legal Partners', 'status' => 'active'],
            ['first_name' => 'Zara', 'last_name' => 'Malik', 'company_name' => 'Malik Event Management', 'status' => 'active'],
            ['first_name' => 'Anders', 'last_name' => 'Nilsson', 'company_name' => 'Nilsson Design Agency', 'status' => 'active'],
            ['first_name' => 'Fatima', 'last_name' => 'Al-Rashid', 'company_name' => 'Al-Rashid Consulting', 'status' => 'active'],
            ['first_name' => 'James', 'last_name' => "O'Brien", 'company_name' => "O'Brien Properties", 'status' => 'active'],
            ['first_name' => 'Ananya', 'last_name' => 'Sharma', 'company_name' => 'Sharma Wellness Center', 'status' => 'active'],
            ['first_name' => 'Pierre', 'last_name' => 'Dubois', 'company_name' => 'Dubois Culinary Group', 'status' => 'active'],
        ];

        foreach ($tenantDefs as $i => $t) {
            $email = strtolower($t['first_name'] . '.' . $t['last_name']) . '@example.com';
            $tenant = Tenant::create([
                'first_name' => $t['first_name'],
                'last_name' => $t['last_name'],
                'email' => $email,
                'phone' => '+1 (206) 555-' . str_pad((string) (1000 + $i * 7), 4, '0', STR_PAD_LEFT),
                'company_name' => $t['company_name'],
                'id_number' => 'ID' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'tax_id' => (string) (82 + $i) . '-' . (4000000 + $i * 137),
                'date_of_birth' => Carbon::now()->subYears(25 + ($i % 30))->subMonths($i % 12)->toDateString(),
                'address' => rand(100, 9999) . ' ' . ['Main St', 'Oak Ave', 'Park Blvd', 'Cedar Ln'][rand(0, 3)],
                'emergency_contact' => $t['last_name'] . ' Contact',
                'emergency_contact_name' => $t['last_name'] . ' Contact',
                'emergency_contact_phone' => '+1 (425) 555-' . str_pad((string) (2000 + $i * 3), 4, '0', STR_PAD_LEFT),
                'status' => $t['status'],
                'notes' => $i % 4 === 0 ? 'Prefers email communication. Renews early.' : null,
            ]);

            // Create a user record for each tenant.
            User::create([
                'name' => $tenant->full_name,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'role' => 'tenant',
                'tenant_id' => $tenant->id,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Leases
    // ------------------------------------------------------------------

    private function seedLeases(): void
    {
        if (Lease::count() > 0) {
            $this->command->info('Leases already exist — skipping lease seed.');
            return;
        }

        $manager = User::where('role', 'manager')->first();
        $occupiableUnits = Unit::whereIn('status', ['occupied', 'reserved'])->get();

        $leaseTypes = ['fixed', 'fixed', 'residential', 'commercial', 'fixed', 'periodic'];
        $frequencies = ['monthly', 'monthly', 'monthly', 'quarterly', 'yearly'];
        $endOffsets = [12, 21, 28, 45, 62, 78, 88, 120, 155, 200, 240, 300, 360, 420, -20, -60, -110, 640];

        for ($i = 0; $i < 65; $i++) {
            $unit = $occupiableUnits[$i % $occupiableUnits->count()];
            $tenant = Tenant::where('status', 'active')->inRandomOrder()->first();
            if (! $unit || ! $tenant) continue;

            $endOffset = $endOffsets[array_rand($endOffsets)];
            $duration = 365;
            $signed = $endOffset - $duration;
            $status = $endOffset < 0 ? ($i % 2 === 0 ? 'expired' : 'terminated') : 'active';

            $lease = Lease::create([
                'lease_number' => 'LS-' . (5000 + $i + 1),
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'start_date' => Carbon::now()->addDays($signed)->toDateString(),
                'end_date' => Carbon::now()->addDays($endOffset)->toDateString(),
                'rent_amount' => $unit->rent_amount,
                'deposit_amount' => $unit->rent_amount,
                'late_fee_percent' => 2.5,
                'due_day' => 5,
                'status' => $status,
                'payment_frequency' => $frequencies[array_rand($frequencies)],
                'terms' => json_encode(['Tenant maintains interior', 'No subletting without consent']),
                'notes' => null,
                'document_path' => null,
                'created_by' => $manager?->id,
                'type' => $leaseTypes[array_rand($leaseTypes)],
                'escalation_clause' => $i % 3 === 0 ? '3% annual increase on each anniversary' : null,
                'renewal_options' => ($i % 2) + 1,
                'signed_at' => $status === 'active' ? Carbon::now()->addDays($signed + 3)->toDateTimeString() : null,
                'terminated_at' => $status === 'terminated' ? Carbon::now()->addDays($endOffset)->toDateTimeString() : null,
            ]);

            // Mark unit as occupied
            if ($status === 'active') {
                $unit->update(['status' => 'occupied']);
            }
        }
    }

    // ------------------------------------------------------------------
    // Invoices
    // ------------------------------------------------------------------

    private function seedInvoices(): void
    {
        if (Invoice::count() > 0) {
            $this->command->info('Invoices already exist — skipping invoice seed.');
            return;
        }

        $activeLeases = Lease::whereIn('status', ['active', 'expired'])->get();
        $invoiceId = 1;

        // 11 months of historical invoices + current month
        for ($back = 11; $back >= 1; $back--) {
            foreach ($activeLeases as $idx => $lease) {
                $periodStart = Carbon::now()->subMonths($back)->startOfMonth();
                $periodEnd = Carbon::now()->subMonths($back)->endOfMonth();
                $dueDate = $periodStart->copy()->day(5);
                $variant = ($idx + $back) % 5;

                $status = $variant === 5 ? 'cancelled' : ($variant === 3 ? 'partial' : 'paid');
                $amount = (float) $lease->rent_amount;
                $lateFee = 0;
                $utility = 0;

                if ($variant === 4) {
                    $lateFee = 75;
                    $amount += $lateFee;
                }
                if ($variant === 3) {
                    $utility = 120;
                    $amount += $utility;
                }

                $totalAmount = $amount;
                $paidAmount = match ($status) {
                    'paid' => $totalAmount,
                    'partial' => round($totalAmount * 0.5, 2),
                    default => 0,
                };

                $issueDate = $periodStart->toDateString();
                $paidDate = $paidAmount > 0 ? $periodStart->copy()->addDays(3)->toDateString() : null;

                Invoice::create([
                    'invoice_number' => 'INV-' . date('Y') . '-' . str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT),
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'unit_id' => $lease->unit_id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'amount' => $amount - $lateFee - $utility,
                    'late_fee' => $lateFee,
                    'total_amount' => $totalAmount,
                    'status' => $status,
                    'notes' => $lateFee > 0 ? 'Includes late payment fee' : ($utility > 0 ? 'Includes utility charges' : null),
                    'sent_at' => $periodStart->copy()->addDays(1)->toDateTimeString(),
                    'type' => $lateFee > 0 ? 'late_fee' : ($utility > 0 ? 'utility' : 'rent'),
                    'issue_date' => $issueDate,
                    'currency' => 'USD',
                ]);

                $invoiceId++;
            }
        }

        // Current month invoices
        foreach ($activeLeases as $idx => $lease) {
            $periodStart = Carbon::now()->startOfMonth();
            $periodEnd = Carbon::now()->endOfMonth();
            $variant = $idx % 6;

            $status = ['paid', 'paid', 'pending', 'partial', 'overdue', 'pending'][$variant] ?? 'pending';
            $amount = (float) $lease->rent_amount;
            $lateFee = $variant === 4 ? round($amount * 0.025, 2) : 0;
            $totalAmount = $amount + $lateFee;

            $paidAmount = match ($status) {
                'paid' => $totalAmount,
                'partial' => round($totalAmount * 0.5, 2),
                'overdue' => $variant === 0 ? 0 : round($totalAmount * 0.25, 2),
                default => 0,
            };

            Invoice::create([
                'invoice_number' => 'INV-' . date('Y') . '-' . str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT),
                'lease_id' => $lease->id,
                'tenant_id' => $lease->tenant_id,
                'unit_id' => $lease->unit_id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $periodStart->copy()->day(5)->toDateString(),
                'amount' => $amount,
                'late_fee' => $lateFee,
                'total_amount' => $totalAmount,
                'status' => $status,
                'notes' => null,
                'sent_at' => $periodStart->copy()->addDays(1)->toDateTimeString(),
                'type' => $lateFee > 0 ? 'late_fee' : 'rent',
                'issue_date' => $periodStart->toDateString(),
                'currency' => 'USD',
            ]);

            $invoiceId++;
        }
    }

    // ------------------------------------------------------------------
    // Payments
    // ------------------------------------------------------------------

    private function seedPayments(): void
    {
        if (Payment::count() > 0) {
            $this->command->info('Payments already exist — skipping payment seed.');
            return;
        }

        $accountant = User::where('role', 'accountant')->first();
        $methods = ['bank_transfer', 'check', 'online', 'card', 'cash'];
        $paidInvoices = Invoice::whereIn('status', ['paid', 'partial', 'overdue'])->get();

        foreach ($paidInvoices as $i => $invoice) {
            $paidAmount = $invoice->status === 'paid'
                ? (float) $invoice->total_amount
                : ($invoice->status === 'partial'
                    ? round((float) $invoice->total_amount * 0.5, 2)
                    : round((float) $invoice->total_amount * 0.25, 2));

            if ($paidAmount <= 0) continue;

            Payment::create([
                'payment_number' => 'PMT-' . (1000 + $i + 1),
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'amount' => $paidAmount,
                'method' => $methods[array_rand($methods)],
                'reference' => 'REF' . strtoupper(substr(md5((string) $i), 0, 6)),
                'paid_at' => Carbon::parse($invoice->due_date)->addDays(rand(-2, 3))->toDateTimeString(),
                'receipt_pdf_path' => null,
                'notes' => null,
                'recorded_by' => $accountant?->id,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Tenant Documents
    // ------------------------------------------------------------------

    private function seedTenantDocuments(): void
    {
        if (TenantDocument::count() > 0) {
            $this->command->info('Tenant documents already exist — skipping document seed.');
            return;
        }

        $admin = User::where('role', 'admin')->first();
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $docs = [
                ['name' => 'Signed lease agreement.pdf', 'category' => 'lease_agreement', 'size_kb' => 412],
                ['name' => 'Government ID (both sides).pdf', 'category' => 'id_document', 'size_kb' => 180],
                ['name' => 'Proof of insurance.pdf', 'category' => 'insurance', 'size_kb' => 96],
            ];

            if ($tenant->id % 2 === 0) {
                $docs[] = ['name' => 'Tax residency certificate.pdf', 'category' => 'tax_form', 'size_kb' => 64];
            }

            foreach ($docs as $di => $doc) {
                TenantDocument::create([
                    'tenant_id' => $tenant->id,
                    'name' => $doc['name'],
                    'category' => $doc['category'],
                    'disk' => 'documents',
                    'path' => "tenants/{$tenant->id}/" . $doc['name'],
                    'mime_type' => 'application/pdf',
                    'size_kb' => $doc['size_kb'],
                    'uploaded_by' => $admin?->id,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Invoice Line Items
    // ------------------------------------------------------------------

    private function seedInvoiceLineItems(): void
    {
        if (InvoiceLineItem::count() > 0) {
            $this->command->info('Invoice line items already exist — skipping line item seed.');
            return;
        }

        $invoices = Invoice::all();

        foreach ($invoices as $invoice) {
            $baseAmount = (float) $invoice->amount;
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Base rent — ' . Carbon::parse($invoice->period_start)->format('F Y'),
                'quantity' => 1,
                'unit_price' => $baseAmount,
                'amount' => $baseAmount,
                'tax_rate' => 0,
                'tax_amount' => 0,
            ]);

            if ((float) $invoice->late_fee > 0) {
                $fee = (float) $invoice->late_fee;
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Late payment fee',
                    'quantity' => 1,
                    'unit_price' => $fee,
                    'amount' => $fee,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                ]);
            }

            if (str_contains((string) $invoice->notes ?? '', 'utility')) {
                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'description' => 'Utilities (water/electric)',
                    'quantity' => 1,
                    'unit_price' => 120,
                    'amount' => 120,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Vault Assets
    // ------------------------------------------------------------------

    private function seedVaultAssets(): void
    {
        if (VaultAsset::count() > 0) {
            $this->command->info('Vault assets already exist — skipping seed.');
            return;
        }

        $assetTypes = ['deed', 'title', 'key', 'access_card', 'contract', 'certificate', 'license', 'permit'];
        $properties = Property::all();
        $holders = ['Property Manager', 'Legal Counsel', 'Bank Vault', 'Secure Storage Co.', 'Tenant Services', 'Facilities Team'];

        foreach ($properties as $prop) {
            $count = rand(2, 5);
            for ($i = 1; $i <= $count; $i++) {
                $type = $assetTypes[array_rand($assetTypes)];
                $status = ['sealed', 'active', 'released', 'expired'][array_rand(['sealed', 'active', 'released', 'expired'])];

                VaultAsset::create([
                    'code' => 'VA-' . $prop->code . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'asset_type' => $type,
                    'title' => ucfirst($type) . ' - ' . $prop->name . ' #' . $i,
                    'property_id' => $prop->id,
                    'property_name' => $prop->name,
                    'unit_code' => $i <= 2 ? 'UNIT-' . rand(100, 999) : null,
                    'holder' => $holders[array_rand($holders)],
                    'safe_id' => 'SAFE-' . rand(1000, 9999),
                    'safe_location' => 'Vault Room ' . chr(65 + rand(0, 4)) . ', Level ' . rand(1, 3),
                    'status' => $status,
                    'deposited_on' => Carbon::now()->subDays(rand(30, 730))->toDateString(),
                    'expires_on' => $status === 'expired' ? Carbon::now()->subDays(rand(1, 30))->toDateString() : Carbon::now()->addYears(rand(1, 5))->toDateString(),
                    'value' => rand(5000, 500000) / 100 * 100,
                    'document_count' => rand(1, 10),
                    'notes' => $status === 'sealed' ? 'Original documents in secure storage' : ($status === 'active' ? 'Currently in use' : ($status === 'released' ? 'Released to authorized party' : 'Expired - renewal required')),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Post Dated Cheques
    // ------------------------------------------------------------------

    private function seedPostDatedCheques(): void
    {
        if (PostDatedCheque::count() > 0) {
            $this->command->info('Post-dated cheques already exist — skipping seed.');
            return;
        }

        $leases = Lease::where('status', 'active')->get();
        $banks = ['Emirates NBD', 'Mashreq Bank', 'ADCB', 'FAB', 'DIB', 'RAK Bank', 'CBD', 'HSBC'];
        $statuses = ['pending', 'deposited', 'cleared', 'returned', 'replaced'];

        foreach ($leases as $idx => $lease) {
            $unit = $lease->unit;
            $tenant = $lease->tenant;
            $property = $unit?->building?->property;

            if (! $unit || ! $tenant || ! $property) continue;

            $chequeCount = rand(4, 12);
            for ($i = 1; $i <= $chequeCount; $i++) {
                $dueDate = Carbon::now()->addMonths($i - 1)->startOfMonth()->addDays(5);
                $status = $dueDate->isPast() ? $statuses[array_rand(array_slice($statuses, 1))] : 'pending';

                PostDatedCheque::create([
                    'cheque_no' => 'CHQ-' . rand(100000, 999999),
                    'bank' => $banks[array_rand($banks)],
                    'branch' => 'Main Branch',
                    'amount' => $lease->rent_amount,
                    'currency' => 'USD',
                    'payer' => $tenant->full_name,
                    'payer_company' => $tenant->company_name,
                    'payee' => $property->name,
                    'lease_code' => $lease->lease_number,
                    'unit_code' => $unit->unit_number,
                    'property_name' => $property->name,
                    'issued_on' => Carbon::now()->subMonths($chequeCount - $i + 1)->toDateString(),
                    'due_on' => $dueDate->toDateString(),
                    'deposited_on' => in_array($status, ['deposited', 'cleared']) ? $dueDate->copy()->addDays(rand(0, 2))->toDateString() : null,
                    'status' => $status,
                    'instalment_label' => 'Rent - ' . $dueDate->format('M Y'),
                    'bounce_case_code' => $status === 'returned' ? 'BC-' . rand(1000, 9999) : null,
                    'notes' => $status === 'returned' ? 'Insufficient funds' : null,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Lease Agreements
    // ------------------------------------------------------------------

    private function seedLeaseAgreements(): void
    {
        if (LeaseAgreement::count() > 0) {
            $this->command->info('Lease agreements already exist — skipping seed.');
            return;
        }

        $leases = Lease::where('status', 'active')->get();
        $templates = ['Standard Residential', 'Commercial Office', 'Retail', 'Industrial/Warehouse', 'Mixed Use'];
        $statuses = ['draft', 'sent', 'partially_signed', 'executed', 'expired'];

        foreach ($leases as $idx => $lease) {
            $unit = $lease->unit;
            $tenant = $lease->tenant;
            $property = $unit?->building?->property;

            if (! $unit || ! $tenant || ! $property) continue;

            $status = $idx % 5 === 0 ? 'draft' : ($idx % 5 === 1 ? 'sent' : ($idx % 5 === 2 ? 'partially_signed' : 'executed'));
            $hasEjari = $status === 'executed' && $idx % 3 !== 0;

            LeaseAgreement::create([
                'code' => 'LA-' . $lease->lease_number,
                'lease_code' => $lease->lease_number,
                'property_name' => $property->name,
                'unit_code' => $unit->unit_number,
                'tenant_name' => $tenant->full_name,
                'tenant_company' => $tenant->company_name,
                'type' => $templates[array_rand($templates)],
                'start_date' => $lease->start_date,
                'end_date' => $lease->end_date,
                'rent_amount' => $lease->rent_amount,
                'security_deposit' => $lease->deposit_amount,
                'payment_frequency' => $lease->payment_frequency,
                'template' => $templates[array_rand($templates)],
                'version' => rand(1, 3),
                'generated_on' => Carbon::parse($lease->start_date)->subDays(rand(5, 30))->toDateString(),
                'status' => $status,
                'landlord_signed_on' => in_array($status, ['partially_signed', 'executed']) ? Carbon::parse($lease->start_date)->subDays(rand(1, 10))->toDateString() : null,
                'tenant_signed_on' => $status === 'executed' ? Carbon::parse($lease->start_date)->subDays(rand(0, 5))->toDateString() : null,
                'witness_name' => $status === 'executed' ? 'Witness ' . ($idx + 1) : null,
                'ejari_number' => $hasEjari ? 'EJARI-' . rand(100000, 999999) : null,
                'document_size_kb' => rand(200, 2000),
                'notes' => $status === 'draft' ? 'Pending review' : ($status === 'sent' ? 'Sent for signature' : null),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Lease Renewals
    // ------------------------------------------------------------------

    private function seedLeaseRenewals(): void
    {
        if (LeaseRenewal::count() > 0) {
            $this->command->info('Lease renewals already exist — skipping seed.');
            return;
        }

        $leases = Lease::where('status', 'active')
            ->where('end_date', '>=', Carbon::now())
            ->where('end_date', '<=', Carbon::now()->addMonths(6))
            ->get();

        $statuses = ['draft', 'sent', 'under_negotiation', 'accepted', 'declined', 'expired'];

        foreach ($leases as $idx => $lease) {
            $unit = $lease->unit;
            $tenant = $lease->tenant;
            $property = $unit?->building?->property;

            if (! $unit || ! $tenant || ! $property) continue;

            $daysToExpiry = Carbon::parse($lease->end_date)->diffInDays(Carbon::now());
            $status = $statuses[array_rand($statuses)];
            $uplift = rand(3, 10);

            LeaseRenewal::create([
                'code' => 'LR-' . $lease->lease_number,
                'lease_code' => $lease->lease_number,
                'property_name' => $property->name,
                'unit_code' => $unit->unit_number,
                'tenant_name' => $tenant->full_name,
                'tenant_company' => $tenant->company_name,
                'current_rent' => $lease->rent_amount,
                'proposed_rent' => round($lease->rent_amount * (1 + $uplift / 100), 2),
                'uplift_percent' => $uplift,
                'expiry_date' => $lease->end_date,
                'days_to_expiry' => $daysToExpiry,
                'offer_sent_on' => in_array($status, ['sent', 'under_negotiation', 'accepted', 'declined']) ? Carbon::now()->subDays(rand(5, 30))->toDateString() : null,
                'response_due_on' => Carbon::now()->addDays(rand(15, 45))->toDateString(),
                'responded_on' => in_array($status, ['accepted', 'declined']) ? Carbon::now()->subDays(rand(1, 10))->toDateString() : null,
                'status' => $status,
                'term_months' => 12,
                'new_start_date' => Carbon::parse($lease->end_date)->addDay()->toDateString(),
                'new_end_date' => Carbon::parse($lease->end_date)->addYear()->toDateString(),
                'discount_percent' => $status === 'accepted' ? rand(0, 5) : 0,
                'owner_note' => $status === 'under_negotiation' ? 'Negotiating terms' : null,
                'notes' => $status === 'draft' ? 'Draft renewal offer' : null,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Service Charges
    // ------------------------------------------------------------------

    private function seedServiceCharges(): void
    {
        if (ServiceCharge::count() > 0) {
            $this->command->info('Service charges already exist — skipping seed.');
            return;
        }

        $properties = Property::where('status', 'active')->get();
        $categories = ['Maintenance', 'Cleaning', 'Security', 'Landscaping', 'Utilities', 'Insurance', 'Management Fee', 'Repairs', 'Common Area', 'Parking'];

        foreach ($properties as $prop) {
            $years = [date('Y'), date('Y') - 1];
            foreach ($years as $year) {
                foreach ($categories as $cat) {
                    $budgeted = rand(5000, 100000);
                    $actual = $budgeted + rand(-10000, 10000);
                    $status = ['draft', 'issued', 'partially_paid', 'paid', 'closed'][array_rand(['draft', 'issued', 'partially_paid', 'paid', 'closed'])];

                    ServiceCharge::create([
                        'code' => 'SC-' . $prop->code . '-' . $year . '-' . strtoupper(substr($cat, 0, 3)),
                        'property_id' => $prop->id,
                        'property_name' => $prop->name,
                        'period_year' => $year,
                        'category' => $cat,
                        'budgeted_amount' => $budgeted,
                        'actual_amount' => $actual,
                        'variance' => $actual - $budgeted,
                        'total_area_sqft' => $prop->total_area_sqft,
                        'rate_per_sqft' => $prop->total_area_sqft > 0 ? round($budgeted / $prop->total_area_sqft, 4) : 0,
                        'units_charged' => $prop->units()->count(),
                        'amount_collected' => $status === 'paid' ? $actual : ($status === 'partially_paid' ? round($actual * 0.6) : 0),
                        'outstanding' => $status === 'paid' ? 0 : ($status === 'partially_paid' ? round($actual * 0.4) : $actual),
                        'issued_on' => Carbon::create($year, 1, 1)->toDateString(),
                        'due_date' => Carbon::create($year, 3, 31)->toDateString(),
                        'status' => $status,
                        'approved_by' => $status !== 'draft' ? 'Property Manager' : null,
                        'notes' => $status === 'draft' ? 'Pending approval' : null,
                    ]);
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Escrow Accounts
    // ------------------------------------------------------------------

    private function seedEscrowAccounts(): void
    {
        if (EscrowAccount::count() > 0) {
            $this->command->info('Escrow accounts already exist — skipping seed.');
            return;
        }

        $properties = Property::where('status', 'active')->get();
        $banks = ['Emirates NBD', 'Mashreq Bank', 'ADCB', 'FAB', 'DIB', 'HSBC', 'Standard Chartered'];

        foreach ($properties as $prop) {
            $count = rand(1, 3);
            for ($i = 1; $i <= $count; $i++) {
                $opening = rand(50000, 500000);
                $deposits = rand(10000, 200000);
                $withdrawals = rand(5000, 150000);
                $status = ['active', 'frozen', 'closed'][array_rand(['active', 'frozen', 'closed'])];

                EscrowAccount::create([
                    'code' => 'ESC-' . $prop->code . '-' . $i,
                    'property_id' => $prop->id,
                    'property_name' => $prop->name,
                    'bank' => $banks[array_rand($banks)],
                    'iban' => 'AE' . rand(10, 99) . ' ' . rand(1000, 9999) . ' ' . rand(1000000000, 9999999999),
                    'account_number' => 'ACC-' . rand(10000000, 99999999),
                    'opening_balance' => $opening,
                    'deposits' => $deposits,
                    'withdrawals' => $withdrawals,
                    'closing_balance' => $opening + $deposits - $withdrawals,
                    'currency' => 'USD',
                    'authority_limit' => rand(50000, 1000000),
                    'status' => $status,
                    'opened_on' => Carbon::now()->subYears(rand(1, 5))->toDateString(),
                    'last_reconciled_on' => $status === 'active' ? Carbon::now()->subDays(rand(1, 30))->toDateString() : Carbon::now()->subMonths(rand(1, 6))->toDateString(),
                    'manager_name' => 'Escrow Manager ' . ($i + 1),
                    'notes' => $status === 'frozen' ? 'Account frozen pending audit' : ($status === 'closed' ? 'Property sold' : 'Active escrow account'),
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Ejari Contracts
    // ------------------------------------------------------------------

    private function seedEjariContracts(): void
    {
        if (EjariContract::count() > 0) {
            $this->command->info('Ejari contracts already exist — skipping seed.');
            return;
        }

        $leases = Lease::where('status', 'active')->get();
        $ejariTypes = ['residential', 'commercial', 'industrial', 'retail', 'mixed_use'];
        $statuses = ['draft', 'submitted', 'registered', 'rejected', 'cancelled'];

        foreach ($leases as $idx => $lease) {
            $unit = $lease->unit;
            $tenant = $lease->tenant;
            $property = $unit?->building?->property;

            if (! $unit || ! $tenant || ! $property) continue;

            // 60% chance of having an Ejari contract
            if ($idx % 5 >= 3) continue;

            $status = $idx % 7 === 0 ? 'draft' : ($idx % 7 === 1 ? 'submitted' : ($idx % 7 === 2 ? 'rejected' : 'registered'));
            $hasNumber = in_array($status, ['registered']);

            EjariContract::create([
                'code' => 'EJC-' . $lease->lease_number,
                'ejari_number' => $hasNumber ? 'EJARI-' . rand(1000000, 9999999) : null,
                'lease_code' => $lease->lease_number,
                'property_name' => $property->name,
                'unit_code' => $unit->unit_number,
                'landlord_name' => $property->name . ' LLC',
                'tenant_name' => $tenant->full_name,
                'tenant_company' => $tenant->company_name,
                'ejari_type' => $ejariTypes[array_rand($ejariTypes)],
                'registration_date' => in_array($status, ['submitted', 'registered', 'rejected']) ? Carbon::now()->subDays(rand(1, 60))->toDateString() : null,
                'certificate_issued_on' => $status === 'registered' ? Carbon::now()->subDays(rand(1, 30))->toDateString() : null,
                'expiry_date' => Carbon::parse($lease->end_date)->toDateString(),
                'status' => $status,
                'registration_fee' => rand(1000, 5000),
                'penalty_amount' => $status === 'rejected' ? rand(500, 2000) : 0,
                'deed_reference' => $status === 'registered' ? 'DEED-' . rand(10000, 99999) : null,
                'free_zone_property' => false,
                'attempts' => $status === 'rejected' ? rand(1, 3) : 1,
                'last_error' => $status === 'rejected' ? 'Document mismatch - tenancy contract not attested' : null,
                'notes' => $status === 'draft' ? 'Awaiting document preparation' : ($status === 'submitted' ? 'Submitted to Ejari portal' : ($status === 'rejected' ? 'Rejected - resubmission required' : 'Registered successfully')),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Fit Out Requests
    // ------------------------------------------------------------------

    private function seedFitOutRequests(): void
    {
        if (FitOutRequest::count() > 0) {
            $this->command->info('Fit-out requests already exist — skipping seed.');
            return;
        }

        $leases = Lease::where('status', 'active')->get();
        $permitStatuses = ['not_submitted', 'under_review', 'approved', 'rejected', 'expired'];
        $nocStatuses = ['pending', 'inspection_scheduled', 'noc_issued', 'noc_rejected'];
        $priorities = ['low', 'normal', 'high'];

        foreach ($leases as $idx => $lease) {
            $unit = $lease->unit;
            $tenant = $lease->tenant;
            $property = $unit?->building?->property;

            if (! $unit || ! $tenant || ! $property) continue;

            // 30% chance of having a fit-out request
            if ($idx % 10 >= 3) continue;

            $permitStatus = $permitStatuses[array_rand($permitStatuses)];
            $nocStatus = $permitStatus === 'approved' ? $nocStatuses[array_rand($nocStatuses)] : 'pending';

            FitOutRequest::create([
                'code' => 'FOR-' . $lease->lease_number,
                'property_name' => $property->name,
                'unit_code' => $unit->unit_number,
                'tenant_name' => $tenant->full_name,
                'tenant_company' => $tenant->company_name,
                'contractor_name' => 'Contractor ' . chr(65 + ($idx % 26)) . ' Construction',
                'contractor_trn' => 'TRN-' . rand(100000000, 999999999),
                'scope' => 'Interior fit-out including partitioning, electrical, plumbing, HVAC, and finishes.',
                'permit_status' => $permitStatus,
                'noc_status' => $nocStatus,
                'deposit_amount' => rand(10000, 100000),
                'deposit_status' => $permitStatus === 'approved' ? 'paid' : 'pending',
                'requested_on' => Carbon::now()->subDays(rand(10, 120))->toDateString(),
                'expected_completion' => Carbon::now()->addDays(rand(30, 180))->toDateString(),
                'inspection_date' => $nocStatus === 'inspection_scheduled' ? Carbon::now()->addDays(rand(1, 14))->toDateString() : null,
                'progress_percent' => $permitStatus === 'approved' ? rand(10, 90) : 0,
                'priority' => $priorities[array_rand($priorities)],
                'estimated_cost' => rand(50000, 500000),
                'owner_name' => $property->name . ' Management',
                'notes' => $permitStatus === 'not_submitted' ? 'Awaiting tenant submission' : ($permitStatus === 'under_review' ? 'Under authority review' : null),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Bounced Cheques
    // ------------------------------------------------------------------

    private function seedBouncedCheques(): void
    {
        if (BouncedCheque::count() > 0) {
            $this->command->info('Bounced cheques already exist — skipping seed.');
            return;
        }

        $returnedCheques = PostDatedCheque::where('status', 'returned')->get();
        $stages = ['new', 'notified', 'promise_to_pay', 'partially_recovered', 'recovered', 'escalated', 'legal_action', 'written_off'];
        $priorities = ['low', 'normal', 'high', 'critical'];
        $reasons = ['Insufficient funds', 'Account closed', 'Signature mismatch', 'Post-dated', 'Stop payment', 'Refer to drawer'];

        foreach ($returnedCheques as $idx => $cheque) {
            $stage = $stages[array_rand($stages)];
            $amount = (float) $cheque->amount;
            $recovered = in_array($stage, ['partially_recovered', 'recovered']) ? rand(1, (int)$amount) : 0;

            BouncedCheque::create([
                'code' => 'BC-' . $cheque->cheque_no,
                'cheque_no' => $cheque->cheque_no,
                'bank' => $cheque->bank,
                'amount' => $amount,
                'currency' => 'USD',
                'tenant_name' => $cheque->payer,
                'tenant_company' => $cheque->payer_company,
                'lease_code' => $cheque->lease_code,
                'unit_code' => $cheque->unit_code,
                'property_name' => $cheque->property_name,
                'bounced_on' => $cheque->due_on,
                'bounce_reason' => $reasons[array_rand($reasons)],
                'stage' => $stage,
                'recovered_amount' => $recovered,
                'outstanding' => $amount - $recovered,
                'recovery_percent' => $amount > 0 ? round(($recovered / $amount) * 100, 2) : 0,
                'actions_taken' => rand(0, 5),
                'next_action_due' => Carbon::now()->addDays(rand(1, 30))->toDateString(),
                'aging_days' => Carbon::parse($cheque->due_on)->diffInDays(Carbon::now()),
                'case_manager' => 'Collections Team ' . chr(65 + ($idx % 5)),
                'priority' => $priorities[array_rand($priorities)],
                'legal_notice_sent' => in_array($stage, ['escalated', 'legal_action', 'written_off']),
                'notes' => $stage === 'new' ? 'Initial bounce recorded' : ($stage === 'notified' ? 'Tenant notified' : ($stage === 'legal_action' ? 'Legal proceedings initiated' : null)),
            ]);
        }
    }
}

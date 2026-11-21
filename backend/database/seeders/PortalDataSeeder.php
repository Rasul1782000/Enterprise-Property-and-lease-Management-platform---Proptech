<?php

namespace Database\Seeders;

use App\Models\BouncedCheque;
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
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Builds the whole portal dataset from records in the database.
 *
 * Every tenant user owns one tenant record and one active lease on a distinct
 * unit. The portfolio is expanded so there are never more leases than units.
 * Run with: php artisan db:seed --class=PortalDataSeeder --force
 */
class PortalDataSeeder extends Seeder
{
    private const BUILDINGS_PER_PROPERTY = 10;

    private const UNITS_PER_BUILDING = 20;

    /**
     * @var array<int, array{id:int, building_id:int, unit_number:string, rent:float, is_residential:bool}>
     */
    private array $units = [];

    public function run(): void
    {
        if (App::environment('production')) {
            throw new RuntimeException('Portal data seeding is not allowed in production.');
        }

        $userCount = (int) env('PORTAL_SEED_USERS', 3000);
        $userCount = max(1, min(50000, $userCount));

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->resetTables();
            $managerIds = $this->seedStaff();
            $properties = $this->seedProperties($managerIds);
            $this->seedBuildings($properties);
            $this->seedUnits();
            $this->seedTenantsAndUsers($userCount);
            $this->seedLeases($userCount, $managerIds);
            $this->seedInvoicesAndPayments($userCount, $managerIds);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->command->info(sprintf(
            'Portal data seeded: %d users (%d tenant users), %d properties, %d buildings, %d units, %d tenants, %d leases, %d invoices, %d payments',
            User::count(),
            User::where('role', 'tenant')->count(),
            Property::count(),
            DB::table('buildings')->count(),
            Unit::count(),
            Tenant::count(),
            Lease::count(),
            Invoice::count(),
            Payment::count()
        ));
    }

    private function resetTables(): void
    {
        $tables = [
            'bounced_cheques',
            'fit_out_requests',
            'ejari_contracts',
            'escrow_accounts',
            'service_charges',
            'lease_renewals',
            'lease_agreements',
            'post_dated_cheques',
            'vault_assets',
            'invoice_line_items',
            'tenant_documents',
            'payments',
            'invoices',
            'leases',
            'tenants',
            'units',
            'buildings',
            'properties',
            'users',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
    }

    /**
     * @return array<int, int> Manager user ids.
     */
    private function seedStaff(): array
    {
        $now = Carbon::now()->toDateTimeString();
        $staff = [
            ['id' => 1, 'name' => 'Alexandra Reed', 'email' => 'admin@gaarbejana.com', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Nathan Cole', 'email' => 'manager.north@gaarbejana.com', 'role' => 'manager'],
            ['id' => 3, 'name' => 'Priya Nair', 'email' => 'manager.south@gaarbejana.com', 'role' => 'manager'],
            ['id' => 4, 'name' => 'Daniel Brooks', 'email' => 'accountant@gaarbejana.com', 'role' => 'accountant'],
            ['id' => 5, 'name' => 'Leila Haddad', 'email' => 'accounting@gaarbejana.com', 'role' => 'accountant'],
            ['id' => 6, 'name' => 'Marcus Webb', 'email' => 'agent@gaarbejana.com', 'role' => 'agent'],
        ];

        $rows = [];
        foreach ($staff as $person) {
            $rows[] = [
                'id' => $person['id'],
                'name' => $person['name'],
                'email' => $person['email'],
                'email_verified_at' => $now,
                'password' => password_hash(Str::random(32), PASSWORD_BCRYPT, ['cost' => 8]),
                'role' => $person['role'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('users')->insert($rows);

        return [2, 3];
    }

    /**
     * @param  array<int, int>  $managerIds
     * @return array<int, array{id:int, code:string, type:string, name:string, address:string, city:string, state:string, year:int, area:int}>
     */
    private function seedProperties(array $managerIds): array
    {
        $definitions = [
            ['Harbor Point Residences', 'Seattle', 'WA', 'multi_family', '1200 Harbor View Blvd', 2015, 125000],
            ['Cedar Grove Apartments', 'Portland', 'OR', 'multi_family', '845 Cedar Grove Ln', 2010, 82000],
            ['Meridian Business Park', 'Bellevue', 'WA', 'commercial', '4800 Meridian Pkwy', 2018, 210000],
            ['Sunset Retail Center', 'Tacoma', 'WA', 'commercial', '77 Sunset Highway', 2008, 65000],
            ['Northgate Industrial', 'Everett', 'WA', 'commercial', '2250 Northgate Way', 2020, 180000],
            ['Lakeview Terrace', 'Austin', 'TX', 'multi_family', '3100 Lakeview Dr', 2016, 112000],
            ['Barton Creek Offices', 'Austin', 'TX', 'commercial', '500 Barton Springs Rd', 2012, 148000],
            ['Riverbend Apartments', 'Denver', 'CO', 'multi_family', '1800 Riverbend Ave', 2014, 96000],
            ['Union Station Plaza', 'Denver', 'CO', 'mixed_use', '601 W 33rd Ave', 2019, 173000],
            ['Silverlake Commerce', 'Los Angeles', 'CA', 'commercial', '2800 Silverlake Blvd', 2011, 135000],
            ['Willow Court Homes', 'San Diego', 'CA', 'multi_family', '440 Willow Ct', 2017, 88000],
            ['Bayview Logistics', 'Oakland', 'CA', 'commercial', '900 Bayview Ave', 2021, 195000],
            ['Oakwood Residences', 'Chicago', 'IL', 'multi_family', '1250 Oakwood Ave', 2013, 105000],
            ['Wacker Tower', 'Chicago', 'IL', 'commercial', '333 W Wacker Dr', 2009, 225000],
            ['Cypress Point Shops', 'Phoenix', 'AZ', 'commercial', '7100 E Cypress St', 2015, 78000],
        ];

        $now = Carbon::now()->toDateTimeString();
        $properties = [];
        $rows = [];

        foreach ($definitions as $index => $definition) {
            [$name, $city, $state, $type, $address, $year, $area] = $definition;
            $name = ltrim($name);
            $code = 'PRP-'.(1001 + $index);
            $id = $index + 1;

            $properties[] = [
                'id' => $id,
                'code' => $code,
                'type' => $type,
                'name' => $name,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'year' => $year,
                'area' => $area,
            ];

            $rows[] = [
                'id' => $id,
                'name' => $name,
                'code' => $code,
                'type' => $type,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'zip' => str_pad((string) (10000 + ($index * 137) % 89999), 5, '0', STR_PAD_LEFT),
                'country' => 'USA',
                'total_area_sqft' => $area,
                'year_built' => $year,
                'manager_id' => $managerIds[$index % count($managerIds)],
                'description' => $type === 'multi_family'
                    ? 'Residential community with '.self::UNITS_PER_BUILDING * self::BUILDINGS_PER_PROPERTY.' homes in '.$city.'.'
                    : 'Commercial portfolio serving businesses in the '.$city.' market.',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('properties')->insert($rows);

        return $properties;
    }

    /**
     * @param  array<int, array{id:int, code:string, type:string, name:string, address:string, city:string, state:string, year:int, area:int}>  $properties
     */
    private function seedBuildings(array $properties): void
    {
        $now = Carbon::now()->toDateTimeString();
        $rows = [];
        $id = 1;

        foreach ($properties as $property) {
            for ($i = 0; $i < self::BUILDINGS_PER_PROPERTY; $i++) {
                $letter = chr(65 + $i);
                $rows[] = [
                    'id' => $id++,
                    'property_id' => $property['id'],
                    'name' => $property['name'].' - Building '.$letter,
                    'code' => 'BLD-'.$property['code'].'-'.$letter,
                    'floors' => 5,
                    'year_built' => $property['year'],
                    'construction_type' => $property['type'] === 'commercial' ? 'steel' : 'concrete',
                    'address' => $property['address'],
                    'city' => $property['city'],
                    'state' => $property['state'],
                    'zip' => str_pad((string) (10000 + (($property['id'] - 1) * 137) % 89999), 5, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('buildings')->insert($chunk);
        }
    }

    private function seedUnits(): void
    {
        $now = Carbon::now()->toDateTimeString();
        $properties = Property::orderBy('id')->get(['id', 'type'])->keyBy('id');
        $buildings = DB::table('buildings')->orderBy('id')->get(['id', 'property_id']);
        $rows = [];
        $id = 0;

        foreach ($buildings as $building) {
            $propertyType = $properties->get($building->property_id)?->type;
            $isResidential = $propertyType === 'multi_family';
            $type = $isResidential ? 'residential' : ($propertyType === 'mixed_use' ? 'retail' : 'office');

            for ($i = 1; $i <= self::UNITS_PER_BUILDING; $i++) {
                $id++;
                $unitNumber = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $rent = $isResidential ? 1500 + ($i % 6) * 175 : 3200 + ($i % 7) * 420;
                $amenities = $isResidential
                    ? ['Air conditioning', 'Dishwasher', 'Balcony']
                    : ['Loading dock', 'HVAC', '24/7 security'];

                $rows[] = [
                    'id' => $id,
                    'building_id' => $building->id,
                    'name' => ($isResidential ? 'Unit ' : 'Suite ').$i,
                    'unit_number' => $unitNumber,
                    'floor' => (int) ceil($i / 4),
                    'sqft' => $isResidential ? 650 + ($i % 5) * 90 : 1800 + ($i % 5) * 260,
                    'bedrooms' => $isResidential ? 1 + ($i % 3) : null,
                    'bathrooms' => $isResidential ? 1 + ($i % 2) : 2,
                    'rent_amount' => $rent,
                    'unit_type' => $type,
                    'status' => 'occupied',
                    'amenities' => json_encode($amenities),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $this->units[] = [
                    'id' => $id,
                    'building_id' => $building->id,
                    'unit_number' => $unitNumber,
                    'rent' => (float) $rent,
                    'is_residential' => $isResidential,
                ];
            }
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('units')->insert($chunk);
        }
    }

    private function seedTenantsAndUsers(int $count): void
    {
        $firstNames = [
            'Amelia', 'Noah', 'Priya', 'Lucas', 'Sofia', 'Ethan', 'Grace', 'Omar', 'Maya', 'Daniel',
            'Aisha', 'Liam', 'Elena', 'Mateo', 'Chloe', 'Yusuf', 'Nina', 'Julian', 'Fatima', 'Andre',
            'Isabella', 'Hamza', 'Mei', 'Diego', 'Zara', 'Felix', 'Hana', 'Rafael', 'Leila', 'Victor',
            'Clara', 'Ibrahim', 'Ava', 'Kwame', 'Ingrid', 'Tariq', 'Rose', 'Sebastian', 'Nadia', 'Theo',
            'Camila', 'Rashid', 'Lily', 'Jonas', 'Amina', 'Elijah', 'Freya', 'Karim', 'Olga', 'Miles',
        ];
        $lastNames = [
            'Hartley', 'Bennett', 'Raman', 'Moreau', 'Alvarez', 'Whitfield', 'Lindqvist', 'Haddad', 'Kowalski', 'Osei',
            'Rahman', 'Sullivan', 'Petrov', 'Silva', 'Dubois', 'Nakamura', 'Ibrahim', 'Costa', 'Novak', 'Mensah',
            'Fischer', 'Qureshi', 'Chen', 'Ortega', 'Bakker', 'Larsen', 'Iqbal', 'Reyes', 'Vasquez', 'Morgan',
            'Bauer', 'Sayed', 'Jensen', 'Boateng', 'Lindberg', 'Farouk', 'Hughes', 'Weber', 'Khan', 'Brooks',
            'Vega', 'Aziz', 'Flores', 'Berg', 'Malik', 'Cooper', 'Eriksen', 'Nasser', 'Sokolov', 'Turner',
        ];
        $companies = [
            'Legal Group', 'Consulting', 'Diagnostics', 'Design Studio', 'Logistics', 'Dental Care',
            'Trading Co.', 'Textiles', 'Bakery', 'Fitness Studio', 'Architecture', 'Pharmacy',
            'Technologies', 'Accounting', 'Real Estate', 'Engineering', 'Marketing', 'Clinic',
            'Manufacturing', 'Import Export', 'Software', 'Hospitality', 'Pharmaceuticals', 'Retail Group',
        ];

        $now = Carbon::now()->toDateTimeString();
        $tenantRows = [];
        $userRows = [];
        $userId = User::max('id') + 1;

        for ($i = 0; $i < $count; $i++) {
            $id = $i + 1;
            $first = $firstNames[$i % count($firstNames)];
            $last = $lastNames[($i * 7 + (int) floor($i / count($firstNames))) % count($lastNames)];
            $email = strtolower($first.'.'.$last.'.'.$id).'@tenants.gaarbejana.com';
            $company = $last.' '.$companies[$i % count($companies)];

            $tenantRows[] = [
                'id' => $id,
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'phone' => '+1 '.str_pad((string) (200 + ($i % 700)), 3, '0', STR_PAD_LEFT).' 555-'.str_pad((string) (1000 + ($i % 9000)), 4, '0', STR_PAD_LEFT),
                'company_name' => $company,
                'id_number' => 'TEN-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'tax_id' => 'TAX-'.str_pad((string) (1000000 + $id), 8, '0', STR_PAD_LEFT),
                'date_of_birth' => Carbon::now()->subYears(25 + ($i % 30))->subDays($i % 365)->toDateString(),
                'address' => (100 + ($i % 900)).' '.['Main St', 'Market Ave', 'Union Blvd', 'Cedar Ln'][$i % 4],
                'emergency_contact' => $last.' Family Contact',
                'emergency_contact_name' => $last.' Family Contact',
                'emergency_contact_phone' => '+1 206 555-'.str_pad((string) (1000 + (($i + 17) % 9000)), 4, '0', STR_PAD_LEFT),
                'code' => 'TNT-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $userRows[] = [
                'id' => $userId++,
                'name' => $first.' '.$last,
                'email' => $email,
                'email_verified_at' => $now,
                'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT, ['cost' => 8]),
                'role' => 'tenant',
                'tenant_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($tenantRows, 250) as $chunk) {
            DB::table('tenants')->insert($chunk);
        }

        foreach (array_chunk($userRows, 250) as $chunk) {
            DB::table('users')->insert($chunk);
        }
    }

    /**
     * @param  array<int, int>  $managerIds
     */
    private function seedLeases(int $count, array $managerIds): void
    {
        $now = Carbon::now()->toDateTimeString();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $unit = $this->units[$i] ?? null;
            if (! $unit) {
                break;
            }

            $end = Carbon::now()->startOfMonth()->addMonths(1 + ($i % 24))->day(28);
            $start = $end->copy()->subYear();
            $signed = $start->copy()->addDays(3);

            $rows[] = [
                'id' => $i + 1,
                'lease_number' => 'LS-'.Carbon::now()->year.'-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'unit_id' => $unit['id'],
                'tenant_id' => $i + 1,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'rent_amount' => $unit['rent'],
                'deposit_amount' => $unit['rent'],
                'late_fee_percent' => 2.5,
                'due_day' => 5,
                'status' => 'active',
                'payment_frequency' => 'monthly',
                'terms' => json_encode(['Tenant maintains interior', 'No subletting without written consent']),
                'type' => 'fixed',
                'escalation_clause' => $i % 3 === 0 ? '3% annual uplift on each anniversary' : null,
                'renewal_options' => 1 + ($i % 2),
                'signed_at' => $signed->toDateTimeString(),
                'created_by' => $managerIds[$i % count($managerIds)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('leases')->insert($chunk);
        }
    }

    /**
     * @param  array<int, int>  $managerIds
     */
    private function seedInvoicesAndPayments(int $count, array $managerIds): void
    {
        $now = Carbon::now()->toDateTimeString();
        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $dueDate = $periodStart->copy()->day(5);
        $label = $periodStart->format('F Y');

        $invoiceRows = [];
        $lineRows = [];
        $paymentRows = [];
        $paymentId = 1;
        $methods = ['bank_transfer', 'online', 'card', 'check'];

        for ($i = 0; $i < $count; $i++) {
            $unit = $this->units[$i] ?? null;
            if (! $unit) {
                break;
            }

            $invoiceId = $i + 1;
            $amount = $unit['rent'];
            $status = $i % 5 < 3 ? 'paid' : ($i % 5 === 3 ? 'sent' : 'overdue');
            $invoiceRows[] = [
                'id' => $invoiceId,
                'invoice_number' => 'INV-'.Carbon::now()->year.'-'.str_pad((string) $invoiceId, 5, '0', STR_PAD_LEFT),
                'lease_id' => $i + 1,
                'tenant_id' => $i + 1,
                'unit_id' => $unit['id'],
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'amount' => $amount,
                'late_fee' => $status === 'overdue' ? round($amount * 0.025, 2) : 0,
                'total_amount' => $status === 'overdue' ? round($amount * 1.025, 2) : $amount,
                'status' => $status,
                'type' => 'rent',
                'issue_date' => $periodStart->toDateString(),
                'currency' => 'USD',
                'sent_at' => $periodStart->copy()->addDay()->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $lineRows[] = [
                'invoice_id' => $invoiceId,
                'description' => 'Base rent - '.$label,
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($status === 'paid') {
                $paymentRows[] = [
                    'id' => $paymentId,
                    'payment_number' => 'PMT-'.Carbon::now()->year.'-'.str_pad((string) $paymentId, 5, '0', STR_PAD_LEFT),
                    'invoice_id' => $invoiceId,
                    'tenant_id' => $i + 1,
                    'amount' => $amount,
                    'method' => $methods[$i % count($methods)],
                    'reference' => 'REF-'.str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT),
                    'paid_at' => $periodStart->copy()->addDays(1 + ($i % 3))->toDateTimeString(),
                    'recorded_by' => $managerIds[$i % count($managerIds)],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $paymentId++;
            }
        }

        foreach (array_chunk($invoiceRows, 250) as $chunk) {
            DB::table('invoices')->insert($chunk);
        }
        foreach (array_chunk($lineRows, 250) as $chunk) {
            DB::table('invoice_line_items')->insert($chunk);
        }
        foreach (array_chunk($paymentRows, 250) as $chunk) {
            DB::table('payments')->insert($chunk);
        }
    }
}

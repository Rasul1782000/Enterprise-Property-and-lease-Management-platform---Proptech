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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (App::environment('production') || ! filter_var(env('DEMO_SEED_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Demo seeding is disabled. Enable DEMO_SEED_ENABLED only for a non-production database.');
        }

        Schema::disableForeignKeyConstraints();

        try {
            $this->resetSeededTables();
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
        } finally {
            Schema::enableForeignKeyConstraints();
        }

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

    /**
     * Ensure all seeded data comes from this run by clearing demo-owned tables.
     */
    private function resetSeededTables(): void
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
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }
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
        $manager = User::where('role', 'manager')->first();

        // The dashboard portfolio total must be exactly five properties.
        $properties = [
            ['name' => 'Harbor Point Residences', 'code' => 'PRP-1001', 'type' => 'multi_family', 'address' => '1200 Harbor View Blvd', 'city' => 'Seattle', 'state' => 'WA', 'zip' => '98101', 'country' => 'USA', 'total_area_sqft' => 125000, 'year_built' => 2015, 'status' => 'active', 'description' => 'Waterfront residential complex with panoramic views of Puget Sound. Features 20 units across three towers.'],
            ['name' => 'Cedar Grove Apartments', 'code' => 'PRP-1002', 'type' => 'multi_family', 'address' => '845 Cedar Grove Ln', 'city' => 'Portland', 'state' => 'OR', 'zip' => '97205', 'country' => 'USA', 'total_area_sqft' => 82000, 'year_built' => 2010, 'status' => 'active', 'description' => 'Garden-style apartment community with mature landscaping. 20 units in two low-rise buildings.'],
            ['name' => 'Meridian Business Park', 'code' => 'PRP-1003', 'type' => 'commercial', 'address' => '4800 Meridian Pkwy', 'city' => 'Bellevue', 'state' => 'WA', 'zip' => '98004', 'country' => 'USA', 'total_area_sqft' => 210000, 'year_built' => 2018, 'status' => 'active', 'description' => 'Class A office park with 20 premium office suites. LEED Gold certified.'],
            ['name' => 'Sunset Retail Center', 'code' => 'PRP-1004', 'type' => 'commercial', 'address' => '77 Sunset Highway', 'city' => 'Tacoma', 'state' => 'WA', 'zip' => '98402', 'country' => 'USA', 'total_area_sqft' => 65000, 'year_built' => 2008, 'status' => 'active', 'description' => 'Neighborhood retail center with 20 commercial units including anchor grocery tenant.'],
            ['name' => 'Northgate Industrial', 'code' => 'PRP-1005', 'type' => 'commercial', 'address' => '2250 Northgate Way', 'city' => 'Everett', 'state' => 'WA', 'zip' => '98201', 'country' => 'USA', 'total_area_sqft' => 180000, 'year_built' => 2020, 'status' => 'active', 'description' => 'Modern industrial warehouse facility with 20 high-bay units. Fully leased to logistics operators.'],
        ];

        foreach ($properties as $p) {
            $p['manager_id'] = $manager?->id;
            Property::updateOrCreate(['code' => $p['code']], $p);
        }
    }

    // ------------------------------------------------------------------
    // Buildings
    // ------------------------------------------------------------------

    private function seedBuildings(): void
    {
        $properties = Property::orderBy('id')->get();

        // One active building per property keeps the portfolio tree deterministic.
        foreach ($properties as $property) {
            Building::updateOrCreate(
                ['code' => 'BLD-'.$property->code],
                [
                    'property_id' => $property->id,
                    'name' => $property->name.' Building',
                    'floors' => 5,
                    'year_built' => $property->year_built,
                    'construction_type' => 'concrete',
                    'address' => $property->address,
                    'city' => $property->city,
                    'state' => $property->state,
                    'zip' => $property->zip,
                    'status' => 'active',
                    'description' => 'Primary building for '.$property->name.'.',
                ]
            );
        }
    }

    // ------------------------------------------------------------------
    // Units
    // ------------------------------------------------------------------

    private function seedUnits(): void
    {
        $buildings = Building::orderBy('id')->get();

        // Five buildings x 20 units = 100 units, all occupied (100% occupancy).
        foreach ($buildings as $building) {
            $property = $building->property;
            $isResidential = $property?->type === 'multi_family';

            for ($i = 1; $i <= 20; $i++) {
                $unitNumber = sprintf('%03d', $i);

                Unit::updateOrCreate(
                    [
                        'building_id' => $building->id,
                        'unit_number' => $unitNumber,
                    ],
                    [
                        'name' => ($isResidential ? 'Unit ' : 'Suite ').$i,
                        'floor' => (int) ceil($i / 4),
                        'sqft' => $isResidential ? 650 + ($i % 5) * 90 : 2200 + ($i % 5) * 260,
                        'bedrooms' => $isResidential ? 1 + ($i % 3) : 0,
                        'bathrooms' => $isResidential ? 1 + ($i % 2) : 2,
                        'rent_amount' => $isResidential ? 1500 + ($i % 6) * 175 : 4200 + ($i % 6) * 450,
                        'unit_type' => $isResidential ? 'apartment' : 'office',
                        'status' => 'occupied',
                        'amenities' => $isResidential
                            ? json_encode(['AC', 'Dishwasher', 'Balcony'])
                            : json_encode(['Loading Dock', 'HVAC', 'Security']),
                        'description' => null,
                    ]
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Tenants
    // ------------------------------------------------------------------

    private function seedTenants(): void
    {
        // Ten active tenants support exactly ten active leases.
        $tenantDefs = [
            ['first_name' => 'Amelia', 'last_name' => 'Hartley', 'company_name' => 'Hartley Legal Group'],
            ['first_name' => 'Noah', 'last_name' => 'Bennett', 'company_name' => 'Bennett & Co Consulting'],
            ['first_name' => 'Priya', 'last_name' => 'Raman', 'company_name' => 'Raman Diagnostics'],
            ['first_name' => 'Lucas', 'last_name' => 'Moreau', 'company_name' => 'Moreau Design Studio'],
            ['first_name' => 'Sofia', 'last_name' => 'Alvarez', 'company_name' => 'Alvarez Logistics'],
            ['first_name' => 'Ethan', 'last_name' => 'Whitfield', 'company_name' => 'Whitfield Dental'],
            ['first_name' => 'Grace', 'last_name' => 'Lindqvist', 'company_name' => 'Lindqvist Imports'],
            ['first_name' => 'Omar', 'last_name' => 'Haddad', 'company_name' => 'Haddad Textiles'],
            ['first_name' => 'Maya', 'last_name' => 'Kowalski', 'company_name' => 'Kowalski Bakery'],
            ['first_name' => 'Daniel', 'last_name' => 'Osei', 'company_name' => 'Osei Fitness'],
        ];

        foreach ($tenantDefs as $i => $t) {
            $email = strtolower($t['first_name'].'.'.$t['last_name']).'@example.com';
            $tenant = Tenant::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $t['first_name'],
                    'last_name' => $t['last_name'],
                    'phone' => '+1 (206) 555-'.str_pad((string) (1000 + $i * 7), 4, '0', STR_PAD_LEFT),
                    'company_name' => $t['company_name'],
                    'id_number' => 'ID'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                    'tax_id' => (string) (82 + $i).'-'.(4000000 + $i * 137),
                    'date_of_birth' => Carbon::now()->subYears(28 + ($i % 12))->toDateString(),
                    'address' => (1000 + $i * 17).' Main St',
                    'emergency_contact' => $t['last_name'].' Contact',
                    'emergency_contact_name' => $t['last_name'].' Contact',
                    'emergency_contact_phone' => '+1 (425) 555-'.str_pad((string) (2000 + $i * 3), 4, '0', STR_PAD_LEFT),
                    'status' => 'active',
                    'notes' => null,
                ]
            );

            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $tenant->full_name,
                    'password' => Hash::make(Str::random(64)),
                    'role' => 'tenant',
                    'tenant_id' => $tenant->id,
                ]
            );
        }
    }

    // ------------------------------------------------------------------
    // Leases
    // ------------------------------------------------------------------

    private function seedLeases(): void
    {
        $manager = User::where('role', 'manager')->first();
        $units = Unit::orderBy('id')->limit(10)->get();
        $tenants = Tenant::where('status', 'active')->orderBy('id')->limit(10)->get();

        // Exactly ten active leases for the dashboard.
        for ($i = 0; $i < 10; $i++) {
            $unit = $units[$i] ?? null;
            $tenant = $tenants[$i] ?? null;
            if (! $unit || ! $tenant) {
                continue;
            }

            $endOffset = 15 + ($i * 25);
            $start = Carbon::now()->subDays(350 - ($i * 20))->toDateString();

            Lease::updateOrCreate(
                ['lease_number' => 'LS-'.(5001 + $i)],
                [
                    'unit_id' => $unit->id,
                    'tenant_id' => $tenant->id,
                    'start_date' => $start,
                    'end_date' => Carbon::now()->addDays($endOffset)->toDateString(),
                    'rent_amount' => $unit->rent_amount,
                    'deposit_amount' => $unit->rent_amount,
                    'late_fee_percent' => 2.5,
                    'due_day' => 5,
                    'status' => 'active',
                    'payment_frequency' => 'monthly',
                    'terms' => ['Tenant maintains interior', 'No subletting without consent'],
                    'notes' => null,
                    'document_path' => null,
                    'created_by' => $manager?->id,
                    'type' => 'fixed',
                    'escalation_clause' => $i % 3 === 0 ? '3% annual increase on each anniversary' : null,
                    'renewal_options' => ($i % 2) + 1,
                    'signed_at' => Carbon::parse($start)->addDays(3)->toDateTimeString(),
                    'terminated_at' => null,
                ]
            );

            $unit->update(['status' => 'occupied']);
        }
    }

    // ------------------------------------------------------------------
    // Invoices
    // ------------------------------------------------------------------

    private function seedInvoices(): void
    {
        $leases = Lease::where('status', 'active')->orderBy('id')->limit(10)->get();

        $currentStart = Carbon::now()->startOfMonth();
        $currentEnd = Carbon::now()->endOfMonth();
        $previousStart = Carbon::now()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = Carbon::now()->subMonthNoOverflow()->endOfMonth();

        foreach ($leases as $idx => $lease) {
            // Ten invoices x 26,100.60 = 261,006 outstanding.
            Invoice::updateOrCreate(
                ['invoice_number' => 'INV-GA-'.str_pad((string) ($idx + 1), 5, '0', STR_PAD_LEFT)],
                [
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'unit_id' => $lease->unit_id,
                    'period_start' => $currentStart->toDateString(),
                    'period_end' => $currentEnd->toDateString(),
                    'due_date' => $currentStart->copy()->day(5)->toDateString(),
                    'amount' => 26100.60,
                    'late_fee' => 0,
                    'total_amount' => 26100.60,
                    'status' => $idx < 6 ? 'pending' : 'overdue',
                    'notes' => 'Seeded outstanding receivable',
                    'sent_at' => $currentStart->copy()->addDay()->toDateTimeString(),
                    'type' => 'rent',
                    'issue_date' => $currentStart->toDateString(),
                    'currency' => 'USD',
                ]
            );

            // Paid historical invoices feed the revenue chart without affecting outstanding.
            if ($idx < 5) {
                Invoice::updateOrCreate(
                    ['invoice_number' => 'INV-PAID-'.str_pad((string) ($idx + 1), 5, '0', STR_PAD_LEFT)],
                    [
                        'lease_id' => $lease->id,
                        'tenant_id' => $lease->tenant_id,
                        'unit_id' => $lease->unit_id,
                        'period_start' => $previousStart->toDateString(),
                        'period_end' => $previousEnd->toDateString(),
                        'due_date' => $previousStart->copy()->day(5)->toDateString(),
                        'amount' => 5000,
                        'late_fee' => 0,
                        'total_amount' => 5000,
                        'status' => 'paid',
                        'notes' => 'Seeded settled invoice',
                        'sent_at' => $previousStart->copy()->addDay()->toDateTimeString(),
                        'type' => 'rent',
                        'issue_date' => $previousStart->toDateString(),
                        'currency' => 'USD',
                    ]
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // Payments
    // ------------------------------------------------------------------

    private function seedPayments(): void
    {
        $accountant = User::where('role', 'accountant')->first();
        $methods = ['bank_transfer', 'check', 'online', 'card', 'cash'];
        $paidInvoices = Invoice::where('invoice_number', 'like', 'INV-PAID-%')->orderBy('id')->get();

        foreach ($paidInvoices as $i => $invoice) {
            Payment::updateOrCreate(
                ['payment_number' => 'PMT-GA-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)],
                [
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $invoice->tenant_id,
                    'amount' => (float) $invoice->total_amount,
                    'method' => $methods[$i % count($methods)],
                    'reference' => 'REF-GA-'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                    'paid_at' => Carbon::now()->startOfMonth()->addDays(3 + $i)->toDateTimeString(),
                    'receipt_pdf_path' => null,
                    'notes' => null,
                    'recorded_by' => $accountant?->id,
                ]
            );
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

            $daysToExpiry = abs((int) Carbon::parse($lease->end_date)->diffInDays(Carbon::now()));
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

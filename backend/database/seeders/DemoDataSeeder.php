<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        $admin = User::create(['name'=>'Admin','email'=>'admin@propertylease.test','password'=>Hash::make('password'),'role'=>'admin']);
        $manager = User::create(['name'=>'Sarah Manager','email'=>'manager@propertylease.test','password'=>Hash::make('password'),'role'=>'manager']);
        $accountant = User::create(['name'=>'Alex Accountant','email'=>'accountant@propertylease.test','password'=>Hash::make('password'),'role'=>'accountant']);

        // Tenants + tenant users
        $tenants = collect();
        foreach (range(1,12) as $i) {
            $t = Tenant::create([
                'first_name'=>fake()->firstName(),
                'last_name'=>fake()->lastName(),
                'email'=>fake()->unique()->safeEmail(),
                'phone'=>fake()->phoneNumber(),
                'company_name'=> $i % 2 ? fake()->company() : null,
                'id_number'=>'ID'.str_pad($i,6,'0',STR_PAD_LEFT),
                'status'=>'active',
            ]);
            // tenant portal user
            User::create(['name'=>$t->full_name,'email'=>$t->email,'password'=>Hash::make('password'),'role'=>'tenant','tenant_id'=>$t->id]);
            $tenants->push($t);
        }

        // Properties
        $properties = collect();
        $cities = ['New York','Austin','San Francisco','Chicago'];
        foreach (range(1,4) as $i) {
            $prop = Property::create([
                'name'=> fake()->unique()->company().' Plaza',
                'code'=>'PROP-'.str_pad($i,3,'0',STR_PAD_LEFT),
                'type'=> fake()->randomElement(['commercial','multi_family','mixed_use']),
                'address'=> fake()->streetAddress(),
                'city'=> $cities[$i-1],
                'state'=> fake()->stateAbbr(),
                'zip'=> fake()->postcode(),
                'manager_id'=> $manager->id,
                'total_area_sqft'=> fake()->numberBetween(20000,120000),
                'year_built'=> fake()->numberBetween(1995,2023),
                'status'=>'active',
                'description'=> fake()->paragraph(),
            ]);
            $properties->push($prop);

            // Buildings per property
            $buildingCount = rand(1,3);
            foreach (range(1,$buildingCount) as $b) {
                $building = Building::create([
                    'property_id'=>$prop->id,
                    'name'=> chr(64+$b).' Tower',
                    'code'=> $prop->code.'-B'.$b,
                    'floors'=> fake()->numberBetween(3,12),
                    'year_built'=> $prop->year_built,
                    'description'=> fake()->sentence(),
                ]);

                // Units per building
                $unitCount = rand(6,12);
                foreach (range(1,$unitCount) as $u) {
                    Unit::create([
                        'building_id'=>$building->id,
                        'unit_number'=> $building->floors>1 ? sprintf('%d%02d', rand(1,$building->floors), $u) : (100+$u),
                        'floor'=> rand(1,$building->floors),
                        'sqft'=> fake()->numberBetween(400,3500),
                        'bedrooms'=> fake()->numberBetween(0,3),
                        'bathrooms'=> fake()->numberBetween(1,2),
                        'rent_amount'=> fake()->numberBetween(1200,7500),
                        'unit_type'=> fake()->randomElement(['office','retail','apartment','studio']),
                        'status'=> 'vacant',
                    ]);
                }
            }
        }

        // Leases + Invoices + Payments
        $units = Unit::inRandomOrder()->take(10)->get();
        foreach ($units as $idx=>$unit) {
            $tenant = $tenants[$idx];
            $lease = Lease::create([
                'unit_id'=>$unit->id,
                'tenant_id'=>$tenant->id,
                'start_date'=> now()->subMonths(rand(2,6))->startOfMonth(),
                'end_date'=> now()->addMonths(rand(6,14))->endOfMonth(),
                'rent_amount'=>$unit->rent_amount,
                'deposit_amount'=> $unit->rent_amount,
                'status'=>'active',
                'due_day'=>5,
                'payment_frequency'=>'monthly',
                'created_by'=> $manager->id,
                'terms'=> ['Tenant maintains interior', 'No subletting without consent'],
            ]);
            $unit->update(['status'=>'occupied']);

            // Generate 3 months invoices backward
            foreach (range(0,2) as $m) {
                $periodStart = now()->subMonths($m)->startOfMonth();
                $periodEnd = now()->subMonths($m)->endOfMonth();
                $dueDate = $periodStart->copy()->day(5);
                $status = $m===0 ? 'pending' : (rand(0,1) ? 'paid' : 'pending');
                if ($m===2 && rand(0,1)) $status='overdue';

                $invoice = Invoice::create([
                    'lease_id'=>$lease->id,
                    'tenant_id'=>$tenant->id,
                    'unit_id'=>$unit->id,
                    'period_start'=>$periodStart,
                    'period_end'=>$periodEnd,
                    'due_date'=>$dueDate,
                    'amount'=>$lease->rent_amount,
                    'late_fee'=> $status==='overdue' ? round($lease->rent_amount*0.025,2) : 0,
                    'total_amount'=> $status==='overdue' ? $lease->rent_amount*1.025 : $lease->rent_amount,
                    'status'=>$status,
                ]);

                if ($status==='paid') {
                    Payment::create([
                        'invoice_id'=>$invoice->id,
                        'tenant_id'=>$tenant->id,
                        'amount'=>$invoice->total_amount,
                        'method'=> fake()->randomElement(['bank_transfer','card','cash']),
                        'reference'=> 'REF'.fake()->bothify('###???'),
                        'paid_at'=> $dueDate->copy()->addDays(rand(-2,2)),
                        'recorded_by'=> $accountant->id,
                    ]);
                }
            }
        }

        $this->command->info('Demo data seeded: '.Property::count().' properties, '.Building::count().' buildings, '.Unit::count().' units, '.Tenant::count().' tenants, '.Lease::count().' leases.');
    }
}

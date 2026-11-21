<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;

class BootstrapPortalData extends Command
{
    protected $signature = 'portal:bootstrap';

    protected $description = 'Apply migrations and seed a small demo dataset when the portal has no properties.';

    public function handle(): int
    {
        $this->call('migrate', [
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if (Property::count() > 0) {
            $this->info('Portal data already present; skipping seed.');

            return self::SUCCESS;
        }

        $_ENV['PORTAL_ALLOW_PRODUCTION_SEED'] = 'true';
        $_SERVER['PORTAL_ALLOW_PRODUCTION_SEED'] = 'true';
        putenv('PORTAL_ALLOW_PRODUCTION_SEED=true');

        $this->call('db:seed', [
            '--class' => 'PortalDataSeeder',
            '--force' => true,
            '--no-interaction' => true,
        ]);

        $this->info('Portal bootstrap complete.');

        return self::SUCCESS;
    }
}

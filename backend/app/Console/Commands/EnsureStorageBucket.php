<?php

namespace App\Console\Commands;

use App\Support\Storage\S3ClientFactory;
use Illuminate\Console\Command;
use Throwable;

class EnsureStorageBucket extends Command
{
    protected $signature = 'storage:ensure-bucket {--disk=documents : The filesystem disk whose bucket should exist}';

    protected $description = 'Create the S3 bucket for a disk if it does not exist (idempotent; works against AWS and Floci)';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $endpoint = S3ClientFactory::endpoint($disk);

        try {
            $bucket = S3ClientFactory::bucket($disk);
            $created = S3ClientFactory::ensureBucket($disk);
        } catch (Throwable $e) {
            $this->error("Could not reach storage endpoint [{$endpoint}]: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($created) {
            $this->info("Created bucket [{$bucket}] on disk [{$disk}] at {$endpoint}.");
        } else {
            $this->info("Bucket [{$bucket}] already exists on disk [{$disk}] at {$endpoint}.");
        }

        return self::SUCCESS;
    }
}

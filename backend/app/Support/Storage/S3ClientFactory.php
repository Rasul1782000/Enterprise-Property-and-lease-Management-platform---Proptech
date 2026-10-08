<?php

namespace App\Support\Storage;

use Aws\S3\S3Client;
use RuntimeException;
use Throwable;

class S3ClientFactory
{

    private static array $reachability = [];


    public static function forDisk(string $disk = 'documents', array $overrides = []): S3Client
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 's3') {
            throw new RuntimeException("Filesystem disk [{$disk}] is not configured as an s3 disk.");
        }

        $options = [
            'region' => $config['region'] ?? 'us-east-1',
            'version' => 'latest',

            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
        ];



        if (! empty($config['endpoint'])) {
            $options['endpoint'] = $config['endpoint'];
        }

        if (! empty($config['key']) && ! empty($config['secret'])) {
            $options['credentials'] = [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ];
        }

        return new S3Client($options + $overrides);
    }


    public static function bucket(string $disk = 'documents'): string
    {
        $bucket = config("filesystems.disks.{$disk}.bucket");

        if (empty($bucket)) {
            throw new RuntimeException("No bucket is configured for filesystem disk [{$disk}].");
        }

        return $bucket;
    }


    public static function ensureBucket(string $disk = 'documents'): bool
    {
        $client = self::forDisk($disk);
        $bucket = self::bucket($disk);

        if ($client->doesBucketExist($bucket)) {
            return false;
        }

        $arguments = ['Bucket' => $bucket];


        $region = config("filesystems.disks.{$disk}.region");
        if ($region && $region !== 'us-east-1') {
            $arguments['CreateBucketConfiguration'] = ['LocationConstraint' => $region];
        }

        $client->createBucket($arguments);

        return true;
    }


    public static function flushReachabilityCache(): void
    {
        self::$reachability = [];
    }


    public static function probeEndpoint(?string $endpoint, ?string $region = null, float $timeoutSeconds = 3.0): bool
    {
        try {
            $client = new S3Client([
                'region' => $region ?: 'us-east-1',
                'version' => 'latest',
                'use_path_style_endpoint' => true,
                'credentials' => ['key' => 'test', 'secret' => 'test'],


                'retries' => 0,
                'http' => [
                    'connect_timeout' => $timeoutSeconds,
                    'timeout' => $timeoutSeconds,
                ],


                'endpoint' => $endpoint ?: null,
            ]);

            $client->listBuckets();

            return true;
        } catch (Throwable) {
            return false;
        }
    }


    public static function isReachable(string $disk = 'documents', float $timeoutSeconds = 3.0): bool
    {

        $endpoint = (string) (config("filesystems.disks.{$disk}.endpoint") ?: getenv('AWS_ENDPOINT_URL') ?: 'aws');

        if (array_key_exists($endpoint, self::$reachability)) {
            return self::$reachability[$endpoint];
        }

        try {
            self::forDisk($disk, [



                'retries' => 0,
                'http' => [
                    'connect_timeout' => $timeoutSeconds,
                    'timeout' => $timeoutSeconds,
                ],
            ])->listBuckets();

            return self::$reachability[$endpoint] = true;
        } catch (Throwable) {
            return self::$reachability[$endpoint] = false;
        }
    }


    public static function endpoint(string $disk = 'documents'): string
    {
        return config("filesystems.disks.{$disk}.endpoint") ?: 'aws (region '.config("filesystems.disks.{$disk}.region").')';
    }
}

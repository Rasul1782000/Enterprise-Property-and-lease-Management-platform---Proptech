<?php

namespace App\Support\Storage;

use Aws\S3\S3Client;
use RuntimeException;
use Throwable;

/**
 * Builds a raw AWS S3 client from a Laravel filesystem disk definition.
 *
 * Flysystem's S3 adapter is enough to read and write objects, but bucket
 * management (HeadBucket / CreateBucket) needs the underlying SDK, so this
 * exists to bridge from `config/filesystems.php` to Aws\S3\S3Client.
 *
 * Everything here works unchanged against real AWS and against Floci, because
 * the only difference between the two is the endpoint URL.
 */
class S3ClientFactory
{
    /**
     * Memoised reachability results, keyed by endpoint.
     *
     * @var array<string, bool>
     */
    private static array $reachability = [];

    /**
     * Build an S3 client for the given filesystem disk.
     *
     * @param  array<string, mixed>  $overrides  Extra SDK options, merged with
     *                                           lowest precedence.
     */
    public static function forDisk(string $disk = 'documents', array $overrides = []): S3Client
    {
        $config = config("filesystems.disks.{$disk}");

        if (! is_array($config) || ($config['driver'] ?? null) !== 's3') {
            throw new RuntimeException("Filesystem disk [{$disk}] is not configured as an s3 disk.");
        }

        $options = [
            'region' => $config['region'] ?? 'us-east-1',
            'version' => 'latest',
            // Required by Floci and other S3-compatible emulators.
            'use_path_style_endpoint' => (bool) ($config['use_path_style_endpoint'] ?? true),
        ];

        // When AWS_ENDPOINT_URL is set (development/CI) this targets the
        // emulator; when it is null the SDK resolves the real AWS endpoint.
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

    /**
     * The bucket configured for the given disk.
     */
    public static function bucket(string $disk = 'documents'): string
    {
        $bucket = config("filesystems.disks.{$disk}.bucket");

        if (empty($bucket)) {
            throw new RuntimeException("No bucket is configured for filesystem disk [{$disk}].");
        }

        return $bucket;
    }

    /**
     * Create the bucket if it does not already exist.
     *
     * @return bool True when the bucket was created, false when it already existed.
     */
    public static function ensureBucket(string $disk = 'documents'): bool
    {
        $client = self::forDisk($disk);
        $bucket = self::bucket($disk);

        if ($client->doesBucketExist($bucket)) {
            return false;
        }

        $arguments = ['Bucket' => $bucket];

        // us-east-1 must not carry a LocationConstraint; other regions require it.
        $region = config("filesystems.disks.{$disk}.region");
        if ($region && $region !== 'us-east-1') {
            $arguments['CreateBucketConfiguration'] = ['LocationConstraint' => $region];
        }

        $client->createBucket($arguments);

        return true;
    }

    /**
     * Forget memoised reachability results. Only needed when tests change the
     * configured endpoint mid-process.
     */
    public static function flushReachabilityCache(): void
    {
        self::$reachability = [];
    }

    /**
     * Probe an endpoint without needing a booted Laravel application.
     *
     * Used by the test suite's setUpBeforeClass, which runs before any test
     * application exists. Booting one just to answer "is Floci up?" would swap
     * out PHP's error handlers and make PHPUnit flag every test as risky.
     */
    public static function probeEndpoint(?string $endpoint, ?string $region = null, float $timeoutSeconds = 3.0): bool
    {
        try {
            $client = new S3Client([
                'region' => $region ?: 'us-east-1',
                'version' => 'latest',
                'use_path_style_endpoint' => true,
                'credentials' => ['key' => 'test', 'secret' => 'test'],
                // Fail fast: the default retry budget turns an unreachable
                // endpoint into a multi-minute stall.
                'retries' => 0,
                'http' => [
                    'connect_timeout' => $timeoutSeconds,
                    'timeout' => $timeoutSeconds,
                ],
                // Null endpoint lets the SDK fall back to AWS_ENDPOINT_URL and
                // then to the real AWS endpoint for the region.
                'endpoint' => $endpoint ?: null,
            ]);

            $client->listBuckets();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether the configured endpoint answers at all. Used to skip
     * emulator-backed tests on machines that are not running Floci.
     */
    public static function isReachable(string $disk = 'documents', float $timeoutSeconds = 3.0): bool
    {
        // Cache per endpoint so a suite of tests probes once, not once per test.
        $endpoint = (string) (config("filesystems.disks.{$disk}.endpoint") ?: getenv('AWS_ENDPOINT_URL') ?: 'aws');

        if (array_key_exists($endpoint, self::$reachability)) {
            return self::$reachability[$endpoint];
        }

        try {
            self::forDisk($disk, [
                // A reachability probe must fail fast. Without this the SDK's
                // default retries stretch an unreachable-endpoint check out to
                // well over a minute.
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

    /**
     * The endpoint in use, for logging and error messages.
     */
    public static function endpoint(string $disk = 'documents'): string
    {
        return config("filesystems.disks.{$disk}.endpoint") ?: 'aws (region '.config("filesystems.disks.{$disk}.region").')';
    }
}

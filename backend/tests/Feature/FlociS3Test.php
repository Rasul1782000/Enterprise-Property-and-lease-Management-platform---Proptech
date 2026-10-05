<?php

namespace Tests\Feature;

use App\Support\Storage\S3ClientFactory;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end verification that the application really talks to the Floci
 * emulator over S3.
 *
 * These tests are skipped when no endpoint is reachable, so a developer
 * without Floci running still gets a green suite. In CI the Jenkinsfile
 * starts Floci first, so they execute for real.
 *
 * Run the whole suite against Floci with:
 *   AWS_ENDPOINT_URL=http://localhost:4566 php artisan test
 */
class FlociS3Test extends TestCase
{
    private const TEST_BUCKET = 'property-lease-ci-tests';

    /**
     * Skip the whole class before any test runs when no emulator is reachable.
     *
     * Doing this in setUpBeforeClass rather than setUp keeps PHPUnit reporting a
     * clean "skipped" instead of flagging every test as risky, because the
     * Laravel application is never torn down mid-test.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Probe directly from the environment. No Laravel application is booted
        // here, so PHP's error handlers stay untouched and PHPUnit does not
        // report the tests in this class as risky.
        $endpoint = getenv('AWS_ENDPOINT_URL') ?: null;

        if (! S3ClientFactory::probeEndpoint($endpoint, getenv('AWS_DEFAULT_REGION') ?: null)) {
            self::markTestSkipped(
                'No S3 endpoint reachable at ['.($endpoint ?: 'AWS_ENDPOINT_URL unset').']. '
                .'Start Floci (docker run -p 4566:4566 floci/floci:latest) to run these.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A dedicated bucket so cleanup can never touch development data.
        config([
            'filesystems.disks.documents.bucket' => self::TEST_BUCKET,
        ]);

        S3ClientFactory::flushReachabilityCache();
        S3ClientFactory::ensureBucket();
    }

    protected function tearDown(): void
    {
        try {
            $client = S3ClientFactory::forDisk();
            $bucket = config('filesystems.disks.documents.bucket');

            // Empty the bucket before removing it.
            $objects = $client->listObjectsV2(['Bucket' => $bucket]);
            if (($objects['KeyCount'] ?? 0) > 0) {
                $client->deleteObjects([
                    'Bucket' => $bucket,
                    'Delete' => [
                        'Objects' => array_map(
                            fn ($o) => ['Key' => $o['Key']],
                            $objects['Contents']
                        ),
                    ],
                ]);
            }

            $client->deleteBucket(['Bucket' => $bucket]);
        } catch (\Throwable) {
            // Cleanup is best-effort; a leaked emulator bucket is harmless.
        }

        parent::tearDown();
    }

    /** @test */
    public function it_reaches_the_emulator_and_lists_buckets(): void
    {
        $buckets = S3ClientFactory::forDisk()->listBuckets();

        $this->assertIsArray($buckets['Buckets']);
    }

    /** @test */
    public function ensure_bucket_is_idempotent(): void
    {
        $this->assertFalse(
            S3ClientFactory::ensureBucket(),
            'Bucket already existed, so ensureBucket() must report no creation.'
        );

        $this->assertTrue(
            S3ClientFactory::forDisk()->doesBucketExist(self::TEST_BUCKET)
        );
    }

    /** @test */
    public function it_writes_and_reads_an_object(): void
    {
        $path = 'ci/hello.txt';
        Storage::disk('documents')->put($path, 'hello floci');

        $this->assertTrue(Storage::disk('documents')->exists($path));
        $this->assertSame('hello floci', Storage::disk('documents')->get($path));
    }

    /** @test */
    public function it_persists_binary_content_intact(): void
    {
        // A real PDF header, to prove bytes are not mangled in transit.
        $bytes = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $path = 'ci/sample.pdf';

        Storage::disk('documents')->put($path, $bytes);

        $this->assertSame($bytes, Storage::disk('documents')->get($path));
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($path));
    }

    /** @test */
    public function it_deletes_an_object(): void
    {
        $path = 'ci/temporary.txt';
        Storage::disk('documents')->put($path, 'delete me');

        $this->assertTrue(Storage::disk('documents')->delete($path));
        $this->assertFalse(Storage::disk('documents')->exists($path));
    }

    /** @test */
    public function it_overwrites_an_existing_object(): void
    {
        $path = 'ci/overwrite.txt';

        Storage::disk('documents')->put($path, 'first');
        Storage::disk('documents')->put($path, 'second');

        $this->assertSame('second', Storage::disk('documents')->get($path));
    }

    /** @test */
    public function it_applies_the_configured_key_prefix(): void
    {
        config(['filesystems.disks.documents.root' => 'portal']);

        Storage::disk('documents')->put('ci/prefixed.txt', 'x');

        // With root=portal the key on the wire is portal/ci/prefixed.txt.
        $listing = S3ClientFactory::forDisk()->listObjectsV2([
            'Bucket' => self::TEST_BUCKET,
            'Prefix' => 'portal/',
        ]);

        $keys = array_column($listing['Contents'] ?? [], 'Key');
        $this->assertContains('portal/ci/prefixed.txt', $keys);
    }

    /** @test */
    public function it_lists_objects_under_a_prefix(): void
    {
        Storage::disk('documents')->put('ci/a.txt', 'a');
        Storage::disk('documents')->put('ci/b.txt', 'b');
        Storage::disk('documents')->put('other/c.txt', 'c');

        $listing = Storage::disk('documents')->files('ci');

        $this->assertCount(2, $listing);
        $this->assertContains('ci/a.txt', $listing);
        $this->assertContains('ci/b.txt', $listing);
    }

    /** @test */
    public function it_reports_a_missing_object(): void
    {
        $this->assertFalse(Storage::disk('documents')->exists('ci/never-written.txt'));
    }
}

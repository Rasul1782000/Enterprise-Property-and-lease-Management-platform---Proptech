<?php

namespace Tests\Feature;

use App\Support\Storage\S3ClientFactory;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FlociS3Test extends TestCase
{
    private const TEST_BUCKET = 'property-lease-ci-tests';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

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
        }

        parent::tearDown();
    }

    #[Test]
    public function it_reaches_the_emulator_and_lists_buckets(): void
    {
        $buckets = S3ClientFactory::forDisk()->listBuckets();

        $this->assertIsArray($buckets['Buckets']);
    }

    #[Test]
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

    #[Test]
    public function it_writes_and_reads_an_object(): void
    {
        $path = 'ci/hello.txt';
        Storage::disk('documents')->put($path, 'hello floci');

        $this->assertTrue(Storage::disk('documents')->exists($path));
        $this->assertSame('hello floci', Storage::disk('documents')->get($path));
    }

    #[Test]
    public function it_persists_binary_content_intact(): void
    {
        $bytes = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $path = 'ci/sample.pdf';

        Storage::disk('documents')->put($path, $bytes);

        $this->assertSame($bytes, Storage::disk('documents')->get($path));
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($path));
    }

    #[Test]
    public function it_deletes_an_object(): void
    {
        $path = 'ci/temporary.txt';
        Storage::disk('documents')->put($path, 'delete me');

        $this->assertTrue(Storage::disk('documents')->delete($path));
        $this->assertFalse(Storage::disk('documents')->exists($path));
    }

    #[Test]
    public function it_overwrites_an_existing_object(): void
    {
        $path = 'ci/overwrite.txt';

        Storage::disk('documents')->put($path, 'first');
        Storage::disk('documents')->put($path, 'second');

        $this->assertSame('second', Storage::disk('documents')->get($path));
    }

    #[Test]
    public function it_applies_the_configured_key_prefix(): void
    {
        config(['filesystems.disks.documents.root' => 'portal']);

        Storage::disk('documents')->put('ci/prefixed.txt', 'x');

        $listing = S3ClientFactory::forDisk()->listObjectsV2([
            'Bucket' => self::TEST_BUCKET,
            'Prefix' => 'portal/',
        ]);

        $keys = array_column($listing['Contents'] ?? [], 'Key');
        $this->assertContains('portal/ci/prefixed.txt', $keys);
    }

    #[Test]
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

    #[Test]
    public function it_reports_a_missing_object(): void
    {
        $this->assertFalse(Storage::disk('documents')->exists('ci/never-written.txt'));
    }
}

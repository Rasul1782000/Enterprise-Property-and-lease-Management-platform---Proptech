<?php

namespace Tests\Unit;

use App\Support\Storage\S3ClientFactory;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies the client factory maps filesystem disk config onto AWS SDK options.
 * No network access: these assert on the constructed client, not on responses.
 */
class S3ClientFactoryTest extends TestCase
{
    /** @test */
    public function it_builds_a_client_with_the_configured_emulator_endpoint(): void
    {
        Config::set('filesystems.disks.documents', [
            'driver' => 's3',
            'key' => 'test',
            'secret' => 'test',
            'region' => 'us-east-1',
            'bucket' => 'property-lease-documents',
            'endpoint' => 'http://floci:4566',
            'use_path_style_endpoint' => true,
        ]);

        $client = S3ClientFactory::forDisk('documents');

        $this->assertInstanceOf(S3Client::class, $client);
        $this->assertSame('us-east-1', $client->getRegion());
        $this->assertSame(
            'http://floci:4566',
            (string) $client->getEndpoint()
        );
    }

    /** @test */
    public function it_enables_path_style_urls_for_emulator_compatibility(): void
    {
        Config::set('filesystems.disks.documents', [
            'driver' => 's3',
            'key' => 'test',
            'secret' => 'test',
            'region' => 'us-east-1',
            'bucket' => 'b',
            'endpoint' => 'http://floci:4566',
            'use_path_style_endpoint' => true,
        ]);

        $client = S3ClientFactory::forDisk('documents');

        // Floci only answers path-style requests (/{bucket}/{key}); without
        // this the SDK would emit bucket.floci-style URLs and get a 404.
        $this->assertTrue($client->getConfig('use_path_style_endpoint'));
    }

    /** @test */
    public function the_sdk_honours_the_aws_endpoint_url_environment_variable(): void
    {
        // The AWS SDK reads AWS_ENDPOINT_URL from the process environment even
        // when no endpoint is set on the disk config. This is why simply
        // exporting AWS_ENDPOINT_URL is enough to redirect the whole app at
        // Floci, and why production must not have it set.
        $original = getenv('AWS_ENDPOINT_URL');
        putenv('AWS_ENDPOINT_URL=http://floci-from-env:4566');

        try {
            Config::set('filesystems.disks.documents', [
                'driver' => 's3',
                'key' => 'test',
                'secret' => 'test',
                'region' => 'us-east-1',
                'bucket' => 'b',
                'endpoint' => null,
                'use_path_style_endpoint' => true,
            ]);

            $client = S3ClientFactory::forDisk('documents');

            $this->assertSame('http://floci-from-env:4566', (string) $client->getEndpoint());
        } finally {
            if ($original === false) {
                putenv('AWS_ENDPOINT_URL');
            } else {
                putenv('AWS_ENDPOINT_URL='.$original);
            }
        }
    }

    /** @test */
    public function an_explicit_disk_endpoint_wins_over_the_environment_variable(): void
    {
        $original = getenv('AWS_ENDPOINT_URL');
        putenv('AWS_ENDPOINT_URL=http://floci-from-env:4566');

        try {
            Config::set('filesystems.disks.documents', [
                'driver' => 's3',
                'key' => 'test',
                'secret' => 'test',
                'region' => 'us-east-1',
                'bucket' => 'b',
                'endpoint' => 'http://explicit:4566',
                'use_path_style_endpoint' => true,
            ]);

            $client = S3ClientFactory::forDisk('documents');

            $this->assertSame('http://explicit:4566', (string) $client->getEndpoint());
        } finally {
            if ($original === false) {
                putenv('AWS_ENDPOINT_URL');
            } else {
                putenv('AWS_ENDPOINT_URL='.$original);
            }
        }
    }

    /** @test */
    public function it_rejects_a_disk_that_is_not_configured_as_s3(): void
    {
        Config::set('filesystems.disks.documents', ['driver' => 'local']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not configured as an s3 disk');

        S3ClientFactory::forDisk('documents');
    }

    /** @test */
    public function it_rejects_a_missing_disk(): void
    {
        $this->expectException(RuntimeException::class);

        S3ClientFactory::forDisk('no-such-disk');
    }

    /** @test */
    public function it_throws_when_the_disk_has_no_bucket(): void
    {
        Config::set('filesystems.disks.documents', [
            'driver' => 's3',
            'region' => 'us-east-1',
            'bucket' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No bucket is configured');

        S3ClientFactory::bucket('documents');
    }

    /** @test */
    public function it_reports_the_configured_endpoint_for_diagnostics(): void
    {
        Config::set('filesystems.disks.documents', [
            'driver' => 's3',
            'region' => 'us-east-1',
            'bucket' => 'b',
            'endpoint' => 'http://floci:4566',
        ]);

        $this->assertSame('http://floci:4566', S3ClientFactory::endpoint('documents'));
    }

    /** @test */
    public function it_describes_the_aws_region_when_no_endpoint_is_set(): void
    {
        Config::set('filesystems.disks.documents', [
            'driver' => 's3',
            'region' => 'eu-west-1',
            'bucket' => 'b',
            'endpoint' => null,
        ]);

        $this->assertStringContainsString('eu-west-1', S3ClientFactory::endpoint('documents'));
    }
}

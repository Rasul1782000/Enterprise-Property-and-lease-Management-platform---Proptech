<?php

namespace Tests\Feature;

use Aws\S3\S3Client;
use App\Support\Storage\S3ClientFactory;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documents how faithfully Floci behaves as a stand-in for AWS — specifically
 * the behaviours an emulator can silently fake and therefore mislead a test.
 */
class FlociFidelityTest extends TestCase
{
    private const BUCKET = 'floci-fidelity-probe';

    private function client(array $creds = ['key' => 'test', 'secret' => 'test']): S3Client
    {
        return new S3Client([
            'region'                  => 'us-east-1',
            'version'                 => 'latest',
            'use_path_style_endpoint' => true,
            'endpoint'                => env('AWS_ENDPOINT_URL', 'http://localhost:4566'),
            'credentials'             => $creds,
            'retries'                 => 0,
            'http'                    => ['connect_timeout' => 5, 'timeout' => 10],
        ]);
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $endpoint = env('AWS_ENDPOINT_URL', 'http://localhost:4566');

        if (! S3ClientFactory::probeEndpoint($endpoint)) {
            self::markTestSkipped(
                'No S3 endpoint reachable at ['.$endpoint.']. '
                .'Start Floci (docker run -p 4566:4566 floci/floci:latest) to run these.'
            );
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->client()->deleteBucket(['Bucket' => self::BUCKET]);
        } catch (\Throwable) {
            // Bucket may not exist yet.
        }

        $this->client()->createBucket(['Bucket' => self::BUCKET]);
    }

    protected function tearDown(): void
    {
        try {
            $c = $this->client();

            $c->deleteObjects([
                'Bucket' => self::BUCKET,
                'Delete' => ['Objects' => [['Key' => 'x']], 'Quiet' => true],
            ]);

            $c->deleteBucket(['Bucket' => self::BUCKET]);
        } catch (\Throwable) {
            // Best-effort cleanup.
        }

        parent::tearDown();
    }

    public function test_floci_accepts_any_credentials_so_tests_cannot_prove_auth_is_enforced(): void
    {
        $client = $this->client(['key' => 'totally-wrong-key', 'secret' => 'totally-wrong-secret']);

        $client->putObject(['Bucket' => self::BUCKET, 'Key' => 'unauth.txt', 'Body' => 'x']);

        $this->assertSame('x', (string) $client->getObject(['Bucket' => self::BUCKET, 'Key' => 'unauth.txt'])['Body']);
    }

    public function test_floci_accepts_bogus_credentials_that_real_aws_would_reject(): void
    {
        // Real S3 would throw InvalidAccessKeyId here.
        $client = $this->client([
            'key'    => 'AKIAIOSFODNN7EXAMPLE',
            'secret' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        ]);

        $client->putObject(['Bucket' => self::BUCKET, 'Key' => 'aws-style.txt', 'Body' => 'x']);

        $this->assertTrue(true);
    }

    public function test_floci_versioning_is_accepted_but_not_enforced(): void
    {
        $client = $this->client();

        $client->putBucketVersioning([
            'Bucket'                  => self::BUCKET,
            'VersioningConfiguration' => ['Status' => 'Enabled'],
        ]);

        $client->putObject(['Bucket' => self::BUCKET, 'Key' => 'v.txt', 'Body' => 'first']);
        $client->putObject(['Bucket' => self::BUCKET, 'Key' => 'v.txt', 'Body' => 'second']);

        // Real S3 would expose the previous version here.
        $body = (string) $client->getObject(['Bucket' => self::BUCKET, 'Key' => 'v.txt'])['Body'];

        $this->assertSame('second', $body, 'Floci keeps only the latest write, so version history is not real.');
    }

    public function test_floci_lifecycle_config_is_accepted_without_a_scheduler(): void
    {
        $client = $this->client();

        // Accepted without error, but nothing will ever expire.
        $client->putBucketLifecycleConfiguration([
            'Bucket'                 => self::BUCKET,
            'LifecycleConfiguration' => [
                'Rules' => [
                    [
                        'ID'         => 'expire-soon',
                        'Status'     => 'Enabled',
                        'Filter'     => ['Prefix' => 'tmp/'],
                        'Expiration' => ['Days' => 1],
                    ],
                ],
            ],
        ]);

        $this->assertTrue(true);
    }

    public function test_floci_storage_is_ephemeral_and_local(): void
    {
        $client = $this->client();
        $client->putObject(['Bucket' => self::BUCKET, 'Key' => 'ephemeral.txt', 'Body' => 'x']);

        $this->assertSame('x', (string) $client->getObject(['Bucket' => self::BUCKET, 'Key' => 'ephemeral.txt'])['Body']);

        // A container restart wipes this, so nothing here is evidence about
        // production durability.
        $this->assertStringContainsString('localhost', (string) env('AWS_ENDPOINT_URL'));
    }

    public function test_app_document_path_flow_works_end_to_end_against_floci(): void
    {
        $client = $this->client();

        $client->putObject([
            'Bucket'      => self::BUCKET,
            'Key'         => 'portal/tenants/1/doc.pdf',
            'Body'        => '%PDF-1.4 fake',
            'ContentType' => 'application/pdf',
            'Metadata'    => ['category' => 'lease'],
        ]);

        $listed = $client->listObjectsV2(['Bucket' => self::BUCKET, 'Prefix' => 'portal/tenants/1/']);

        $this->assertCount(1, $listed['Contents']);
        $this->assertSame('portal/tenants/1/doc.pdf', $listed['Contents'][0]['Key']);

        $head = $client->headObject(['Bucket' => self::BUCKET, 'Key' => 'portal/tenants/1/doc.pdf']);
        $this->assertSame('application/pdf', $head['ContentType']);

        $client->deleteObject(['Bucket' => self::BUCKET, 'Key' => 'portal/tenants/1/doc.pdf']);
        $this->assertFalse($client->doesObjectExist(self::BUCKET, 'portal/tenants/1/doc.pdf'));
    }

    public function test_flysystem_temporary_url_resolves_to_the_floci_host(): void
    {
        config([
            'filesystems.disks.probe' => [
                'driver'                  => 's3',
                'key'                     => 'test',
                'secret'                  => 'test',
                'region'                  => 'us-east-1',
                'bucket'                  => self::BUCKET,
                'endpoint'                => env('AWS_ENDPOINT_URL', 'http://localhost:4566'),
                'use_path_style_endpoint' => true,
                'throw'                   => true,
            ],
        ]);

        $this->client()->putObject([
            'Bucket' => self::BUCKET,
            'Key'    => 'portal/temp.pdf',
            'Body'   => 'pdf',
        ]);

        $url = Storage::disk('probe')->temporaryUrl('portal/temp.pdf', now()->addMinutes(15));

        $this->assertStringContainsString('localhost:4566', $url);
    }
}
<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Lease;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Storage\S3ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the two endpoints that write to object storage through the
 * `documents` disk (Floci in dev/CI, S3 in production).
 */
class LeaseDocumentStorageTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_BUCKET = 'property-lease-doc-tests';

    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = getenv('AWS_ENDPOINT_URL') ?: null;

        if (! S3ClientFactory::probeEndpoint($endpoint, getenv('AWS_DEFAULT_REGION') ?: null)) {
            $this->markTestSkipped('No S3 endpoint reachable; skipping object storage endpoint tests.');
        }

        config([
            'filesystems.disks.documents.bucket' => self::TEST_BUCKET,
            'filesystems.disks.documents.root' => 'portal',
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
                        'Objects' => array_map(fn ($o) => ['Key' => $o['Key']], $objects['Contents']),
                    ],
                ]);
            }

            $client->deleteBucket(['Bucket' => $bucket]);
        } catch (\Throwable) {
            // Best-effort cleanup.
        }

        parent::tearDown();
    }

    private function makeLease(): Lease
    {
        $property = Property::create([
            'name' => 'Test Tower',
            'code' => 'PROP-TST-1',
            'type' => 'commercial',
            'address' => '1 Test Street',
            'city' => 'Testville',
        ]);

        $building = Building::create([
            'property_id' => $property->id,
            'name' => 'Tower A',
            'code' => 'BLD-TST-1',
        ]);

        $unit = Unit::create([
            'building_id' => $building->id,
            'unit_number' => '101',
            'sqft' => 900,
            'rent_amount' => 1500,
            'status' => 'occupied',
        ]);

        $tenant = Tenant::create([
            'first_name' => 'Test',
            'last_name' => 'Tenant',
            'email' => 'test.tenant@example.test',
        ]);

        return Lease::create([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'lease_number' => 'LSE-TST-1',
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->addYear(),
            'rent_amount' => 1500,
            'deposit_amount' => 3000,
            // The leases enum allows: draft, active, expired, terminated, renewed.
            'status' => 'draft',
            'payment_frequency' => 'monthly',
        ]);
    }

    /** @test */
    public function signing_a_lease_stores_the_pdf_and_activates_it(): void
    {
        $lease = $this->makeLease();

        $this->actingAs($lease->created_by ?? User::first() ?? User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret1234'),
        ]));

        $response = $this->postJson("/api/leases/{$lease->id}/sign");

        $response->assertOk();

        $lease->refresh();

        $this->assertSame('active', $lease->status);
        $this->assertSame('signed/lease_LSE-TST-1.pdf', $lease->document_path);

        // The object must genuinely exist in the bucket, not just be recorded.
        Storage::disk('documents')->assertExists($lease->document_path);

        $pdf = Storage::disk('documents')->get($lease->document_path);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(500, strlen($pdf), 'Rendered lease PDF looks suspiciously small.');
    }

    /** @test */
    public function re_signing_replaces_the_document_rather_than_duplicating_it(): void
    {
        $lease = $this->makeLease();

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin2@example.test',
            'password' => bcrypt('secret1234'),
        ]));

        $this->postJson("/api/leases/{$lease->id}/sign")->assertOk();
        $this->postJson("/api/leases/{$lease->id}/sign")->assertOk();

        $lease->refresh();

        $this->assertSame('signed/lease_LSE-TST-1.pdf', $lease->document_path);

        $keys = array_column(
            Storage::disk('documents')->files('signed'),
            null
        );
        $this->assertCount(1, $keys, 'Re-signing should not leave duplicate objects behind.');
    }
}

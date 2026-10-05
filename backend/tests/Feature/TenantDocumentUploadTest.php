<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Models\User;
use App\Support\Storage\S3ClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the tenant document endpoints, including the write path that puts
 * objects on the `documents` disk (Floci in dev/CI, S3 in production).
 */
class TenantDocumentUploadTest extends TestCase
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

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret1234'),
        ]));
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

    private function makeTenant(): Tenant
    {
        return Tenant::create([
            'first_name' => 'Test',
            'last_name' => 'Tenant',
            'email' => 'test.tenant@example.test',
        ]);
    }

    /** @test */
    public function uploading_a_document_stores_the_object_and_records_it_against_the_tenant(): void
    {
        $tenant = $this->makeTenant();

        $response = $this->post("/api/tenants/{$tenant->id}/documents", [
            'file' => UploadedFile::fake()->create('passport-scan.pdf', 120, 'application/pdf'),
            'name' => 'Passport scan',
            'category' => 'id_document',
        ], ['Accept' => 'application/json']);

        $response->assertCreated();

        $document = TenantDocument::firstOrFail();
        $this->assertSame($tenant->id, $document->tenant_id);
        $this->assertSame('Passport scan', $document->name);
        $this->assertSame('id_document', $document->category);
        $this->assertSame('documents', $document->disk);
        $this->assertSame('pdf', $document->extension);

        // The object must genuinely exist in the bucket, not just be recorded.
        Storage::disk('documents')->assertExists($document->path);
        $this->assertStringContainsString("tenants/{$tenant->id}", $document->path);
    }

    /** @test */
    public function the_document_list_returns_only_that_tenants_documents(): void
    {
        $tenant = $this->makeTenant();
        $other = Tenant::create([
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other.person@example.test',
        ]);

        $tenant->documents()->create([
            'name' => 'Lease agreement.pdf',
            'category' => 'lease_agreement',
            'disk' => 'documents',
            'path' => 'tenants/lease.pdf',
            'size_kb' => 10,
        ]);
        $other->documents()->create([
            'name' => 'Something else.pdf',
            'category' => 'insurance',
            'disk' => 'documents',
            'path' => 'tenants/other.pdf',
            'size_kb' => 10,
        ]);

        $response = $this->getJson("/api/tenants/{$tenant->id}/documents");
        $response->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('Lease agreement.pdf', $response->json('data.0.name'));
    }

    /** @test */
    public function the_document_list_can_be_filtered_by_category(): void
    {
        $tenant = $this->makeTenant();

        foreach ([['A.pdf', 'insurance'], ['B.pdf', 'tax_form']] as [$name, $category]) {
            $tenant->documents()->create([
                'name' => $name,
                'category' => $category,
                'disk' => 'documents',
                'path' => "tenants/{$name}",
                'size_kb' => 10,
            ]);
        }

        $response = $this->getJson("/api/tenants/{$tenant->id}/documents?filter[category]=tax_form");
        $response->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('B.pdf', $response->json('data.0.name'));
    }

    /** @test */
    public function a_rejected_file_type_is_a_validation_error_and_stores_nothing(): void
    {
        $tenant = $this->makeTenant();

        $response = $this->post("/api/tenants/{$tenant->id}/documents", [
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/octet-stream'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertSame(0, TenantDocument::count());
    }

    /** @test */
    public function an_unknown_category_is_a_validation_error(): void
    {
        $tenant = $this->makeTenant();

        $response = $this->post("/api/tenants/{$tenant->id}/documents", [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            'category' => 'not-a-real-category',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)->assertJsonValidationErrors('category');
    }

    /** @test */
    public function deleting_a_document_removes_both_the_row_and_the_object(): void
    {
        $tenant = $this->makeTenant();

        $this->post("/api/tenants/{$tenant->id}/documents", [
            'file' => UploadedFile::fake()->create('receipt.pdf', 30, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = TenantDocument::firstOrFail();
        $path = $document->path;

        $this->deleteJson("/api/tenants/{$tenant->id}/documents/{$document->id}")->assertNoContent();

        $this->assertSame(0, TenantDocument::count());
        Storage::disk('documents')->assertMissing($path);
    }

    /** @test */
    public function a_document_cannot_be_reached_through_the_wrong_tenant(): void
    {
        $tenant = $this->makeTenant();
        $other = Tenant::create([
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other.person@example.test',
        ]);

        $document = $tenant->documents()->create([
            'name' => 'Private.pdf',
            'category' => 'other',
            'disk' => 'documents',
            'path' => 'tenants/private.pdf',
            'size_kb' => 10,
        ]);

        $this->getJson("/api/tenants/{$other->id}/documents/{$document->id}/download")->assertNotFound();
        $this->deleteJson("/api/tenants/{$other->id}/documents/{$document->id}")->assertNotFound();
    }

    /** @test */
    public function the_download_route_streams_the_stored_object(): void
    {
        $tenant = $this->makeTenant();

        $this->post("/api/tenants/{$tenant->id}/documents", [
            'file' => UploadedFile::fake()->create('statement.pdf', 40, 'application/pdf'),
            'name' => 'Bank statement.pdf',
        ], ['Accept' => 'application/json'])->assertCreated();

        $document = TenantDocument::firstOrFail();

        $response = $this->get("/api/tenants/{$tenant->id}/documents/{$document->id}/download");
        $response->assertOk();

        $this->assertStringContainsString(
            'Bank statement.pdf',
            (string) $response->headers->get('content-disposition')
        );
    }
}
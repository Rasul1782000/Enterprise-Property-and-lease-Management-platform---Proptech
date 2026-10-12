<?php

namespace Tests\Feature;

use App\Support\DebugHelper;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Tests\TestCase;

class DebugToolingTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        // A unique file per test: Monolog keeps the stream handle open for the
        // lifetime of the channel, and on Windows a deleted-but-open file
        // cannot be reopened in append mode.
        $this->logPath = storage_path('logs/debug-tooling-' . uniqid('', true) . '.log');

        config(['logging.channels.debug' => [
            'driver' => 'single',
            'path'   => $this->logPath,
            'level'  => 'debug',
        ]]);

        Log::forgetChannel('debug');
    }

    protected function tearDown(): void
    {
        Log::forgetChannel('debug');

        if (File::exists($this->logPath)) {
            @File::delete($this->logPath);
        }

        parent::tearDown();
    }

    private function readLog(): string
    {
        return File::exists($this->logPath) ? File::get($this->logPath) : '';
    }

    private function callPrivate(object $object, string $method, array $args = [])
    {
        $ref = new ReflectionClass($object);
        $m  = $ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($object, $args);
    }

    // ─── DebugMiddleware ───────────────────────────────────────────────────

    public function test_middleware_adds_debug_headers_when_debugging_is_enabled(): void
    {
        config(['debug.enabled' => true]);

        $response = $this->getJson('/up');

        $response->assertSuccessful();
        $response->assertHeader('X-Debug-Request-Id');
        $response->assertHeader('X-Debug-Duration-Ms');
        $response->assertHeader('X-Debug-Memory-MB');
        $response->assertHeader('X-Debug-Db-Queries');
    }

    public function test_middleware_omits_debug_headers_when_debugging_is_disabled(): void
    {
        config(['debug.enabled' => false]);

        $response = $this->getJson('/up');

        $response->assertSuccessful();
        $response->assertHeaderMissing('X-Debug-Request-Id');
    }

    public function test_middleware_never_writes_session_cookies_to_the_log(): void
    {
        config(['debug.enabled' => true]);

        // A response that sets cookies must not have their values logged.
        $this->getJson('/up')->assertSuccessful();

        $log = $this->readLog();

        $this->assertStringNotContainsString('XSRF-TOKEN=', $log);
        $this->assertStringNotContainsString('propertylease_portal_session=', $log);
        $this->assertStringContainsString('REDACTED', $log);
    }

    public function test_middleware_writes_nothing_to_disk_when_debugging_is_disabled(): void
    {
        config(['debug.enabled' => false]);

        $this->withHeader('Authorization', 'Bearer should-never-be-written')
             ->getJson('/up')
             ->assertSuccessful();

        $this->assertSame('', $this->readLog(), 'Disabled debugging must not write request or response bodies to disk.');
    }

    public function test_middleware_logs_the_request_and_the_response(): void
    {
        config(['debug.enabled' => true]);

        $this->getJson('/up')->assertSuccessful();

        $log = $this->readLog();

        $this->assertStringContainsString('Incoming Request', $log);
        $this->assertStringContainsString('Outgoing Response', $log);
    }

    public function test_middleware_redacts_sensitive_headers(): void
    {
        config(['debug.enabled' => true]);

        $this->withHeaders(['Authorization' => 'Bearer super-secret-token'])
             ->getJson('/up')
             ->assertSuccessful();

        $log = $this->readLog();

        $this->assertStringNotContainsString('super-secret-token', $log);
        $this->assertStringContainsString('REDACTED', $log);
    }

    public function test_middleware_redacts_sensitive_body_fields(): void
    {
        config(['debug.enabled' => true]);

        // /up is a GET; exercise the private redactor directly for POST bodies.
        $middleware = new \App\Http\Middleware\DebugMiddleware();

        $filtered = $this->callPrivate($middleware, 'filterBody', [[
            'email'            => 'user@example.com',
            'password'         => 'hunter2',
            'password_confirmation' => 'hunter2',
            'card_number'      => '4111111111111111',
        ]]);

        $this->assertSame('user@example.com', $filtered['email']);
        $this->assertSame('***REDACTED***', $filtered['password']);
        $this->assertSame('***REDACTED***', $filtered['password_confirmation']);
        $this->assertSame('***REDACTED***', $filtered['card_number']);
    }

    public function test_middleware_truncates_very_long_body_values(): void
    {
        $middleware = new \App\Http\Middleware\DebugMiddleware();

        $long = str_repeat('x', 500);
        $filtered = $this->callPrivate($middleware, 'filterBody', [['bio' => $long]]);

        $this->assertStringEndsWith('...', $filtered['bio']);
        $this->assertLessThan(250, strlen($filtered['bio']));
    }

    public function test_middleware_formats_byte_sizes_readably(): void
    {
        $middleware = new \App\Http\Middleware\DebugMiddleware();

        $this->assertSame('0 B',  $this->callPrivate($middleware, 'formatBytes', [0]));
        $this->assertSame('1 KB', $this->callPrivate($middleware, 'formatBytes', [1024]));
        $this->assertSame('1 MB', $this->callPrivate($middleware, 'formatBytes', [1048576]));
    }

    public function test_middleware_measures_its_own_duration(): void
    {
        $middleware = new \App\Http\Middleware\DebugMiddleware();

        $ref = new ReflectionClass($middleware);
        $startTime = $ref->getProperty('startTime');
        $startMemory = $ref->getProperty('startMemory');
        $startTime->setAccessible(true);
        $startMemory->setAccessible(true);
        $startTime->setValue($middleware, hrtime(true));
        $startMemory->setValue($middleware, memory_get_usage(true));

        $duration = $this->callPrivate($middleware, 'getDurationMs');
        $memory   = $this->callPrivate($middleware, 'getMemoryMb');

        $this->assertGreaterThan(0, $duration);
        $this->assertGreaterThanOrEqual(0, $memory);
    }

    // ─── DebugHelper ───────────────────────────────────────────────────────

    public function test_helper_log_writes_to_the_debug_channel(): void
    {
        DebugHelper::log('user signed in', ['user_id' => 7]);

        $log = $this->readLog();

        $this->assertStringContainsString('user signed in', $log);
        $this->assertStringContainsString('user_id', $log);
    }

    public function test_helper_log_redacts_sensitive_context(): void
    {
        DebugHelper::log('auth attempt', [
            'email'    => 'a@b.com',
            'password' => 'letmein',
        ]);

        $log = $this->readLog();

        $this->assertStringNotContainsString('letmein', $log);
        $this->assertStringContainsString('REDACTED', $log);
    }

    public function test_helper_log_records_the_calling_class_and_location(): void
    {
        DebugHelper::log('location probe');

        $log = $this->readLog();

        $this->assertStringContainsString('DebugToolingTest', $log);
        $this->assertMatchesRegularExpression('/DebugToolingTest:\w+ \(.*:\d+\)/', $log);
    }

    public function test_helper_dump_handles_scalars_arrays_and_objects(): void
    {
        DebugHelper::dump('plain string', 42, true, null, ['a' => 1], new \stdClass());

        $log = $this->readLog();

        $this->assertStringContainsString('plain string', $log);
        $this->assertStringContainsString('42', $log);
    }

    public function test_helper_trace_writes_a_backtrace(): void
    {
        DebugHelper::trace();

        $log = $this->readLog();

        $this->assertStringContainsString('Backtrace', $log);
    }

    public function test_helper_memory_reports_usage(): void
    {
        DebugHelper::memory('probe');

        $log = $this->readLog();

        $this->assertStringContainsString('current_mb', $log);
        $this->assertStringContainsString('peak_mb', $log);
    }

    public function test_helper_time_returns_the_callback_value_and_measures_duration(): void
    {
        $result = DebugHelper::time(function () {
            usleep(2000);
            return 'computed';
        }, 'sleepy');

        $this->assertSame('computed', $result);
        $this->assertStringContainsString('sleepy', $this->readLog());
        $this->assertStringContainsString('duration_ms', $this->readLog());
    }

    public function test_helper_time_reraises_exceptions_from_the_callback(): void
    {
        $this->expectException(\RuntimeException::class);

        DebugHelper::time(function () {
            throw new \RuntimeException('boom');
        }, 'exploding');
    }

    public function test_helper_time_logs_failures_before_reraising(): void
    {
        try {
            DebugHelper::time(function () {
                throw new \RuntimeException('boom');
            }, 'exploding');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertStringContainsString('FAILED', $this->readLog());
        $this->assertStringContainsString('boom', $this->readLog());
    }

    public function test_helper_db_reports_query_statistics_without_error(): void
    {
        \DB::table('sqlite_master')->select('*');

        DebugHelper::db('after select');

        $log = $this->readLog();

        $this->assertStringContainsString('total_queries', $log);
    }

    public function test_helper_config_does_not_leak_sensitive_values(): void
    {
        config(['database.connections.mysql.password' => 'super-secret-db-pw']);

        DebugHelper::config();

        $log = $this->readLog();

        $this->assertStringNotContainsString('super-secret-db-pw', $log);
    }

    public function test_helper_env_redacts_secret_keys(): void
    {
        putenv('MY_APP_SECRET=super-secret-token');
        $_ENV['MY_APP_SECRET'] = 'super-secret-token';

        DebugHelper::env();

        $log = $this->readLog();

        $this->assertStringNotContainsString('super-secret-token', $log);
        $this->assertStringContainsString('REDACTED', $log);

        putenv('MY_APP_SECRET');
        unset($_ENV['MY_APP_SECRET']);
    }

    public function test_helper_model_logs_attributes_changes_and_relations(): void
    {
        $model = new \App\Models\Property();
        $model->forceFill([
            'name'     => 'Sunrise Tower',
            'address'  => 'Sheikh Zayed Road',
        ]);

        DebugHelper::model($model, 'state');

        $log = $this->readLog();

        $this->assertStringContainsString('Sunrise Tower', $log);
        $this->assertStringContainsString('Property', $log);
    }

    public function test_helper_model_redacts_sensitive_attributes(): void
    {
        $model = new \App\Models\User();
        $model->forceFill([
            'name'     => 'Staff User',
            'password' => 'hashed-secret-value',
        ]);

        DebugHelper::model($model, 'state');

        $log = $this->readLog();

        $this->assertStringContainsString('Staff User', $log);
        $this->assertStringNotContainsString('hashed-secret-value', $log);
    }
}
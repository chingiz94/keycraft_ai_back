<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Monolog\Handler\TestHandler;
use RuntimeException;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    private TestHandler $records;

    protected function setUp(): void
    {
        parent::setUp();
        $this->records = new TestHandler;
        Log::channel('api')->getLogger()->setHandlers([$this->records]);
    }

    public function test_audit_records_method_path_status_duration_and_user_without_secrets(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $response = $this->withToken('private-test-bearer')
            ->getJson('/api/v1/builds?password=private-query-value&api_key=private-key-value')
            ->assertOk()->assertHeader('X-Request-ID');
        $audit = $this->records->getRecords()[0];
        $this->assertSame('api.request', $audit->message);
        $this->assertSame('/api/v1/builds', $audit->context['url']);
        $this->assertSame('GET', $audit->context['method']);
        $this->assertSame($buyer->id, $audit->context['user_id']);
        $this->assertSame(200, $audit->context['status']);
        $this->assertGreaterThanOrEqual(0, $audit->context['duration_ms']);
        $this->assertSame($response->headers->get('X-Request-ID'), $audit->context['request_id']);
        $logged = json_encode($audit->context, JSON_THROW_ON_ERROR);
        foreach (['private-test-bearer', 'private-query-value', 'private-key-value'] as $secret) {
            $this->assertStringNotContainsString($secret, $logged);
        }
    }

    public function test_unauthorized_validation_not_found_and_server_errors_are_audited(): void
    {
        $this->getJson('/api/v1/builds')->assertUnauthorized();
        $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
        $this->getJson('/api/v1/route-that-does-not-exist')->assertNotFound();
        Route::get('/api/v1/audit-test-error', fn () => throw new RuntimeException('Test failure'));
        $this->getJson('/api/v1/audit-test-error')->assertStatus(500);
        $audit = array_values(array_filter($this->records->getRecords(), fn ($record) => $record->message === 'api.request'));
        $this->assertSame([401, 422, 404, 500], array_map(fn ($record) => $record->context['status'], $audit));
        $this->assertSame(['Warning', 'Warning', 'Warning', 'Error'], array_map(fn ($record) => $record->level->name, $audit));
    }

    public function test_login_events_do_not_include_credentials_or_response_tokens(): void
    {
        $password = 'never-log-this-password';
        $buyer = User::factory()->create(['password' => $password]);
        $token = $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => $password])
            ->assertOk()->json('token');
        $this->postJson('/api/v1/auth/login', ['email' => $buyer->email, 'password' => 'wrong-password'])
            ->assertUnauthorized();
        $logged = json_encode(array_map(fn ($record) => [$record->message, $record->context], $this->records->getRecords()), JSON_THROW_ON_ERROR);
        foreach ([$password, 'wrong-password', $token, $buyer->email] as $secret) {
            $this->assertStringNotContainsString($secret, $logged);
        }
        $this->assertTrue($this->records->hasInfo('auth.logged_in'));
        $this->assertTrue($this->records->hasWarning('auth.login_rejected'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RetailRecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai.provider' => 'groq', 'services.groq.key' => 'test-provider-key', 'services.groq.model' => 'openai/gpt-oss-20b']);
        Http::preventStrayRequests();
        Sanctum::actingAs(User::factory()->create());
    }

    private function answer(array $replace = []): array
    {
        $answer = array_replace(['kit_id' => 'gmmk-pro-black', 'switch_id' => 'gateron-red', 'keycap_id' => 'gpbt-black-ash', 'explanation' => 'ANSI основа, линейные MX свитчи и PBT кейкапы.'], $replace);

        return ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($answer, JSON_UNESCAPED_UNICODE)]]]]];
    }

    public function test_provider_selects_real_ids_and_server_recomputes_prices_without_user_identifiers(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->answer())]);
        $response = $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000, 'preferences' => 'Линейный ход'])->assertOk()->assertJsonPath('data.quote.compatible', true);
        $quote = $response->json('data.quote');
        $this->assertSame(array_sum(array_column($quote['items'], 'subtotal')), $quote['total']);
        Http::assertSent(function ($request): bool {
            $payload = json_decode($request['input'], true);

            return $request->url() === 'https://api.groq.com/openai/v1/responses'
                && ! isset($request['store']) && ! isset($payload['user_id']) && ! isset($payload['email'])
                && count($payload['catalog']) === 7
                && $payload['currency'] === 'KZT' && $payload['budget_kzt'] === 200000
                && $payload['catalog'][0]['price_kzt'] === 70845
                && ! isset($payload['catalog'][0]['price']);
        });
    }

    public function test_over_budget_answer_is_rejected(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->answer())]);
        $this->postJson('/api/v1/retail/recommendations', ['budget' => 100])->assertUnprocessable();
    }

    public function test_unknown_product_and_wrong_category_answers_are_rejected(): void
    {
        foreach ([['kit_id' => 'invented-id'], ['kit_id' => 'gateron-red']] as $replace) {
            Http::fake(['api.groq.com/*' => Http::response($this->answer($replace))]);
            $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000])->assertStatus(503);
        }
    }

    public function test_quota_error_never_falls_back_to_a_paid_provider(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'secret-upstream-detail']], 429)]);
        $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000])->assertStatus(503)->assertDontSee('secret-upstream-detail');
        Http::assertSentCount(1);
    }

    public function test_absent_key_and_unknown_provider_make_no_external_request(): void
    {
        config(['services.groq.key' => '']);
        $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000])->assertStatus(503);
        config(['services.ai.provider' => 'untrusted']);
        $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_malformed_and_refused_responses_are_service_errors(): void
    {
        $responses = [
            ['status' => 'completed', 'output' => 'broken'],
            ['status' => 'completed', 'output' => [['type' => 'message', 'content' => 'broken']]],
            ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]]],
            ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 'invalid-json']]]]],
        ];
        foreach ($responses as $response) {
            Http::fake(['api.groq.com/*' => Http::response($response)]);
            $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000])->assertStatus(503);
        }
    }

    public function test_network_failure_is_a_recoverable_service_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Test timeout'));
        $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000])->assertStatus(503)->assertDontSee('Test timeout');
    }

    public function test_administrator_cannot_use_buyer_ai_and_preferences_are_validated(): void
    {
        $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000, 'preferences' => str_repeat('x', 1001)])->assertUnprocessable();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/retail/recommendations', ['budget' => 20000000])->assertForbidden();
        Http::assertNothingSent();
    }
}

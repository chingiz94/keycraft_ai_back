<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Component;
use App\Models\User;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openai.key' => 'test-secret', 'services.openai.model' => 'gpt-4.1-mini']);
        Http::preventStrayRequests();
    }

    public function test_valid_ai_selection_is_verified_without_saving_a_build(): void
    {
        $items = $this->catalog();
        $this->fakeAnswer(['items' => $items, 'explanation' => 'Совместимый набор в пределах бюджета.']);

        $result = app(RecommendationService::class)->recommend($this->input() + [
            'email' => 'must-not-leave@example.test', 'token' => 'private-user-token',
        ]);

        $this->assertTrue($result['compatibility']['compatible']);
        $this->assertSame(7200, $result['total']);
        $this->assertSame($items, $result['items']);
        $this->assertDatabaseCount('builds', 0);
        Http::assertSent(function (Request $request) {
            $payload = $request->data();
            $input = json_decode($payload['input'], true);
            $this->assertSame(['budget', 'format', 'preferences', 'catalog'], array_keys($input));
            $this->assertStringNotContainsString('must-not-leave', $request->body());
            $this->assertStringNotContainsString('private-user-token', $request->body());
            $this->assertFalse($payload['store']);
            $this->assertTrue($payload['text']['format']['strict']);

            return $request->url() === 'https://api.openai.com/v1/responses';
        });
    }

    public function test_missing_key_returns_503_without_an_http_call(): void
    {
        config(['services.openai.key' => '']);
        $this->assertFailure(503, fn () => app(RecommendationService::class)->recommend($this->input()));
        Http::assertNothingSent();
    }

    public function test_ai_endpoint_is_limited_to_ten_requests_per_user_per_minute(): void
    {
        config(['services.openai.key' => '']);
        Sanctum::actingAs(User::factory()->create()->refresh());
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/ai/recommendations', $this->input())->assertStatus(503);
        }
        $this->postJson('/api/v1/ai/recommendations', $this->input())
            ->assertStatus(429)->assertHeader('Retry-After')->assertJsonStructure(['message', 'errors']);

        Sanctum::actingAs(User::factory()->create()->refresh());
        $this->postJson('/api/v1/ai/recommendations', $this->input())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_upstream_failure_is_sanitized(): void
    {
        $this->catalog();
        Http::fake(['api.openai.com/*' => Http::response(['error' => 'test-secret and internal details'], 429)]);
        try {
            app(RecommendationService::class)->recommend($this->input());
            $this->fail('Expected provider failure.');
        } catch (BusinessException $exception) {
            $this->assertSame(503, $exception->status);
            $this->assertStringNotContainsString('test-secret', $exception->getMessage());
            $this->assertStringNotContainsString('internal', $exception->getMessage());
        }
    }

    public function test_unknown_catalog_id_is_rejected_even_if_it_exists_outside_sent_format(): void
    {
        $items = $this->catalog();
        $other = Component::create([
            'name' => 'Other format case', 'type' => 'case', 'format' => '75',
            'price' => 100, 'stock' => 10, 'specs' => ['mount' => 'gasket'],
        ]);
        $items[0]['component_id'] = $other->id;
        $this->fakeAnswer(['items' => $items, 'explanation' => 'Недопустимая деталь.']);
        $this->assertFailure(503, fn () => app(RecommendationService::class)->recommend($this->input()));
    }

    public function test_incompatible_selection_is_rejected(): void
    {
        $items = $this->catalog();
        Component::where('type', 'plate')->update(['specs' => json_encode(['mount' => 'tray', 'switch_type' => 'mx'])]);
        $this->fakeAnswer(['items' => $items, 'explanation' => 'Проверка конфликта крепления.']);
        $this->assertFailure(422, fn () => app(RecommendationService::class)->recommend($this->input()));
    }

    public function test_actual_catalog_total_is_checked_against_budget(): void
    {
        $items = $this->catalog();
        $this->fakeAnswer(['items' => $items, 'explanation' => 'Модель не задаёт итоговую цену.']);
        $input = $this->input();
        $input['budget'] = 7199;
        $this->assertFailure(422, fn () => app(RecommendationService::class)->recommend($input));
    }

    public function test_catalog_is_rechecked_after_the_external_call(): void
    {
        $items = $this->catalog();
        Http::fake(['api.openai.com/*' => function () use ($items) {
            Component::where('type', 'switch')->update(['stock' => 1]);

            return Http::response([
                'status' => 'completed',
                'output' => [['type' => 'message', 'content' => [[
                    'type' => 'output_text', 'text' => json_encode(['items' => $items, 'explanation' => 'Ответ после изменения остатка.']),
                ]]]],
            ]);
        }]);

        $this->assertFailure(422, fn () => app(RecommendationService::class)->recommend($this->input()));
    }

    public function test_duplicate_ids_are_rejected(): void
    {
        $items = $this->catalog();
        $duplicate = $items;
        $duplicate[1] = $duplicate[0];
        $this->fakeAnswer(['items' => $duplicate, 'explanation' => 'Повтор ID.']);
        $this->assertFailure(503, fn () => app(RecommendationService::class)->recommend($this->input()));
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        $items = $this->catalog();
        Component::where('type', 'switch')->update(['stock' => 66]);
        $this->fakeAnswer(['items' => $items, 'explanation' => 'Слишком много свитчей.']);
        $this->assertFailure(503, fn () => app(RecommendationService::class)->recommend($this->input()));
    }

    public function test_malformed_json_refusal_and_incomplete_response_are_rejected(): void
    {
        $this->catalog();
        $responses = [
            ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{bad json']]]]],
            ['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'No']]]]],
            ['status' => 'incomplete', 'output' => []],
            ['status' => 'completed', 'output' => [['type' => 'message', 'content' => 'malformed']]],
        ];
        $sequence = Http::fakeSequence('api.openai.com/*');
        foreach ($responses as $response) {
            $sequence->push($response);
        }
        foreach ($responses as $response) {
            $this->assertFailure(503, fn () => app(RecommendationService::class)->recommend($this->input()));
        }
    }

    public function test_connection_failure_returns_503(): void
    {
        $this->catalog();
        Http::fake(['api.openai.com/*' => Http::failedConnection()]);
        $this->assertFailure(503, fn () => app(RecommendationService::class)->recommend($this->input()));
    }

    private function input(): array
    {
        return ['budget' => 1000000, 'format' => '65', 'preferences' => 'Тихая клавиатура'];
    }

    private function fakeAnswer(array $answer): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'status' => 'completed',
            'output' => [['type' => 'message', 'content' => [[
                'type' => 'output_text', 'text' => json_encode($answer, JSON_UNESCAPED_UNICODE),
            ]]]],
        ])]);
    }

    private function catalog(): array
    {
        $specs = [
            'case' => ['mount' => 'gasket'],
            'pcb' => ['mount' => 'gasket', 'switch_type' => 'mx', 'layout' => 'ansi', 'key_count' => 67, 'stabilizers' => ['2u' => 3, '6.25u' => 1]],
            'plate' => ['mount' => 'gasket', 'switch_type' => 'mx'],
            'stabilizer' => ['stabilizers' => ['2u' => 3, '6.25u' => 1]],
            'switch' => ['switch_type' => 'mx', 'stem' => 'mx'],
            'keycap' => ['stem' => 'mx', 'layout' => 'ansi', 'key_count' => 67],
        ];
        $items = [];
        foreach ($specs as $type => $properties) {
            $component = Component::create([
                'name' => "Test {$type}", 'type' => $type, 'price' => 100,
                'stock' => $type === 'switch' ? 500 : 10,
                'format' => in_array($type, ['case', 'pcb', 'plate']) ? '65' : null,
                'specs' => $properties,
            ]);
            $items[] = ['component_id' => $component->id, 'quantity' => $type === 'switch' ? 67 : 1];
        }

        return $items;
    }

    private function assertFailure(int $status, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected a business exception.');
        } catch (BusinessException $exception) {
            $this->assertSame($status, $exception->status);
        }
    }
}

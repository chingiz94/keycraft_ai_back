<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Component;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::factory()->create()->refresh();
    }

    private function admin(): User
    {
        $user = $this->buyer();
        $user->role = 'admin';
        $user->save();

        return $user;
    }

    public function test_registration_login_token_revocation_and_role_injection(): void
    {
        $payload = ['name' => 'Покупатель', 'email' => 'buyer@example.test', 'password' => 'test-secret-123', 'password_confirmation' => 'test-secret-123'];
        $this->postJson('/api/v1/auth/register', $payload + ['role' => 'admin'])->assertUnprocessable();
        $registered = $this->postJson('/api/v1/auth/register', $payload)->assertCreated()->assertJsonPath('data.role', 'buyer')->assertJsonMissingPath('data.password');
        $this->postJson('/api/v1/auth/register', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => $payload['email'], 'password' => 'incorrect'])->assertUnauthorized();
        $token = $this->postJson('/api/v1/auth/login', ['email' => $payload['email'], 'password' => $payload['password']])->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_guest_invalid_token_expired_token_and_buyer_admin_access(): void
    {
        $this->getJson('/api/v1/builds')->assertUnauthorized()->assertJsonStructure(['message', 'errors']);
        $this->withToken('invalid')->getJson('/api/v1/builds')->assertUnauthorized();
        $user = $this->buyer();
        $token = $user->createToken('expired');
        $token->accessToken->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->patchJson('/api/v1/admin/users/'.$user->id.'/role', ['role' => 'admin'])->assertForbidden();
    }

    public function test_bcrypt_password_byte_limit_and_null_characters_are_validated(): void
    {
        $base = ['name' => 'Проверка пароля', 'email' => 'password-check@example.test'];
        foreach ([str_repeat('a', 73), str_repeat('я', 37), "valid\0password"] as $password) {
            $this->postJson('/api/v1/auth/register', $base + ['password' => $password, 'password_confirmation' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        foreach ([str_repeat('я', 37), "valid\0password"] as $password) {
            $this->postJson('/api/v1/auth/login', ['email' => $base['email'], 'password' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        foreach ([str_repeat('a', 72), str_repeat('я', 36)] as $index => $password) {
            $email = "boundary-{$index}@example.test";
            $this->postJson('/api/v1/auth/register', array_replace($base, ['email' => $email, 'password' => $password, 'password_confirmation' => $password]))
                ->assertCreated();
            $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password])->assertOk();
        }
    }

    public function test_catalog_search_filters_sort_pagination_and_rejects_sql_sort(): void
    {
        Component::factory()->create(['name' => 'Quiet Switch', 'type' => 'switch', 'price' => 100, 'stock' => 1, 'format' => '65']);
        Component::factory()->create(['name' => 'Quiet Switch Plus', 'type' => 'switch', 'price' => 200, 'stock' => 2, 'format' => '65']);
        Component::factory()->create(['name' => 'Other Case', 'type' => 'case', 'price' => 999, 'stock' => 0, 'format' => '75']);
        $this->getJson('/api/v1/components?search=Quiet&type=switch&format=65&min_price=100&max_price=200&in_stock=1&sort=price&order=desc&per_page=1')
            ->assertOk()->assertJsonPath('data.0.price', 200)->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 1)->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson('/api/v1/components?in_stock=0')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/components?max_price=200')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/components?min_price=200&max_price=100')->assertUnprocessable()
            ->assertJsonPath('errors.max_price.0', 'Значение max price должно быть не меньше 200.');
        $this->getJson('/api/v1/components?sort=price%20desc;drop%20table%20users')->assertUnprocessable();
        $this->getJson('/api/v1/components?per_page=-5')->assertUnprocessable();
        $this->getJson('/api/v1/components/99999')->assertNotFound();
    }

    public function test_admin_catalog_offer_crud_and_reference_deletion_conflict(): void
    {
        Sanctum::actingAs($this->admin());
        $data = ['name' => 'Тестовый корпус', 'type' => 'case', 'price' => 10000, 'stock' => 4, 'format' => '65', 'specs' => ['mount' => 'gasket']];
        $id = $this->postJson('/api/v1/admin/components', $data)->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/admin/components/'.$id, ['price' => 12000])->assertOk()->assertJsonPath('data.price', 12000);
        $this->postJson('/api/v1/admin/components', $data + ['extra' => 'ignored'])->assertCreated();
        $this->patchJson('/api/v1/admin/components/'.$id, ['stock' => -1])->assertUnprocessable();
        $offer = ['component_id' => $id, 'marketplace' => 'Тест', 'url' => 'https://example.com/component', 'price' => 14000, 'price_updated_at' => now()->subDay()->toIso8601String()];
        $this->postJson('/api/v1/admin/offers', array_replace($offer, ['url' => 'http://example.com']))->assertUnprocessable();
        $offerId = $this->postJson('/api/v1/admin/offers', $offer)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/components/'.$id.'/offers')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson('/api/v1/admin/offers/'.$offerId, ['price' => 13000])->assertOk();
        $this->deleteJson('/api/v1/admin/offers/'.$offerId)->assertNoContent();
        $build = Build::factory()->create();
        $build->items()->create(['component_id' => $id, 'quantity' => 1]);
        $this->deleteJson('/api/v1/admin/components/'.$id)->assertConflict();
        $build->delete();
        $this->deleteJson('/api/v1/admin/components/'.$id)->assertNoContent();
    }

    public function test_build_crud_owner_nested_items_and_quantity_checks(): void
    {
        $owner = $this->buyer();
        $other = $this->buyer();
        $otherBuild = $other->builds()->create(['name' => 'Чужая', 'budget' => 100000]);
        $otherItem = $otherBuild->items()->create(['component_id' => Component::factory()->create()->id, 'quantity' => 1]);
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/v1/builds', ['name' => 'Моя', 'budget' => 100000])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/builds')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/builds/'.$otherBuild->id)->assertForbidden();
        $this->patchJson('/api/v1/builds/'.$otherBuild->id, ['name' => 'Взлом'])->assertForbidden();
        $component = Component::factory()->create();
        $this->postJson('/api/v1/builds/'.$id.'/items', ['component_id' => $component->id, 'quantity' => 0])->assertUnprocessable();
        $item = $this->postJson('/api/v1/builds/'.$id.'/items', ['component_id' => $component->id, 'quantity' => 1])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/builds/'.$id.'/items', ['component_id' => $component->id, 'quantity' => 1])->assertConflict();
        $this->patchJson('/api/v1/builds/'.$id.'/items/'.$otherItem->id, ['quantity' => 2])->assertNotFound();
        $this->patchJson('/api/v1/builds/'.$id.'/items/'.$item, ['quantity' => 2])->assertOk()->assertJsonPath('data.quantity', 2);
        $this->postJson('/api/v1/builds/'.$id.'/validate')->assertOk()->assertJsonPath('data.complete', false);
        $this->deleteJson('/api/v1/builds/'.$id.'/items/'.$item)->assertNoContent();
        $this->deleteJson('/api/v1/builds/'.$id)->assertNoContent();
    }

    public function test_role_changes_last_admin_protection_statistics_and_health(): void
    {
        $admin = $this->admin();
        $buyer = $this->buyer();
        Sanctum::actingAs($admin);
        $this->patchJson('/api/v1/admin/users/'.$admin->id.'/role', ['role' => 'buyer'])->assertConflict();
        $this->patchJson('/api/v1/admin/users/'.$buyer->id.'/role', ['role' => 'admin'])->assertOk()->assertJsonPath('data.role', 'admin');
        $this->patchJson('/api/v1/admin/users/'.$buyer->id.'/role', ['role' => 'buyer'])->assertOk();
        $this->getJson('/api/v1/admin/statistics')->assertOk()->assertJsonStructure(['data' => ['orders_by_status', 'issued_revenue']]);
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('data.database', 'ok');
    }

    public function test_cors_allows_only_configured_origin(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'authorization,idempotency-key'])->options('/api/v1/orders')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $response = $this->withHeaders(['Origin' => 'https://untrusted.example', 'Access-Control-Request-Method' => 'POST'])->options('/api/v1/orders')->assertNoContent();
        $this->assertNotEquals('https://untrusted.example', $response->headers->get('Access-Control-Allow-Origin'));
    }
}

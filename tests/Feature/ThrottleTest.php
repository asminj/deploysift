<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ThrottleTest extends TestCase {
 use RefreshDatabase;
 public function test_login_attempts_are_rate_limited(): void {
  $codes = [];
  for ($i = 0; $i < 12; $i++) { $codes[] = $this->postJson('/auth/login', ['email' => 'nobody@example.test', 'password' => 'wrongpassword1'])->getStatusCode(); }
  $this->assertContains(429, $codes);
 }
 public function test_authenticated_api_requests_are_rate_limited(): void {
  $user = User::factory()->create(); $w = Workspace::create(['name' => 'T']); $user->workspaces()->attach($w->id, ['role' => 'owner']);
  $codes = [];
  for ($i = 0; $i < 65; $i++) { $codes[] = $this->actingAs($user)->getJson('/api/workspaces')->getStatusCode(); }
  $this->assertContains(429, $codes);
 }
 public function test_unauthenticated_api_requests_are_rejected(): void {
  $this->getJson('/api/workspaces')->assertUnauthorized();
 }
}

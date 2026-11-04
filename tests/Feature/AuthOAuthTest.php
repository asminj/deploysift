<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;
class AuthOAuthTest extends TestCase {
 use RefreshDatabase;
 private function socialUser(?string $email,array $raw=[]): \Laravel\Socialite\Two\User { $attrs=array_merge(['id'=>'ext-1','email'=>$email,'name'=>'OAuth User','nickname'=>null],$raw); $u=new \Laravel\Socialite\Two\User(); $u->map($attrs); $u->setRaw($attrs); return $u; }
 private function mockProvider(string $provider,\Laravel\Socialite\Two\User $u): void { $p=\Mockery::mock(); $p->shouldReceive('user')->andReturn($u); Socialite::shouldReceive('driver')->with($provider)->andReturn($p); }
 public function test_verified_google_creates_user_and_logs_in(): void {
  $this->mockProvider('google',$this->socialUser('g@example.test',['email_verified'=>true]));
  $this->get('/auth/google/callback')->assertRedirect('/');
  $this->assertAuthenticated();
  $this->assertDatabaseHas('users',['email'=>'g@example.test']);
  $this->assertDatabaseHas('workspace_user',['user_id'=>1,'role'=>'owner']);
 }
 public function test_missing_email_creates_nothing(): void {
  $this->mockProvider('google',$this->socialUser(null,[]));
  $this->get('/auth/google/callback')->assertRedirect('/login?error=oauth-no-email');
  $this->assertGuest();
  $this->assertDatabaseCount('users',0);
 }
 public function test_unverified_google_email_is_rejected(): void {
  $this->mockProvider('google',$this->socialUser('u@example.test',['email_verified'=>false]));
  $this->get('/auth/google/callback')->assertRedirect('/login?error=oauth-unverified');
  $this->assertGuest();
  $this->assertDatabaseMissing('users',['email'=>'u@example.test']);
 }
 public function test_github_without_verification_metadata_creates_new_account(): void {
  $this->mockProvider('github',$this->socialUser('h@example.test',[]));
  $this->get('/auth/github/callback')->assertRedirect('/');
  $this->assertAuthenticated();
  $this->assertDatabaseHas('users',['email'=>'h@example.test']);
 }
 public function test_unverified_oauth_cannot_take_over_password_account(): void {
  $local=User::factory()->create(['email'=>'victim@example.test']);
  $this->assertNotNull($local->password);
  $this->mockProvider('github',$this->socialUser('victim@example.test',[]));
  $this->get('/auth/github/callback')->assertRedirect('/login?error=oauth-link-denied');
  $this->assertGuest();
  $this->assertEquals('victim@example.test',User::find($local->id)->email);
 }
 public function test_existing_oauth_user_can_sign_in_again(): void {
  $this->mockProvider('google',$this->socialUser('r@example.test',['email_verified'=>true]));
  $this->get('/auth/google/callback')->assertRedirect('/');
  $this->assertAuthenticated();
  $this->assertDatabaseCount('users',1);
  auth()->logout();
  $this->mockProvider('google',$this->socialUser('r@example.test',['email_verified'=>true]));
  $this->get('/auth/google/callback')->assertRedirect('/');
  $this->assertAuthenticated();
  $this->assertDatabaseCount('users',1);
 }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_login_with_email_password(): void
    {
        $this->postJson('/auth/register',['name'=>'DeploySift User','email'=>'user@example.test','password'=>'password123','password_confirmation'=>'password123'])->assertCreated();
        $this->assertDatabaseHas('users',['email'=>'user@example.test']);
        $this->assertDatabaseHas('workspace_user',['user_id'=>1,'role'=>'owner']);
        $this->postJson('/auth/logout')->assertNoContent();
        $this->postJson('/auth/login',['email'=>'user@example.test','password'=>'password123'])->assertOk()->assertJsonPath('email','user@example.test');
    }
}

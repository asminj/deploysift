<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class WorkspaceApiTest extends TestCase { use RefreshDatabase;
 public function test_user_only_sees_own_workspaces(): void { DB::table('workspaces')->insert([['id'=>1,'name'=>'Mine','created_at'=>now(),'updated_at'=>now()],['id'=>2,'name'=>'Other','created_at'=>now(),'updated_at'=>now()]]);$user=User::factory()->create();DB::table('workspace_user')->insert(['workspace_id'=>1,'user_id'=>$user->id,'role'=>'owner','created_at'=>now(),'updated_at'=>now()]);$this->actingAs($user)->getJson('/api/workspaces')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name','Mine')->assertJsonPath('0.role','owner'); }
}

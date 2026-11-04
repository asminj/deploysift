<?php
namespace Tests\Feature;
use App\Models\Investigation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DiagnosticEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class AsyncProcessingTest extends TestCase {
 use RefreshDatabase;
 private function context(): array { $u=User::factory()->create(); $w=Workspace::create(['name'=>'Q']); $u->workspaces()->attach($w->id,['role'=>'owner']); $this->actingAs($u); return [$u,$w]; }
 public function test_duplicate_process_requests_queue_once(): void {
  config(['queue.default'=>'database']);
  [$u,$w]=$this->context();
  $id=Investigation::create(['workspace_id'=>$w->id,'name'=>'Dup','framework'=>'Laravel / PHP','status'=>'draft'])->id;
  $this->postJson("/api/investigations/$id/process")->assertStatus(202)->assertJsonPath('status','pending');
  $this->postJson("/api/investigations/$id/process")->assertStatus(202)->assertJsonPath('status','pending');
  $this->assertEquals(1,DB::table('jobs')->count());
  $this->assertEquals('pending',Investigation::find($id)->status);
 }
 public function test_failed_job_marks_investigation_failed(): void {
  [$u,$w]=$this->context();
  $id=Investigation::create(['workspace_id'=>$w->id,'name'=>'Boom','framework'=>'Laravel / PHP','status'=>'draft'])->id;
  $this->mock(DiagnosticEngine::class,function($m){$m->shouldReceive('run')->once()->andThrow(new \RuntimeException('engine exploded'));});
  $this->postJson("/api/investigations/$id/process")->assertStatus(500);
  $this->assertEquals('failed',Investigation::find($id)->status);
  $this->assertDatabaseHas('investigation_events',['investigation_id'=>$id,'type'=>'failed']);
 }
 public function test_completed_investigation_can_be_reprocessed(): void {
  [$u,$w]=$this->context();
  $id=Investigation::create(['workspace_id'=>$w->id,'name'=>'Re','framework'=>'Laravel / PHP','status'=>'draft'])->id;
  $this->postJson("/api/investigations/$id/process")->assertOk();
  $this->assertEquals('completed',Investigation::find($id)->status);
  $this->postJson("/api/investigations/$id/process")->assertOk();
  $this->assertEquals('completed',Investigation::find($id)->status);
 }
}

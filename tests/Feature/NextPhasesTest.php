<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class NextPhasesTest extends TestCase {
 use RefreshDatabase;
 private function setupUser(): array { $u=User::factory()->create(); $w=Workspace::create(['name'=>'Mine']); $u->workspaces()->attach($w->id,['role'=>'owner']); return [$u,$w]; }
 public function test_dashboard_metrics_reflect_records(): void {
  [$u,$w]=$this->setupUser();
  $this->actingAs($u)->postJson('/api/investigations',['name'=>'D1','workspace_id'=>$w->id])->assertCreated();
  $this->actingAs($u)->getJson('/api/dashboard')->assertOk()->assertJsonPath('total',1)->assertJsonPath('completed',0);
 }
 public function test_demo_launch_runs_pipeline(): void {
  [$u,$w]=$this->setupUser();
  $this->actingAs($u)->postJson('/api/demo/php',['workspace_id'=>$w->id])->assertCreated()->assertJsonPath('findings.0.identifier','PHP_VERSION_MISMATCH');
 }
 public function test_evidence_list_remove_investigation_delete_and_report_export(): void {
  [$u,$w]=$this->setupUser();
  $id=$this->actingAs($u)->postJson('/api/investigations',['name'=>'E1','workspace_id'=>$w->id])->assertCreated()->json('id');
  $eid=$this->actingAs($u)->postJson("/api/investigations/$id/evidence",['category'=>'project','name'=>'composer.json','content'=>'{"require":{"php":"^8.3"}}'])->assertCreated()->json('id');
  $this->actingAs($u)->getJson("/api/investigations/$id/evidence")->assertOk()->assertJsonCount(1);
  $this->actingAs($u)->postJson("/api/investigations/$id/process")->assertOk();
  $this->actingAs($u)->getJson("/api/investigations/$id/ai")->assertOk()->assertJsonPath('status','not_configured');
  $this->actingAs($u)->getJson("/api/investigations/$id/report/export")->assertOk()->assertHeader('Content-Disposition');
  $this->actingAs($u)->deleteJson("/api/investigations/$id/evidence/$eid")->assertNoContent();
  $outsider=User::factory()->create();
  $this->actingAs($outsider)->deleteJson("/api/investigations/$id")->assertForbidden();
  $this->actingAs($u)->deleteJson("/api/investigations/$id")->assertNoContent();
  $this->assertDatabaseMissing('investigations',['id'=>$id]);
 }
}

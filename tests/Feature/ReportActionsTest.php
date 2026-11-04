<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ReportActionsTest extends TestCase {
 use RefreshDatabase;
 public function test_report_carries_actions_checklist_and_resolvable_evidence(): void {
  $u=User::factory()->create(); $w=Workspace::create(['name'=>'Mine']); $u->workspaces()->attach($w->id,['role'=>'owner']);
  $id=$this->actingAs($u)->postJson('/api/investigations',['name'=>'R1','workspace_id'=>$w->id])->assertCreated()->json('id');
  $this->actingAs($u)->postJson("/api/investigations/$id/evidence",['category'=>'project','name'=>'composer.json','content'=>'{"require":{"php":"^8.3"}}'])->assertCreated();
  $this->actingAs($u)->postJson("/api/investigations/$id/evidence",['category'=>'hosting','name'=>'panel.txt','content'=>'PHP Version: 8.1.27'])->assertCreated();
  $this->actingAs($u)->postJson("/api/investigations/$id/process")->assertOk();
  $report=$this->actingAs($u)->getJson("/api/investigations/$id/report")->assertOk()->json();
  $this->assertNotEmpty($report['findings'][0]['recommended_actions']);
  $this->assertNotEmpty($report['verification_checklist']);
  $evidenceIds=$this->actingAs($u)->getJson("/api/investigations/$id/evidence")->assertOk()->json();
  $known=array_column($evidenceIds,'id');
  foreach($report['findings'] as $finding) foreach($finding['evidence_ids'] as $eid) $this->assertContains($eid,$known);
  $export=$this->actingAs($u)->getJson("/api/investigations/$id/report/export")->assertOk()->assertHeader('Content-Disposition')->json();
  $this->assertEquals($report['findings'][0]['recommended_actions'],$export['findings'][0]['recommended_actions']);
 }
}

<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class DashboardMetricsTest extends TestCase {
 use RefreshDatabase;
 private function owner(): array { $u=User::factory()->create(); $w=Workspace::create(['name'=>'Mine']); $u->workspaces()->attach($w->id,['role'=>'owner']); $this->actingAs($u); return [$u,$w]; }
 public function test_confirmed_counts_findings_not_reports(): void {
  [$u,$w]=$this->owner();
  $id=$this->postJson('/api/investigations',['name'=>'M1','workspace_id'=>$w->id])->assertCreated()->json('id');
  $this->postJson("/api/investigations/$id/evidence",['category'=>'project','name'=>'composer.json','content'=>'{"require":{"php":"^8.3"}}'])->assertCreated();
  $this->postJson("/api/investigations/$id/evidence",['category'=>'hosting','name'=>'panel.txt','content'=>'PHP Version: 8.1.27'])->assertCreated();
  $this->postJson("/api/investigations/$id/process")->assertOk();
  $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('confirmed_issues',1);
  $this->getJson("/api/investigations/$id/report")->assertOk();
  $this->getJson("/api/investigations/$id/report")->assertOk();
  $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('confirmed_issues',1);
 }
 public function test_empty_report_does_not_change_confirmed_count(): void {
  [$u,$w]=$this->owner();
  $before=$this->getJson('/api/dashboard')->assertOk()->json('confirmed_issues');
  $id=$this->postJson('/api/investigations',['name'=>'Empty','workspace_id'=>$w->id])->assertCreated()->json('id');
  $this->postJson("/api/investigations/$id/process")->assertOk();
  $this->getJson("/api/investigations/$id/report")->assertOk();
  $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('confirmed_issues',$before);
 }
}

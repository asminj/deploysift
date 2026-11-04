<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;
class ArchiveAiQueueTest extends TestCase {
 use RefreshDatabase;
 private function setupUser(): array { $u=User::factory()->create(); $w=Workspace::create(['name'=>'Mine']); $u->workspaces()->attach($w->id,['role'=>'owner']); return [$u,$w]; }
 private function makeZip(array $entries): string { $path=tempnam(sys_get_temp_dir(),'arc').'.zip'; $z=new ZipArchive(); $z->open($path,ZipArchive::CREATE); foreach($entries as $name=>$content) $z->addFromString($name,$content); $z->close(); return $path; }
 public function test_valid_zip_is_ingested_and_diagnosed(): void {
  [$u,$w]=$this->setupUser();
  $id=$this->actingAs($u)->postJson('/api/investigations',['name'=>'Z1','workspace_id'=>$w->id])->assertCreated()->json('id');
  $zip=$this->makeZip(['composer.json'=>'{"require":{"php":"^8.3"}}','panel.txt'=>'PHP Version: 8.1.27']);
  $this->actingAs($u)->post("/api/investigations/$id/upload",['category'=>'project','file'=>new UploadedFile($zip,'project.zip','application/zip',null,true)])->assertCreated();
  @unlink($zip);
  $this->actingAs($u)->postJson("/api/investigations/$id/process")->assertOk()->assertJsonPath('findings.0.identifier','PHP_VERSION_MISMATCH');
 }
 public function test_traversal_and_executable_archives_are_rejected(): void {
  [$u,$w]=$this->setupUser();
  $id=$this->actingAs($u)->postJson('/api/investigations',['name'=>'Z2','workspace_id'=>$w->id])->assertCreated()->json('id');
  $evil=$this->makeZip(['../evil.txt'=>'x']); 
  $this->actingAs($u)->post("/api/investigations/$id/upload",['category'=>'project','file'=>new UploadedFile($evil,'evil.zip','application/zip',null,true)])->assertStatus(422);
  @unlink($evil);
  $exe=$this->makeZip(['run.exe'=>'MZ']); 
  $this->actingAs($u)->post("/api/investigations/$id/upload",['category'=>'project','file'=>new UploadedFile($exe,'tool.zip','application/zip',null,true)])->assertStatus(422);
  @unlink($exe);
  $this->assertDatabaseCount('evidence_files',0);
 }
 public function test_ai_reports_honest_unconfigured_status(): void {
  [$u,$w]=$this->setupUser();
  $id=$this->actingAs($u)->postJson('/api/investigations',['name'=>'A1','workspace_id'=>$w->id])->assertCreated()->json('id');
  $this->actingAs($u)->getJson("/api/investigations/$id/ai")->assertOk()->assertJsonPath('status','not_configured');
  $this->actingAs($u)->postJson("/api/investigations/$id/ai")->assertOk()->assertJsonPath('status','not_configured');
  $this->assertDatabaseHas('ai_analysis_runs',['investigation_id'=>$id,'state'=>'skipped']);
  $this->assertDatabaseCount('diagnostic_findings',0);
 }
 public function test_process_queues_when_driver_is_async(): void {
  [$u,$w]=$this->setupUser();
  config(['queue.default'=>'database']);
  $id=$this->actingAs($u)->postJson('/api/investigations',['name'=>'Q1','workspace_id'=>$w->id])->assertCreated()->json('id');
  $this->actingAs($u)->postJson("/api/investigations/$id/process")->assertStatus(202)->assertJsonPath('status','pending');
  $this->assertDatabaseHas('jobs',[]);
  $this->assertDatabaseHas('investigations',['id'=>$id,'status'=>'pending']);
 }
}

<?php
namespace Tests\Feature;
use App\Models\EvidenceFile;
use App\Models\Investigation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\DiagnosticEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class UploadRulesTest extends TestCase {
 use RefreshDatabase;
 private function investigation(): array { $w=Workspace::create(['name'=>'T']); $u=User::factory()->create(); $u->workspaces()->attach($w->id,['role'=>'owner']); $this->actingAs($u); return [Investigation::create(['workspace_id'=>$w->id,'name'=>'Rules','framework'=>'Laravel / PHP','status'=>'draft']),$u]; }
 private function evidence(Investigation $i,string $category,string $name,string $content): void { EvidenceFile::create(['investigation_id'=>$i->id,'category'=>$category,'original_name'=>$name,'sha256'=>hash('sha256',$content),'size'=>strlen($content),'content'=>$content]); }
 private function diagnose(Investigation $i): Investigation { app(DiagnosticEngine::class)->run($i->fresh('evidence')); return $i->fresh('findings'); }
 public function test_missing_app_key_from_log_and_empty_env(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'logs','laravel.log','No application encryption key has been specified.');
  $found=$this->diagnose($i)->findings->firstWhere('identifier','MISSING_APP_KEY');
  $this->assertNotNull($found); $this->assertStringNotContainsStringIgnoringCase('base64',$found->detail);
  [$j,$v]=$this->investigation(); $this->evidence($j,'project','.env',"APP_NAME=DeploySift\nAPP_KEY=\n");
  $this->assertNotNull($this->diagnose($j)->findings->firstWhere('identifier','MISSING_APP_KEY'));
 }
 public function test_present_app_key_produces_no_key_finding(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'project','.env',"APP_NAME=DeploySift\nAPP_KEY=base64:AbCdEfGhIjKlMnOpQrStUvWxYz0123456789AB=\n");
  $this->assertNull($this->diagnose($i)->findings->firstWhere('identifier','MISSING_APP_KEY'));
  $this->assertDatabaseMissing('diagnostic_findings',['investigation_id'=>$i->id,'identifier'=>'MISSING_APP_KEY']);
 }
 public function test_composer_platform_conflict(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'logs','composer.txt',"Your requirements could not be resolved to an installable set.\nConclusion: don't install laravel/framework v10.0.0");
  $found=$this->diagnose($i)->findings->firstWhere('identifier','COMPOSER_PLATFORM_CONFLICT');
  $this->assertNotNull($found); $this->assertNotEmpty($found->evidence_ids);
 }
 public function test_storage_permission_failure(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'logs','laravel.log','The stream or file "storage/logs/laravel.log" could not be opened: Failed to open stream: Permission denied');
  $this->assertNotNull($this->diagnose($i)->findings->firstWhere('identifier','STORAGE_PERMISSION_FAILURE'));
 }
 public function test_document_root_mismatch_and_clean_root(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'hosting','panel.txt','Document Root: /home/user/public_html');
  $found=$this->diagnose($i)->findings->firstWhere('identifier','DOCUMENT_ROOT_MISMATCH');
  $this->assertNotNull($found); $this->assertStringContainsString('/home/user/public_html',$found->detail);
  [$j,$v]=$this->investigation(); $this->evidence($j,'hosting','panel.txt','Document Root: /home/user/public');
  $this->assertNull($this->diagnose($j)->findings->firstWhere('identifier','DOCUMENT_ROOT_MISMATCH'));
 }
 public function test_browser_js_exception_correlates_with_404(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'browser','console.txt',"Uncaught TypeError: Cannot read properties of null (reading 'app')\n    at https://example.test/build/app.js:10:5");
  $this->evidence($i,'browser','network.txt','GET /build/app.js HTTP 404');
  $found=$this->diagnose($i)->findings->firstWhere('identifier','BROWSER_JS_EXCEPTION');
  $this->assertNotNull($found); $this->assertGreaterThanOrEqual(2,count($found->evidence_ids));
 }
 public function test_http_403_and_404_findings(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'browser','network.txt','GET /assets/logo.png HTTP 403');
  $this->assertNotNull($this->diagnose($i)->findings->firstWhere('identifier','HTTP_ASSET_FORBIDDEN'));
  [$j,$v]=$this->investigation(); $this->evidence($j,'browser','network.txt','GET /static/old-bundle.js HTTP 404');
  $found=$this->diagnose($j)->findings;
  $this->assertNotNull($found->firstWhere('identifier','HTTP_ASSET_MISSING'));
  $this->assertNull($found->firstWhere('identifier','VITE_ASSET_FAILURE'));
 }
 public function test_insufficient_and_contradictory_evidence_states(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'logs','notes.txt','Deployment went fine, nothing to report.');
  $this->assertCount(0,$this->diagnose($i)->findings);
  $report=$this->actingAs($u)->getJson("/api/investigations/{$i->id}/report")->assertOk()->json();
  $this->assertCount(0,$report['findings']);
  $this->assertNotEmpty($report['verification_checklist']);
  [$j,$v]=$this->investigation(); $this->evidence($j,'project','composer.json','{"require":{"php":"^8.3"}}'); $this->evidence($j,'hosting','panel.txt','PHP Version: 8.1.27');
  $this->diagnose($j);
  $linked=$this->actingAs($v)->getJson("/api/investigations/{$j->id}/report")->assertOk()->json();
  $finding=collect($linked['findings'])->firstWhere('identifier','PHP_VERSION_MISMATCH');
  $this->assertContains('No contradicting evidence identified in the current evidence set.',$finding['contradicting_observations']);
 }
 public function test_report_links_supporting_evidence_names(): void {
  [$i,$u]=$this->investigation(); $this->evidence($i,'logs','laravel.log','SQLSTATE[HY000] [1045] Access denied for user');
  $ran=$this->diagnose($i); $this->actingAs($u)->postJson("/api/investigations/{$i->id}/process")->assertOk();
  $report=$this->actingAs($u)->getJson("/api/investigations/{$i->id}/report")->assertOk()->json();
  $finding=collect($report['findings'])->firstWhere('identifier','DATABASE_CONNECTION_FAILURE');
  $this->assertNotNull($finding); $this->assertNotEmpty($finding['supporting_observations']);
  $this->assertStringContainsString('laravel.log',$finding['supporting_observations'][0]);
  $this->assertNotEmpty($finding['verification_steps']); $this->assertNotEmpty($finding['recommended_actions']);
  $this->assertNotEmpty($report['verification_checklist']); $this->assertNotNull($ran);
 }
 public function test_upload_validation_rejects_executables_and_oversize(): void {
  [$i,$u]=$this->investigation();
  $this->actingAs($u)->post("/api/investigations/{$i->id}/upload",['category'=>'project','file'=>UploadedFile::fake()->create('run.exe',10)])->assertStatus(422);
  $this->actingAs($u)->post("/api/investigations/{$i->id}/upload",['category'=>'project','file'=>UploadedFile::fake()->create('big.txt',20000)])->assertStatus(422);
  $this->assertDatabaseCount('evidence_files',0);
 }
}

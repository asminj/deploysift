<?php
namespace Tests\Feature;
use App\Models\EvidenceFile;
use App\Models\Investigation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class EvidenceDeletionTest extends TestCase {
 use RefreshDatabase;
 private function owner(): array { $u=User::factory()->create(); $w=Workspace::create(['name'=>'Mine']); $u->workspaces()->attach($w->id,['role'=>'owner']); $this->actingAs($u); return [$u,$w]; }
 private function upload(Workspace $w,string $name='note.txt',string $content='hello evidence'): array {
  $id=Investigation::create(['workspace_id'=>$w->id,'name'=>'Del','framework'=>'Laravel / PHP','status'=>'draft'])->id;
  $e=$this->post("/api/investigations/$id/upload",['category'=>'project','file'=>UploadedFile::fake()->createWithContent($name,$content)])->assertCreated()->json();
  return [$id,$e];
 }
 public function test_delete_removes_stored_file_and_record(): void {
  [$u,$w]=$this->owner(); [$id,$e]=$this->upload($w);
  $path=EvidenceFile::find($e['id'])->storage_path;
  $this->assertNotEmpty($path);
  $this->assertTrue(Storage::disk('local')->exists($path));
  $this->deleteJson("/api/investigations/$id/evidence/{$e['id']}")->assertNoContent();
  $this->assertFalse(Storage::disk('local')->exists($path));
  $this->assertDatabaseMissing('evidence_files',['id'=>$e['id']]);
 }
 public function test_delete_cleans_correlation_references(): void {
  [$u,$w]=$this->owner();
  $id=Investigation::create(['workspace_id'=>$w->id,'name'=>'Corr','framework'=>'Laravel / PHP','status'=>'draft'])->id;
  $a=$this->post("/api/investigations/$id/upload",['category'=>'project','file'=>UploadedFile::fake()->createWithContent('composer.json','{"require":{"php":"^8.3"}}')])->assertCreated()->json();
  $b=$this->post("/api/investigations/$id/upload",['category'=>'hosting','file'=>UploadedFile::fake()->createWithContent('panel.txt','PHP Version: 8.1.27')])->assertCreated()->json();
  $this->postJson("/api/investigations/$id/process")->assertOk();
  $rel=\App\Models\EvidenceRelationship::where('investigation_id',$id)->first();
  $this->assertContains($a['id'],$rel->evidence_ids);
  $this->deleteJson("/api/investigations/$id/evidence/{$a['id']}")->assertNoContent();
  $this->assertNotContains($a['id'],$rel->fresh()->evidence_ids);
  $this->assertContains($b['id'],$rel->fresh()->evidence_ids);
 }
 public function test_delete_missing_file_still_removes_record(): void {
  [$u,$w]=$this->owner(); [$id,$e]=$this->upload($w);
  Storage::disk('local')->delete(EvidenceFile::find($e['id'])->storage_path);
  $this->deleteJson("/api/investigations/$id/evidence/{$e['id']}")->assertNoContent();
  $this->assertDatabaseMissing('evidence_files',['id'=>$e['id']]);
 }
 public function test_repeat_delete_returns_not_found(): void {
  [$u,$w]=$this->owner(); [$id,$e]=$this->upload($w);
  $this->deleteJson("/api/investigations/$id/evidence/{$e['id']}")->assertNoContent();
  $this->deleteJson("/api/investigations/$id/evidence/{$e['id']}")->assertNotFound();
 }
 public function test_outsider_cannot_delete_evidence(): void {
  [$u,$w]=$this->owner(); [$id,$e]=$this->upload($w);
  $other=User::factory()->create(); $this->actingAs($other);
  $this->deleteJson("/api/investigations/$id/evidence/{$e['id']}")->assertForbidden();
  $this->assertDatabaseHas('evidence_files',['id'=>$e['id']]);
 }
 public function test_unsafe_stored_path_is_rejected(): void {
  [$u,$w]=$this->owner(); [$id,$e]=$this->upload($w);
  $m=EvidenceFile::find($e['id']); $m->storage_path='../../.env'; $m->save();
  $this->deleteJson("/api/investigations/$id/evidence/{$e['id']}")->assertStatus(422);
  $this->assertDatabaseHas('evidence_files',['id'=>$e['id']]);
 }
 public function test_evidence_upload_normalizes_encoding(): void {
  [$u,$w]=$this->owner(); [$id,$e]=$this->upload($w,'notes.txt',"caf\xe9 na\xefve \x97 em dash");
  $stored=EvidenceFile::find($e['id'])->content;
  $this->assertSame(1,preg_match('//u',$stored));
  $this->assertStringNotContainsString("\xc3\xa2",$stored);
  $this->assertStringContainsString('caf',$stored);
 }
}

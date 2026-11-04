<?php

namespace Tests\Feature;

use App\Models\EvidenceFile;
use App\Models\Investigation;
use App\Services\DiagnosticEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiagnosticEngineTest extends TestCase
{
    use RefreshDatabase;

    private function investigation(): Investigation
    {
        DB::table('workspaces')->insert(['id'=>1,'name'=>'Test','created_at'=>now(),'updated_at'=>now()]);
        return Investigation::create(['workspace_id'=>1,'name'=>'Test deploy','framework'=>'Laravel / PHP','status'=>'pending']);
    }

    private function evidence(Investigation $i, string $category, string $name, string $content): void
    {
        EvidenceFile::create(['investigation_id'=>$i->id,'category'=>$category,'original_name'=>$name,'sha256'=>hash('sha256',$content),'size'=>strlen($content),'content'=>$content]);
    }

    public function test_php_version_mismatch_is_evidence_linked(): void
    {
        $i=$this->investigation();
        $this->evidence($i,'project','composer.json','{"require":{"php":"^8.3"}}');
        $this->evidence($i,'hosting','panel.txt','PHP Version: 8.1.27');
        app(DiagnosticEngine::class)->run($i->fresh('evidence'));
        $this->assertDatabaseHas('diagnostic_findings',['identifier'=>'PHP_VERSION_MISMATCH','investigation_id'=>$i->id]);
        $this->assertDatabaseHas('evidence_relationships',['relationship'=>'supports','investigation_id'=>$i->id]);
    }

    public function test_database_and_vite_errors_are_detected(): void
    {
        $i=$this->investigation();
        $this->evidence($i,'logs','laravel.log','SQLSTATE[HY000] [1045] Access denied for user');
        $this->evidence($i,'browser','console.txt','Vite manifest not found at public/build/manifest.json');
        app(DiagnosticEngine::class)->run($i->fresh('evidence'));
        $this->assertDatabaseHas('diagnostic_findings',['identifier'=>'DATABASE_CONNECTION_FAILURE','investigation_id'=>$i->id]);
        $this->assertDatabaseHas('diagnostic_findings',['identifier'=>'VITE_ASSET_FAILURE','investigation_id'=>$i->id]);
    }
}

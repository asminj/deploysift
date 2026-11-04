<?php

namespace Database\Seeders;

use App\Models\{EvidenceFile, Investigation};
use App\Services\DiagnosticEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('workspaces')->updateOrInsert(['id'=>1], ['name'=>'DeploySift Demo Workspace','created_at'=>now(),'updated_at'=>now()]);
        $scenarios=[
            ['Laravel Production Failure Demo',[['project','composer.json','{"require":{"php":"^8.3"}}'],['hosting','hosting-panel.txt','PHP Version: 8.1.27'],['logs','laravel.log','PHP Fatal error: requires php 8.3'],['browser','network.txt','GET / HTTP 500']]],
            ['Database Connection Failure Demo',[['project','database.php','DB_CONNECTION=mysql'],['logs','laravel.log','SQLSTATE[HY000] [1045] Access denied for user deploysift'],['browser','network.txt','POST /login HTTP 500']]],
            ['Vite Production Asset Failure Demo',[['project','vite.config.ts','export default defineConfig({});'],['logs','deploy.txt','Vite manifest not found at public/build/manifest.json'],['browser','console.txt','Failed to load module script: /build/assets/app.js HTTP 404']]],
        ];
        foreach($scenarios as [$name,$files]) { $i=Investigation::firstOrCreate(['name'=>$name],['workspace_id'=>1,'framework'=>'Laravel / PHP','environment'=>'Synthetic test data','problem'=>'Synthetic deployment failure','status'=>'pending','is_synthetic'=>true]); foreach($files as [$category,$file,$content]) EvidenceFile::firstOrCreate(['investigation_id'=>$i->id,'sha256'=>hash('sha256',$content)],['category'=>$category,'original_name'=>$file,'mime_type'=>'text/plain','size'=>strlen($content),'content'=>$content,'state'=>'pending']); app(DiagnosticEngine::class)->run($i->fresh('evidence')); }
    }
}

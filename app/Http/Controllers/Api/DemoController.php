<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Investigation;
use App\Services\DiagnosticEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class DemoController extends Controller {
 private const SCENARIOS=[
  'php'=>['Laravel Production Failure Demo',[['project','composer.json','{"require":{"php":"^8.3"}}'],['hosting','hosting-panel.txt','PHP Version: 8.1.27'],['logs','laravel.log','PHP Fatal error: requires php 8.3'],['browser','network.txt','GET / HTTP 500']]],
  'database'=>['Database Connection Failure Demo',[['project','database.php','DB_CONNECTION=mysql'],['logs','laravel.log','SQLSTATE[HY000] [1045] Access denied for user deploysift'],['browser','network.txt','POST /login HTTP 500']]],
  'vite'=>['Vite Production Asset Failure Demo',[['project','vite.config.ts','export default defineConfig({});'],['logs','deploy.txt','Vite manifest not found at public/build/manifest.json'],['browser','console.txt','Failed to load module script: /build/assets/app.js HTTP 404']]],
 ];
 public function store(Request $r,string $scenario,DiagnosticEngine $engine){
  abort_unless(isset(self::SCENARIOS[$scenario]),404,'Unknown demo scenario.');
  $data=$r->validate(['workspace_id'=>'required|integer|exists:workspaces,id']);
  Gate::authorize('create',[Investigation::class,$data['workspace_id']]);
  [$name,$files]=self::SCENARIOS[$scenario];
  $i=Investigation::create(['workspace_id'=>$data['workspace_id'],'name'=>$name,'framework'=>'Laravel / PHP','environment'=>'Synthetic test data','problem'=>'Synthetic deployment failure','status'=>'pending','is_synthetic'=>true]);
  foreach($files as [$category,$file,$content]) $i->evidence()->firstOrCreate(['sha256'=>hash('sha256',$content)],['category'=>$category,'original_name'=>$file,'mime_type'=>'text/plain','size'=>strlen($content),'content'=>$content,'state'=>'pending']);
  $engine->run($i->fresh('evidence'));
  $i->events()->create(['type'=>'demo','message'=>"Demo scenario launched: $scenario"]);
  return response()->json($i->fresh()->load('evidence','findings'),201);
 }
}

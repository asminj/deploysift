<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\DiagnosticReport;
use App\Models\Investigation;
use Illuminate\Http\Request;
class DashboardController extends Controller {
 public function index(Request $r){
  $ids=$r->user()->workspaces()->pluck('workspaces.id');
  $base=Investigation::whereIn('workspace_id',$ids);
  $total=(clone $base)->count();
  $completed=(clone $base)->where('status','completed')->count();
  $review=(clone $base)->whereIn('status',['requires_review','failed'])->count();
  $confirmed=DiagnosticReport::whereIn('investigation_id',(clone $base)->pluck('id'))->count();
  $recent=(clone $base)->latest()->take(5)->get(['id','name','status','updated_at']);
  $reports=DiagnosticReport::whereIn('investigation_id',(clone $base)->pluck('id'))->latest()->take(5)->get(['investigation_id','updated_at']);
  return response()->json(['total'=>$total,'completed'=>$completed,'requires_review'=>$review,'confirmed_issues'=>$confirmed,'recent_investigations'=>$recent,'recent_reports'=>$reports]);
 }
}

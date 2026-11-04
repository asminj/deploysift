<?php
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\InvestigationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WorkspaceController;
Route::middleware('auth')->group(function () {
Route::get('me', fn (Request $r) => $r->user()->only(['id','name','email']));
Route::get('dashboard',[DashboardController::class,'index']);
Route::get('workspaces',[WorkspaceController::class,'index']);
Route::apiResource('investigations',InvestigationController::class)->only(['index','store','show','destroy']);
Route::get('investigations/{investigation}/evidence',[InvestigationController::class,'evidenceIndex']);
Route::post('investigations/{investigation}/evidence',[InvestigationController::class,'evidence']);
Route::delete('investigations/{investigation}/evidence/{evidence}',[InvestigationController::class,'evidenceDestroy']);
Route::post('investigations/{investigation}/upload',[InvestigationController::class,'upload']);
Route::post('investigations/{investigation}/process',[InvestigationController::class,'process']);
Route::get('investigations/{investigation}/report',[InvestigationController::class,'report']);
Route::get('investigations/{investigation}/report/export',[InvestigationController::class,'reportExport']);
Route::get('investigations/{investigation}/ai',[InvestigationController::class,'ai']);
Route::post('investigations/{investigation}/ai',[InvestigationController::class,'aiAnalyze']);
Route::post('demo/{scenario}',[DemoController::class,'store']);
});

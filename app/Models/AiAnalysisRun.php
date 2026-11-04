<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AiAnalysisRun extends Model { protected $guarded=[]; protected $casts=['input'=>'array','output'=>'array']; }

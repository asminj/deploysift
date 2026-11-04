<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DiagnosticFinding extends Model { protected $guarded=[]; protected $casts=['evidence_ids'=>'array']; }

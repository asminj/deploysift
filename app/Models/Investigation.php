<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Investigation extends Model { protected $guarded=[]; protected $casts=['is_synthetic'=>'boolean']; public function evidence():HasMany{return $this->hasMany(EvidenceFile::class);} public function findings():HasMany{return $this->hasMany(DiagnosticFinding::class);} public function relationships():HasMany{return $this->hasMany(EvidenceRelationship::class);} public function workspace():\Illuminate\Database\Eloquent\Relations\BelongsTo{return $this->belongsTo(Workspace::class);} public function events():HasMany{return $this->hasMany(InvestigationEvent::class);} public function aiRuns():HasMany{return $this->hasMany(AiAnalysisRun::class);} }

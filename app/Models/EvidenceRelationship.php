<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvidenceRelationship extends Model { protected $guarded=[]; protected $casts=['evidence_ids'=>'array']; }

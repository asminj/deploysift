<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('workspaces', function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); });
        Schema::create('investigations', function (Blueprint $t) { $t->id(); $t->foreignId('workspace_id')->constrained(); $t->string('name'); $t->text('application_description')->nullable(); $t->string('framework')->default('Laravel / PHP'); $t->string('environment')->nullable(); $t->text('problem')->nullable(); $t->string('status')->default('draft')->index(); $t->boolean('is_synthetic')->default(false); $t->timestamps(); });
        Schema::create('evidence_files', function (Blueprint $t) { $t->id(); $t->foreignId('investigation_id')->constrained()->cascadeOnDelete(); $t->string('category'); $t->string('original_name'); $t->string('storage_path')->nullable(); $t->string('mime_type')->nullable(); $t->unsignedBigInteger('size')->default(0); $t->string('sha256',64); $t->text('content')->nullable(); $t->string('state')->default('pending'); $t->json('extracted')->nullable(); $t->timestamps(); $t->unique(['investigation_id','sha256']); });
        Schema::create('diagnostic_findings', function (Blueprint $t) { $t->id(); $t->foreignId('investigation_id')->constrained()->cascadeOnDelete(); $t->string('identifier')->index(); $t->string('category'); $t->string('severity'); $t->string('title'); $t->text('detail'); $t->text('expected')->nullable(); $t->text('observed')->nullable(); $t->text('confidence'); $t->json('evidence_ids'); $t->string('rule_name'); $t->timestamps(); });
        Schema::create('evidence_relationships', function (Blueprint $t) { $t->id(); $t->foreignId('investigation_id')->constrained()->cascadeOnDelete(); $t->foreignId('finding_id')->nullable()->constrained('diagnostic_findings')->nullOnDelete(); $t->string('relationship'); $t->json('evidence_ids'); $t->text('detail'); $t->timestamps(); });
        Schema::create('ai_analysis_runs', function (Blueprint $t) { $t->id(); $t->foreignId('investigation_id')->constrained()->cascadeOnDelete(); $t->string('provider'); $t->string('model')->nullable(); $t->string('state'); $t->json('input')->nullable(); $t->json('output')->nullable(); $t->text('error')->nullable(); $t->timestamps(); });
        Schema::create('diagnostic_reports', function (Blueprint $t) { $t->id(); $t->foreignId('investigation_id')->unique()->constrained()->cascadeOnDelete(); $t->json('content'); $t->timestamps(); });
        Schema::create('investigation_events', function (Blueprint $t) { $t->id(); $t->foreignId('investigation_id')->constrained()->cascadeOnDelete(); $t->string('type'); $t->text('message'); $t->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('investigation_events'); Schema::dropIfExists('diagnostic_reports'); Schema::dropIfExists('ai_analysis_runs'); Schema::dropIfExists('evidence_relationships'); Schema::dropIfExists('diagnostic_findings'); Schema::dropIfExists('evidence_files'); Schema::dropIfExists('investigations'); Schema::dropIfExists('workspaces'); }
};

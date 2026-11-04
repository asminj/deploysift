<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('workspace_user',function(Blueprint $t){$t->id();$t->foreignId('workspace_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('role',20)->default('member');$t->timestamps();$t->unique(['workspace_id','user_id']);}); }
 public function down(): void { Schema::dropIfExists('workspace_user'); }
};

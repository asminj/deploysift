<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_investigation_evidence_processing_and_report_workflow(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Test']);
        $user->workspaces()->attach($workspace->id, ['role' => 'owner']);

        $created = $this->actingAs($user)->postJson('/api/investigations', ['name' => 'API demo', 'framework' => 'Laravel / PHP', 'problem' => 'HTTP 500', 'workspace_id' => $workspace->id])->assertCreated();
        $id = $created->json('id');
        $this->actingAs($user)->postJson("/api/investigations/$id/evidence", ['category' => 'project', 'name' => 'composer.json', 'content' => '{"require":{"php":"^8.3"}}'])->assertCreated();
        $this->actingAs($user)->postJson("/api/investigations/$id/evidence", ['category' => 'hosting', 'name' => 'hosting.txt', 'content' => 'PHP Version: 8.1.27'])->assertCreated();
        $this->actingAs($user)->postJson("/api/investigations/$id/process")->assertOk()->assertJsonPath('findings.0.identifier', 'PHP_VERSION_MISMATCH');
        $this->actingAs($user)->getJson("/api/investigations/$id/report")->assertOk()->assertJsonPath('findings.0.identifier', 'PHP_VERSION_MISMATCH');
        $this->assertDatabaseHas('diagnostic_reports', ['investigation_id' => $id]);
    }

    public function test_investigation_routes_require_workspace_membership(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::create(['name' => 'Mine']);
        $owner->workspaces()->attach($workspace->id, ['role' => 'owner']);
        $outsider = User::factory()->create();

        $this->getJson('/api/investigations')->assertUnauthorized();

        $id = $this->actingAs($owner)->postJson('/api/investigations', ['name' => 'Scoped', 'workspace_id' => $workspace->id])->assertCreated()->json('id');
        $this->actingAs($outsider)->getJson("/api/investigations/$id")->assertForbidden();
        $this->actingAs($outsider)->postJson('/api/investigations', ['name' => 'Nope', 'workspace_id' => $workspace->id])->assertForbidden();
    }
}

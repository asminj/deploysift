<?php
namespace App\Policies;
use App\Models\{Investigation,User};
class InvestigationPolicy {
 private function role(User $user, Investigation $investigation): ?string { return $user->workspaces()->whereKey($investigation->workspace_id)->value('workspace_user.role'); }
 public function view(User $user, Investigation $investigation): bool { return in_array($this->role($user,$investigation),['owner','member','viewer'],true); }
 public function create(User $user, int $workspaceId): bool { return in_array($user->workspaces()->whereKey($workspaceId)->value('workspace_user.role'),['owner','member'],true); }
 public function update(User $user, Investigation $investigation): bool { return in_array($this->role($user,$investigation),['owner','member'],true); }
 public function delete(User $user, Investigation $investigation): bool { return $this->role($user,$investigation)==='owner'; }
}

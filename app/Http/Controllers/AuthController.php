<?php
namespace App\Http\Controllers;
use App\Models\{User,Workspace}; use Illuminate\Http\Request; use Illuminate\Support\Facades\Auth; use Illuminate\Support\Facades\Hash; use Laravel\Socialite\Facades\Socialite;
class AuthController extends Controller {
 public function register(Request $r){$d=$r->validate(['name'=>'required|string|max:255','email'=>'required|email|unique:users','password'=>'required|string|min:8|confirmed']);$u=User::create(['name'=>$d['name'],'email'=>$d['email'],'password'=>Hash::make($d['password'])]);$w=Workspace::create(['name'=>$u->name."'s Workspace"]);$u->workspaces()->attach($w->id,['role'=>'owner']);Auth::login($u);return response()->json($u,201);}
 public function login(Request $r){$d=$r->validate(['email'=>'required|email','password'=>'required|string']);if(!Auth::attempt($d))return response()->json(['message'=>'Invalid credentials.'],422);$r->session()->regenerate();return response()->json($r->user());}
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return response()->noContent();}
 public function redirect(string $provider){abort_unless(in_array($provider,['google','github'],true),404);abort_unless(config("services.$provider.client_id"),503,'OAuth provider is not configured.');return Socialite::driver($provider)->redirect();}
 public function callback(string $provider){abort_unless(in_array($provider,['google','github'],true),404);$p=Socialite::driver($provider)->user();$u=User::updateOrCreate(['email'=>$p->getEmail()],['name'=>$p->getName()?:$p->getNickname()?:'DeploySift user']);if($u->workspaces()->count()===0){$w=Workspace::create(['name'=>$u->name."'s Workspace"])->fresh();$u->workspaces()->attach($w->id,['role'=>'owner']);}Auth::login($u,true);return redirect('/');}
}

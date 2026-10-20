<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Technicien;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search', $request->input('q'));
        $users = User::query()
            ->when($search, fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->role))
            ->orderBy('name')->paginate(10)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::create($request->safe()->except(['password_confirmation', 'phone', 'speciality', 'skills']));
            $this->syncTechnicianProfile($user, $request->safe()->only(['phone', 'speciality', 'skills']));
        });

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé.');
    }

    public function edit(User $user): View
    {
        $user->load('technicien');

        return view('users.edit', compact('user'));
    }

    public function show(User $user): View
    {
        $user->load('technicien');

        return view('users.show', compact('user'));
    }

    public function approveTechnician(User $user): RedirectResponse
    {
        abort_unless($user->role === 'technician', 404);

        if ($user->technician_approval_status === User::TECHNICIAN_APPROVAL_APPROVED) {
            return back()->with('success', 'Ce compte technicien est déjà approuvé.');
        }

        $user->update([
            'technician_approval_status' => User::TECHNICIAN_APPROVAL_APPROVED,
        ]);

        return back()->with('success', 'Le compte technicien a été approuvé.');
    }

    public function rejectTechnician(User $user): RedirectResponse
    {
        abort_unless($user->role === 'technician', 404);

        if ($user->technician_approval_status !== User::TECHNICIAN_APPROVAL_PENDING) {
            return back()->withErrors(['technician' => 'Seules les demandes en attente peuvent être refusées.']);
        }

        $user->update([
            'technician_approval_status' => User::TECHNICIAN_APPROVAL_REJECTED,
        ]);

        return back()->with('success', 'La demande de compte technicien a été refusée.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $technicianProfile = $request->safe()->only(['phone', 'speciality', 'skills']);
        $data = $request->safe()->except(['password_confirmation', 'password', 'phone', 'speciality', 'skills']);
        if ($request->user()->is($user) && $request->role !== 'admin') {
            return back()->withErrors(['role' => 'Vous ne pouvez pas retirer votre propre rôle administrateur.'])->withInput();
        }
        if ($user->role === 'admin' && $request->role !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['role' => 'Le dernier administrateur doit conserver son rôle.'])->withInput();
        }
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        DB::transaction(function () use ($user, $data, $technicianProfile): void {
            $user->update($data);
            $this->syncTechnicianProfile($user, $technicianProfile);
        });

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->withErrors(['user' => 'Vous ne pouvez pas supprimer votre propre compte.']);
        }
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->withErrors(['user' => 'Le dernier administrateur ne peut pas être supprimé.']);
        }
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Utilisateur supprimé.');
    }

    private function syncTechnicianProfile(User $user, array $profileData = []): void
    {
        if ($user->role !== 'technician') {
            return;
        }

        $technician = $user->technicien
            ?? Technicien::whereNull('user_id')->where('email', $user->email)->first()
            ?? new Technicien(['status' => 'available']);

        $technician->fill([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ])->fill($profileData)->save();
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Actions\Households\CreateHousehold;
use App\Actions\Households\DeleteHousehold;
use App\Actions\Households\InviteHouseholdMember;
use App\Actions\Households\ManageHouseholdMembers;
use App\Enums\HouseholdRole;
use App\Http\Requests\Households\HouseholdRequest;
use App\Http\Requests\Households\InviteMemberRequest;
use App\Http\Requests\Households\MemberRoleRequest;
use App\Http\Resources\HouseholdInvitationResource;
use App\Http\Resources\HouseholdResource;
use App\Http\Resources\MemberResource;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HouseholdController extends WebController
{
    public function index(Request $request): Response
    {
        return Inertia::render('households/index', [
            'households' => $this->props(HouseholdResource::collection(
                $this->actor($request)->households()->withCount('members')->orderBy('name')->get()
            )),
        ]);
    }

    public function store(HouseholdRequest $request, CreateHousehold $create): RedirectResponse
    {
        /** @var array{name: string, timezone?: string|null} $validated */
        $validated = $request->validated();

        return to_route('households.show', $create->handle($this->actor($request), $validated));
    }

    public function show(Household $household): Response
    {
        Gate::authorize('view', $household);

        $canManage = Gate::allows('update', $household);

        return Inertia::render('households/settings', [
            'household' => $this->props(HouseholdResource::make($household->loadCount('members'))),
            'members' => $this->props(MemberResource::collection($household->members()->orderBy('name')->get())),
            'invitations' => $canManage ? $this->props(HouseholdInvitationResource::collection($household->invitations()->pending()->latest()->get())) : [],
            'timezones' => DateTimeZone::listIdentifiers(),
            'can' => [
                'manage' => $canManage,
                'transfer' => Gate::allows('transfer', $household),
                'delete' => Gate::allows('delete', $household),
            ],
        ]);
    }

    public function update(HouseholdRequest $request, Household $household): RedirectResponse
    {
        Gate::authorize('update', $household);

        $household->update($request->validated());

        return $this->backWith(__('Household updated.'));
    }

    public function destroy(Request $request, Household $household, DeleteHousehold $delete): RedirectResponse
    {
        Gate::authorize('delete', $household);

        $request->validate(['confirm_name' => ['required', 'string', Rule::in([$household->name])]]);

        $delete->handle($household, $this->actor($request));

        return to_route('households.index');
    }

    public function leave(Request $request, Household $household, ManageHouseholdMembers $members): RedirectResponse
    {
        Gate::authorize('view', $household);

        $actor = $this->actor($request);
        $members->remove($household, $actor, $actor);

        return to_route('households.index');
    }

    public function transfer(Request $request, Household $household, ManageHouseholdMembers $members): RedirectResponse
    {
        Gate::authorize('transfer', $household);

        $validated = $request->validate(['user' => ['required', 'uuid']]);
        $newOwner = $household->members()->where('users.uuid', $validated['user'])->firstOrFail();

        $members->transferOwnership($household, $this->actor($request), $newOwner);

        return $this->backWith(__('Ownership transferred.'));
    }

    public function updateMember(MemberRoleRequest $request, Household $household, string $member, ManageHouseholdMembers $members): RedirectResponse
    {
        Gate::authorize('manageMembers', $household);

        $target = $household->members()->where('users.uuid', $member)->firstOrFail();
        $members->changeRole($household, $this->actor($request), $target, $request->enum('role', HouseholdRole::class) ?? HouseholdRole::Member);

        return $this->backWith(__('Role updated.'));
    }

    public function removeMember(Request $request, Household $household, string $member, ManageHouseholdMembers $members): RedirectResponse
    {
        Gate::authorize('manageMembers', $household);

        $target = $household->members()->where('users.uuid', $member)->firstOrFail();
        $members->remove($household, $this->actor($request), $target);

        return $this->backWith(__('Member removed.'));
    }

    public function invite(InviteMemberRequest $request, Household $household, InviteHouseholdMember $invite): RedirectResponse
    {
        Gate::authorize('invite', $household);

        $invite->handle($household, $this->actor($request), $request->string('email')->toString(), $request->role());

        return $this->backWith(__('Invitation sent.'));
    }

    public function revokeInvitation(Request $request, Household $household, HouseholdInvitation $invitation, InviteHouseholdMember $invite): RedirectResponse
    {
        Gate::authorize('invite', $household);
        abort_unless($invitation->household_id === $household->id, 404);

        $invite->revoke($invitation, $this->actor($request));

        return $this->backWith(__('Invitation revoked.'));
    }
}

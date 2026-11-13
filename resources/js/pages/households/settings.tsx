import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { useLiveReload } from '@/hooks/use-live-reload';
import { TimezoneSelect } from '@/components/timezone-select';
import { IconButton } from '@/components/icon-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import households from '@/routes/households';
import organize from '@/routes/organize';
import type { Household, HouseholdInvitation, Member } from '@/types';

const HOUSEHOLD_EVENTS = ['InvitationAccepted'];

function DeleteHousehold({ household }: { household: Household }) {
    const form = useForm({ confirm_name: '' });

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button variant="destructive">Delete household</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {household.name}?</DialogTitle>
                    <DialogDescription>
                        Every member loses access to its lists and purchase
                        history. Type the household name to confirm.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.delete(households.destroy.url(household.uuid));
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="confirm-name">Household name</Label>
                        <Input
                            id="confirm-name"
                            autoComplete="off"
                            value={form.data.confirm_name}
                            onChange={(event) =>
                                form.setData('confirm_name', event.target.value)
                            }
                        />
                        <InputError message={form.errors.confirm_name} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={
                                form.processing ||
                                form.data.confirm_name !== household.name
                            }
                        >
                            Delete household
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function HouseholdSettings({
    household,
    members,
    invitations,
    timezones,
    can,
}: {
    household: Household;
    members: Member[];
    invitations: HouseholdInvitation[];
    timezones: string[];
    can: { manage: boolean; transfer: boolean; delete: boolean };
}) {
    const { auth } = usePage().props;
    useLiveReload(`household.${household.uuid}`, HOUSEHOLD_EVENTS, [
        'members',
        'invitations',
        'household',
    ]);
    const rename = useForm({
        name: household.name,
        timezone: household.timezone,
    });
    const invite = useForm({ email: '', role: 'member' });
    const [newOwner, setNewOwner] = useState('');
    const options = { preserveScroll: true, preserveState: true };
    const others = members.filter((member) => member.uuid !== auth.user.uuid);

    return (
        <>
            <Head title={household.name} />
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-8 p-4">
                <header>
                    <h1 className="text-xl font-semibold break-words">
                        {household.name}
                    </h1>
                    <p className="text-sm text-muted-foreground capitalize">
                        Your role: {household.role}
                        {' · '}
                        <Link
                            href={organize.index({
                                query: { household: household.uuid },
                            })}
                            className="text-primary normal-case underline underline-offset-4"
                        >
                            Stores and categories
                        </Link>
                    </p>
                </header>

                {can.manage && (
                    <section aria-labelledby="details-heading">
                        <h2 id="details-heading" className="mb-2 font-medium">
                            Details
                        </h2>
                        <form
                            className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                rename.patch(
                                    households.update.url(household.uuid),
                                    options,
                                );
                            }}
                        >
                            <Input
                                aria-label="Household name"
                                required
                                value={rename.data.name}
                                onChange={(event) =>
                                    rename.setData('name', event.target.value)
                                }
                            />
                            <TimezoneSelect
                                label="Household time zone"
                                timezones={timezones}
                                value={rename.data.timezone}
                                onValueChange={(timezone) =>
                                    rename.setData('timezone', timezone)
                                }
                            />
                            <Button type="submit" disabled={rename.processing}>
                                Save
                            </Button>
                        </form>
                        <InputError
                            message={rename.errors.name}
                            className="mt-1"
                        />
                    </section>
                )}

                <section aria-labelledby="members-heading">
                    <h2 id="members-heading" className="mb-2 font-medium">
                        Members
                    </h2>
                    <ul className="divide-y rounded-lg border">
                        {members.map((member) => (
                            <li
                                key={member.uuid}
                                className="flex flex-wrap items-center gap-2 p-3"
                            >
                                <span className="min-w-40 flex-1 break-words">
                                    {member.name}
                                    {member.uuid === auth.user.uuid && ' (you)'}
                                    <span className="block text-sm break-all text-muted-foreground">
                                        {member.email}
                                    </span>
                                </span>
                                {can.manage && member.role !== 'owner' ? (
                                    <>
                                        <Select
                                            value={member.role}
                                            onValueChange={(role) =>
                                                router.patch(
                                                    households.members.update.url(
                                                        {
                                                            household:
                                                                household.uuid,
                                                            member: member.uuid,
                                                        },
                                                    ),
                                                    { role },
                                                    options,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                className="w-28"
                                                aria-label={`Role for ${member.name}`}
                                            >
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="admin">
                                                    Admin
                                                </SelectItem>
                                                <SelectItem value="member">
                                                    Member
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <ConfirmDialog
                                            trigger={
                                                <IconButton
                                                    label={`Remove ${member.name}`}
                                                    variant="ghost"
                                                >
                                                    <Trash2 />
                                                </IconButton>
                                            }
                                            title={`Remove ${member.name}?`}
                                            description="They lose access right away. Purchases they made stay with the household."
                                            confirmLabel="Remove member"
                                            destructive
                                            onConfirm={() =>
                                                router.delete(
                                                    households.members.destroy.url(
                                                        {
                                                            household:
                                                                household.uuid,
                                                            member: member.uuid,
                                                        },
                                                    ),
                                                    options,
                                                )
                                            }
                                        />
                                    </>
                                ) : (
                                    <span className="text-sm text-muted-foreground capitalize">
                                        {member.role}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>

                {can.manage && (
                    <section aria-labelledby="invite-heading">
                        <h2 id="invite-heading" className="mb-2 font-medium">
                            Invite someone
                        </h2>
                        <form
                            className="grid gap-2 sm:grid-cols-[1fr_auto_auto]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                invite.post(
                                    households.invitations.store.url(
                                        household.uuid,
                                    ),
                                    {
                                        ...options,
                                        onSuccess: () => invite.reset('email'),
                                    },
                                );
                            }}
                        >
                            <Input
                                type="email"
                                required
                                aria-label="Email address"
                                placeholder="name@example.com"
                                value={invite.data.email}
                                onChange={(event) =>
                                    invite.setData('email', event.target.value)
                                }
                            />
                            <Select
                                value={invite.data.role}
                                onValueChange={(role) =>
                                    invite.setData('role', role)
                                }
                            >
                                <SelectTrigger aria-label="Role">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="member">
                                        Member
                                    </SelectItem>
                                    <SelectItem value="admin">Admin</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button type="submit" disabled={invite.processing}>
                                Send invitation
                            </Button>
                            <InputError
                                message={invite.errors.email}
                                className="sm:col-span-3"
                            />
                        </form>
                        {invitations.length > 0 && (
                            <ul className="mt-3 divide-y rounded-lg border">
                                {invitations.map((invitation) => (
                                    <li
                                        key={invitation.uuid}
                                        className="flex items-center gap-2 p-3 text-sm"
                                    >
                                        <span className="min-w-0 flex-1 break-all">
                                            {invitation.email}
                                            <span className="block text-muted-foreground capitalize">
                                                {invitation.role} · pending
                                            </span>
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                router.delete(
                                                    households.invitations.destroy.url(
                                                        {
                                                            household:
                                                                household.uuid,
                                                            invitation:
                                                                invitation.uuid,
                                                        },
                                                    ),
                                                    options,
                                                )
                                            }
                                        >
                                            Revoke
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                <section
                    aria-labelledby="leave-heading"
                    className="flex flex-col gap-4 rounded-lg border p-4"
                >
                    <h2 id="leave-heading" className="font-medium">
                        Membership
                    </h2>
                    {can.transfer && others.length > 0 && (
                        <div className="flex flex-col gap-2 sm:flex-row">
                            <Select
                                value={newOwner}
                                onValueChange={setNewOwner}
                            >
                                <SelectTrigger
                                    className="sm:flex-1"
                                    aria-label="New owner"
                                >
                                    <SelectValue placeholder="Choose a new owner" />
                                </SelectTrigger>
                                <SelectContent>
                                    {others.map((member) => (
                                        <SelectItem
                                            key={member.uuid}
                                            value={member.uuid}
                                        >
                                            {member.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <ConfirmDialog
                                trigger={
                                    <Button
                                        variant="outline"
                                        disabled={!newOwner}
                                    >
                                        Transfer ownership
                                    </Button>
                                }
                                title="Transfer ownership?"
                                description="You become an admin. Only the new owner can transfer ownership or delete this household."
                                confirmLabel="Transfer ownership"
                                onConfirm={() =>
                                    router.post(
                                        households.transfer.url(household.uuid),
                                        { user: newOwner },
                                        options,
                                    )
                                }
                            />
                        </div>
                    )}
                    <div className="flex flex-wrap gap-2">
                        {household.role !== 'owner' && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline">
                                        Leave household
                                    </Button>
                                }
                                title="Leave this household?"
                                description="You lose access to its lists and history. Purchases you made stay with the household."
                                confirmLabel="Leave household"
                                destructive
                                onConfirm={() =>
                                    router.post(
                                        households.leave.url(household.uuid),
                                    )
                                }
                            />
                        )}
                        {can.delete && (
                            <DeleteHousehold household={household} />
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}

HouseholdSettings.layout = {
    breadcrumbs: [{ title: 'Households', href: households.index() }],
};

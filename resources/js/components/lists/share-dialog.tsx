import { router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { IconButton } from '@/components/icon-button';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import lists from '@/routes/lists';
import type { ListShare, ListShareInvitation } from '@/types';

const roles = [
    { value: 'editor', label: 'Can edit and shop' },
    { value: 'viewer', label: 'Can view' },
];

export function ShareDialog({
    listUuid,
    shares,
    invitations,
    trigger,
}: {
    listUuid: string;
    shares: ListShare[];
    invitations: ListShareInvitation[];
    trigger: ReactNode;
}) {
    const form = useForm({ email: '', role: 'editor' });
    const options = { preserveScroll: true, preserveState: true };

    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Share this list</DialogTitle>
                    <DialogDescription>
                        Guests can open only this list. They do not join your
                        household or see its purchase history.
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="grid gap-2 sm:grid-cols-[1fr_auto_auto]"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(lists.shares.store.url(listUuid), {
                            ...options,
                            onSuccess: () => form.reset('email'),
                        });
                    }}
                >
                    <Input
                        type="email"
                        required
                        placeholder="name@example.com"
                        aria-label="Email address"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                    />
                    <Select
                        value={form.data.role}
                        onValueChange={(value) => form.setData('role', value)}
                    >
                        <SelectTrigger aria-label="Access">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((role) => (
                                <SelectItem key={role.value} value={role.value}>
                                    {role.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button type="submit" disabled={form.processing}>
                        Invite
                    </Button>
                    <InputError
                        message={form.errors.email}
                        className="sm:col-span-3"
                    />
                </form>
                {shares.length + invitations.length > 0 && (
                    <ul className="divide-y text-sm">
                        {shares.map((share) => (
                            <li
                                key={share.user}
                                className="flex items-center gap-2 py-2"
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {share.name}
                                    <span className="block truncate text-muted-foreground">
                                        {share.email}
                                    </span>
                                </span>
                                <Select
                                    value={share.role}
                                    onValueChange={(role) =>
                                        router.patch(
                                            lists.shares.update.url({
                                                list: listUuid,
                                                guest: share.user,
                                            }),
                                            { role },
                                            options,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-40"
                                        aria-label={`Access for ${share.name}`}
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {roles.map((role) => (
                                            <SelectItem
                                                key={role.value}
                                                value={role.value}
                                            >
                                                {role.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <IconButton
                                    label={`Remove ${share.name}`}
                                    variant="ghost"

                                    onClick={() =>
                                        router.delete(
                                            lists.shares.destroy.url({
                                                list: listUuid,
                                                guest: share.user,
                                            }),
                                            options,
                                        )
                                    }
                                >
                                    <Trash2 />
                                </IconButton>
                            </li>
                        ))}
                        {invitations.map((invitation) => (
                            <li
                                key={invitation.uuid}
                                className="flex items-center gap-2 py-2"
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {invitation.email}
                                    <span className="block text-muted-foreground">
                                        Invitation pending
                                    </span>
                                </span>
                                <IconButton
                                    label={`Revoke invitation for ${invitation.email}`}
                                    variant="ghost"

                                    onClick={() =>
                                        router.delete(
                                            lists.shareInvitations.destroy.url({
                                                list: listUuid,
                                                invitation: invitation.uuid,
                                            }),
                                            options,
                                        )
                                    }
                                >
                                    <Trash2 />
                                </IconButton>
                            </li>
                        ))}
                    </ul>
                )}
            </DialogContent>
        </Dialog>
    );
}

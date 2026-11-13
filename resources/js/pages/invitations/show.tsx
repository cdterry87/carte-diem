import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import invitations from '@/routes/invitations';

type Invitation = {
    type: 'household' | 'list';
    uuid: string;
    name: string;
    inviter: string | null;
    role: string;
} | null;

export default function InvitationShow({
    invitation,
}: {
    invitation: Invitation;
}) {
    return (
        <>
            <Head title="Invitation" />
            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pt-10 text-center">
                {invitation === null ? (
                    <>
                        <h1 className="text-xl font-semibold">
                            This invitation is not available
                        </h1>
                        <p className="text-muted-foreground">
                            It may have expired or been revoked, or it may have
                            been sent to a different email address.
                        </p>
                        <Button asChild variant="outline">
                            <Link href={dashboard()}>Go to dashboard</Link>
                        </Button>
                    </>
                ) : (
                    <>
                        <h1 className="text-xl font-semibold break-words">
                            {invitation.type === 'household'
                                ? `Join ${invitation.name}?`
                                : `Open the list ${invitation.name}?`}
                        </h1>
                        <p className="text-muted-foreground">
                            {invitation.type === 'household'
                                ? 'Members can see the household lists, purchase history, and spending.'
                                : invitation.role === 'editor'
                                  ? 'You can view, edit, and shop from this list only.'
                                  : 'You can view this list only.'}
                        </p>
                        <div className="flex justify-center gap-2">
                            <Button
                                onClick={() =>
                                    router.post(
                                        (invitation.type === 'household'
                                            ? invitations.household
                                            : invitations.list
                                        ).accept.url(invitation.uuid),
                                    )
                                }
                            >
                                Accept
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        (invitation.type === 'household'
                                            ? invitations.household
                                            : invitations.list
                                        ).decline.url(invitation.uuid),
                                    )
                                }
                            >
                                Decline
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

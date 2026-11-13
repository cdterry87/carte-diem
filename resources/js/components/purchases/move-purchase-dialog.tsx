import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AlertError from '@/components/alert-error';
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
import purchases from '@/routes/purchases';
import type { Purchase } from '@/types';

export function MovePurchaseDialog({ purchase }: { purchase: Purchase }) {
    const { workspaces } = usePage().props;
    const others = workspaces.filter(
        (workspace) => workspace.uuid !== purchase.owner?.uuid,
    );
    const [open, setOpen] = useState(false);
    const [target, setTarget] = useState(others[0]?.uuid ?? '');
    const form = useForm({});
    const errors = form.errors as Record<string, string | undefined>;

    if (others.length === 0) {
        return null;
    }

    const move = (allowDuplicate: boolean) => {
        const workspace = others.find((other) => other.uuid === target);

        form.transform(() => ({
            owner: { type: workspace?.type, uuid: workspace?.uuid },
            revision: purchase.revision,
            allow_duplicate: allowDuplicate,
        }));
        form.post(purchases.move.url(purchase.uuid), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    Move
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Move this purchase?</DialogTitle>
                    <DialogDescription>
                        It leaves {purchase.owner?.name}, so people there lose
                        access, and everyone in the new space can see them.
                    </DialogDescription>
                </DialogHeader>
                <label className="grid gap-2 text-sm font-medium">
                    Move to
                    <select
                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm font-normal shadow-xs"
                        value={target}
                        onChange={(event) => setTarget(event.target.value)}
                    >
                        {others.map((workspace) => (
                            <option key={workspace.uuid} value={workspace.uuid}>
                                {workspace.name}
                            </option>
                        ))}
                    </select>
                </label>
                {errors.allow_duplicate && (
                    <AlertError
                        title="This may be a duplicate"
                        errors={[errors.allow_duplicate]}
                    />
                )}
                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Keep as is
                    </Button>
                    <Button
                        disabled={form.processing}
                        onClick={() => move(Boolean(errors.allow_duplicate))}
                    >
                        {errors.allow_duplicate
                            ? 'Move anyway'
                            : 'Move purchase'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

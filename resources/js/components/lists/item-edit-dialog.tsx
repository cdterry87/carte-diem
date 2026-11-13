import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
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
import { formatQuantity } from '@/lib/format';
import type { Category, ListItem } from '@/types';

const NO_CATEGORY = 'none';

type EditableItem = Pick<
    ListItem,
    'uuid' | 'name' | 'quantity' | 'unit' | 'notes' | 'category'
> & { revision?: number };

export function ItemEditDialog({
    updateUrl,
    item,
    categories,
    trigger,
}: {
    updateUrl: string;
    item: EditableItem;
    categories: Category[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        name: item.name,
        quantity: formatQuantity(item.quantity),
        unit: item.unit ?? '',
        category: item.category?.uuid ?? NO_CATEGORY,
        notes: item.notes ?? '',
    });

    const submit = () => {
        form.transform((data) => ({
            name: data.name,
            quantity: data.quantity === '' ? null : data.quantity,
            unit: data.unit === '' ? null : data.unit,
            category: data.category === NO_CATEGORY ? null : data.category,
            notes: data.notes === '' ? null : data.notes,
            revision: item.revision,
        }));
        form.patch(updateUrl, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    form.setData({
                        name: item.name,
                        quantity: formatQuantity(item.quantity),
                        unit: item.unit ?? '',
                        category: item.category?.uuid ?? NO_CATEGORY,
                        notes: item.notes ?? '',
                    });
                    form.clearErrors();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit item</DialogTitle>
                </DialogHeader>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor={`name-${item.uuid}`}>Name</Label>
                        <Input
                            id={`name-${item.uuid}`}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor={`quantity-${item.uuid}`}>
                                Quantity
                            </Label>
                            <Input
                                id={`quantity-${item.uuid}`}
                                inputMode="decimal"
                                value={form.data.quantity}
                                onChange={(event) =>
                                    form.setData('quantity', event.target.value)
                                }
                            />
                            <InputError message={form.errors.quantity} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`unit-${item.uuid}`}>Unit</Label>
                            <Input
                                id={`unit-${item.uuid}`}
                                value={form.data.unit}
                                onChange={(event) =>
                                    form.setData('unit', event.target.value)
                                }
                            />
                            <InputError message={form.errors.unit} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label>Category</Label>
                        <Select
                            value={form.data.category}
                            onValueChange={(value) =>
                                form.setData('category', value)
                            }
                        >
                            <SelectTrigger aria-label="Category">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_CATEGORY}>
                                    No category
                                </SelectItem>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.uuid}
                                        value={category.uuid}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.category} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor={`notes-${item.uuid}`}>Notes</Label>
                        <Input
                            id={`notes-${item.uuid}`}
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

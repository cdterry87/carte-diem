import { usePage } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ALL = 'all';

export function ScopeFilter({
    value,
    onChange,
}: {
    value: string | null;
    onChange: (scope: string | null) => void;
}) {
    const { workspaces } = usePage().props;

    return (
        <Select
            value={value ?? ALL}
            onValueChange={(next) => onChange(next === ALL ? null : next)}
        >
            <SelectTrigger
                className="w-full sm:w-48"
                aria-label="Filter by owner"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>All</SelectItem>
                <SelectItem value="personal">Personal</SelectItem>
                <SelectItem value="shared">Shared with me</SelectItem>
                {workspaces
                    .filter((workspace) => workspace.type === 'household')
                    .map((workspace) => (
                        <SelectItem key={workspace.uuid} value={workspace.uuid}>
                            {workspace.name}
                        </SelectItem>
                    ))}
            </SelectContent>
        </Select>
    );
}

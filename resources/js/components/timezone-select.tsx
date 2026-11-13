import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export function TimezoneSelect({
    id,
    name,
    label,
    timezones,
    value,
    defaultValue,
    onValueChange,
}: {
    id?: string;
    name?: string;
    label: string;
    timezones: string[];
    value?: string;
    defaultValue?: string;
    onValueChange?: (value: string) => void;
}) {
    return (
        <Select
            name={name}
            value={value}
            defaultValue={defaultValue}
            onValueChange={onValueChange}
        >
            <SelectTrigger id={id} aria-label={label} className="w-full">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {[
                    ...new Set([value ?? defaultValue ?? 'UTC', ...timezones]),
                ].map((timezone) => (
                    <SelectItem key={timezone} value={timezone}>
                        {timezone.replaceAll('_', ' ')}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

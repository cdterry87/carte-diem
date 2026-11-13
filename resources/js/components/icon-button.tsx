import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * An icon-only button whose label is both its accessible name and a hover tooltip. Props passed
 * by a wrapping `asChild` trigger, such as a dialog's, reach the button underneath.
 */
export function IconButton({
    label,
    variant = 'outline',
    ...props
}: Omit<ComponentProps<typeof Button>, 'size' | 'aria-label'> & {
    label: string;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    variant={variant}
                    size="icon"
                    aria-label={label}
                    {...props}
                />
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

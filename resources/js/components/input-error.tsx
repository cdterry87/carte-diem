import type { HTMLAttributes } from 'react';
import { useId, useLayoutEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

const CONTROLS =
    'input:not([type="hidden"]), select, textarea, [role="combobox"]';

/**
 * The field an error belongs to: the last control before it in the same container.
 */
function controlFor(error: HTMLElement): HTMLElement | null {
    const controls = Array.from(
        error.parentElement?.querySelectorAll<HTMLElement>(CONTROLS) ?? [],
    ).filter(
        (control) =>
            control.compareDocumentPosition(error) &
            Node.DOCUMENT_POSITION_FOLLOWING,
    );

    return controls.at(-1) ?? null;
}

/**
 * A field's validation message. It marks the field it follows as invalid and adds itself
 * to the field's description, so screen readers say which field failed and why.
 */
export default function InputError({
    message,
    className = '',
    id,
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    const generatedId = useId();
    const errorId = id ?? `${generatedId}-error`;
    const ref = useRef<HTMLParagraphElement>(null);

    useLayoutEffect(() => {
        const control = ref.current ? controlFor(ref.current) : null;

        if (!control || !message) {
            return;
        }

        const describedBy = (control.getAttribute('aria-describedby') ?? '')
            .split(' ')
            .filter(Boolean);
        control.setAttribute(
            'aria-describedby',
            [...describedBy, errorId].join(' '),
        );
        control.setAttribute('aria-invalid', 'true');

        return () => {
            const remaining = (control.getAttribute('aria-describedby') ?? '')
                .split(' ')
                .filter((token) => token && token !== errorId);

            if (remaining.length > 0) {
                control.setAttribute('aria-describedby', remaining.join(' '));
            } else {
                control.removeAttribute('aria-describedby');
            }

            control.removeAttribute('aria-invalid');
        };
    }, [message, errorId]);

    return message ? (
        <p
            {...props}
            id={errorId}
            ref={ref}
            className={cn('text-sm text-red-600 dark:text-red-400', className)}
        >
            {message}
        </p>
    ) : null;
}

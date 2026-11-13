import { router } from '@inertiajs/react';
import { Plus, Star } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Suggestion } from '@/types';

export function ItemQuickAdd({
    addUrl,
    suggestionsUrl,
}: {
    addUrl: string;
    suggestionsUrl: string;
}) {
    const listboxId = useId();
    const inputRef = useRef<HTMLInputElement>(null);
    const submitting = useRef(false);
    const [name, setName] = useState('');
    const [error, setError] = useState<string>();
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(-1);
    const [suggestions, setSuggestions] = useState<Suggestion[]>([]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(`${suggestionsUrl}?q=${encodeURIComponent(name.trim())}`, {
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((body: { data: Suggestion[] } | null) => {
                    setSuggestions(body?.data ?? []);
                    setActive(-1);
                })
                .catch(() => undefined);
        }, 120);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [name, open, suggestionsUrl]);

    const add = (value: string) => {
        const trimmed = value.trim();

        if (!trimmed || submitting.current) {
            return;
        }

        submitting.current = true;

        router.post(
            addUrl,
            { name: trimmed },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setName('');
                    setError(undefined);
                    setOpen(false);
                },
                onError: (errors) => setError(errors.name),
                onFinish: () => {
                    submitting.current = false;
                    inputRef.current?.focus();
                },
            },
        );
    };

    const visible = open && suggestions.length > 0;

    return (
        <div className="relative">
            <form
                className="flex gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    add(active >= 0 ? suggestions[active].name : name);
                }}
            >
                <Input
                    ref={inputRef}
                    value={name}
                    onChange={(event) => {
                        setName(event.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    onBlur={() => setOpen(false)}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape') {
                            setOpen(false);
                        } else if (event.key === 'ArrowDown' && visible) {
                            event.preventDefault();
                            setActive((active + 1) % suggestions.length);
                        } else if (event.key === 'ArrowUp' && visible) {
                            event.preventDefault();
                            setActive(
                                (active - 1 + suggestions.length) %
                                    suggestions.length,
                            );
                        }
                    }}
                    placeholder="Add an item, like 2 lb apples"
                    aria-label="Add an item"
                    role="combobox"
                    aria-expanded={visible}
                    aria-controls={listboxId}
                    aria-autocomplete="list"
                    aria-activedescendant={
                        active >= 0 ? `${listboxId}-${active}` : undefined
                    }
                    autoComplete="off"
                    enterKeyHint="done"
                    className="h-11"
                />
                <Button type="submit" className="h-11" disabled={!name.trim()}>
                    <Plus />
                    Add
                </Button>
            </form>
            <InputError message={error} className="mt-1" />
            {visible && (
                <ul
                    id={listboxId}
                    role="listbox"
                    aria-label="Suggestions"
                    className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                >
                    {suggestions.map((suggestion, index) => (
                        <li
                            key={suggestion.name}
                            id={`${listboxId}-${index}`}
                            role="option"
                            aria-selected={index === active}
                            data-active={index === active}
                            className="flex cursor-pointer items-center justify-between gap-2 rounded-sm px-2 py-2 text-sm hover:bg-accent data-[active=true]:bg-accent"
                            onMouseDown={(event) => {
                                event.preventDefault();
                                add(suggestion.name);
                            }}
                        >
                            <span className="flex min-w-0 items-center gap-1.5">
                                {suggestion.is_favorite && (
                                    <Star
                                        className="size-3 shrink-0 fill-current text-primary"
                                        aria-label="Favorite"
                                    />
                                )}
                                <span className="truncate">
                                    {suggestion.name}
                                </span>
                            </span>
                            {(suggestion.reason ?? suggestion.category) && (
                                <span className="min-w-0 truncate text-right text-xs text-muted-foreground">
                                    {suggestion.reason ?? suggestion.category}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

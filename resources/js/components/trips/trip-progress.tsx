export function TripProgress({ done, total }: { done: number; total: number }) {
    const percent = total === 0 ? 0 : Math.round((done / total) * 100);

    return (
        <div>
            <p className="mb-1 text-sm text-muted-foreground">
                {done} of {total} in the cart
            </p>
            <div
                role="progressbar"
                aria-label="Trip progress"
                aria-valuenow={done}
                aria-valuemin={0}
                aria-valuemax={total}
                className="h-2 overflow-hidden rounded-full bg-secondary"
            >
                <div
                    className="h-full bg-primary transition-all"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

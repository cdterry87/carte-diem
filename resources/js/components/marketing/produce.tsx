import type { SVGAttributes } from 'react';

type Props = SVGAttributes<SVGElement>;

export function Apple(props: Props) {
    return (
        <svg viewBox="0 0 64 64" aria-hidden="true" {...props}>
            <path
                fill="#d9503a"
                d="M32 20c-5-5-13-5-18 1-6 7-4 20 2 29 4 6 9 9 13 7 2-1 4-1 6 0 4 2 9-1 13-7 6-9 8-22 2-29-5-6-13-6-18-1Z"
            />
            <path
                fill="#f07e68"
                d="M17 26c3-3 7-4 10-3-4 2-7 6-8 12-2-2-3-6-2-9Z"
            />
            <path
                fill="none"
                stroke="#6b4a2f"
                strokeLinecap="round"
                strokeWidth="3"
                d="M32 21c0-5 1-9 4-12"
            />
            <path fill="#3f8a5f" d="M35 15c2-7 9-10 16-8-1 7-8 11-16 8Z" />
        </svg>
    );
}

export function Avocado(props: Props) {
    return (
        <svg viewBox="0 0 64 64" aria-hidden="true" {...props}>
            <path
                fill="#3d6b3f"
                d="M32 4c-8 0-12 9-15 18-3 8-7 13-7 21 0 11 10 18 22 18s22-7 22-18c0-8-4-13-7-21C44 13 40 4 32 4Z"
            />
            <path
                fill="#d9e28f"
                d="M32 10c-6 0-9 8-12 15-3 7-6 12-6 18 0 8 8 13 18 13s18-5 18-13c0-6-3-11-6-18-3-7-6-15-12-15Z"
            />
            <circle cx="32" cy="41" r="10" fill="#8a5a33" />
            <circle cx="29" cy="38" r="3" fill="#a97648" />
        </svg>
    );
}

export function Carrot(props: Props) {
    return (
        <svg viewBox="0 0 64 64" aria-hidden="true" {...props}>
            <path
                fill="#3f8a5f"
                d="M41 21c-2-6 0-13 5-17 2 5 2 10 0 13 4-3 10-4 15-1-3 5-9 7-14 6 3 2 5 6 4 10-5-1-8-5-10-11Z"
            />
            <path
                fill="#e8853a"
                d="M42 18c5 2 7 8 5 12C41 42 21 58 8 60c-3 0-5-2-4-5C7 42 24 23 36 17c2-1 4-1 6 1Z"
            />
            <path
                fill="none"
                stroke="#c9692a"
                strokeLinecap="round"
                strokeWidth="2.5"
                d="M26 27l6 5M18 37l5 5M12 47l4 3"
            />
        </svg>
    );
}

export function MilkCarton(props: Props) {
    return (
        <svg viewBox="0 0 64 64" aria-hidden="true" {...props}>
            <path fill="#e6e1d4" d="M22 5h20v8H22z" />
            <path fill="#ffffff" d="M22 13h20l8 13v33H14V26z" />
            <path fill="#dbe7f2" d="M14 26h36v33H14z" />
            <path fill="#ffffff" d="M14 26h36v9H14z" />
            <path fill="#2f6f9f" d="M21 40h22v12H21z" />
            <path
                fill="none"
                stroke="#c9c2b1"
                strokeWidth="2"
                d="M22 13l10 13 10-13"
            />
        </svg>
    );
}

export function Bread(props: Props) {
    return (
        <svg viewBox="0 0 64 64" aria-hidden="true" {...props}>
            <path
                fill="#c98b4a"
                d="M16 14c-7 0-12 5-12 11 0 4 2 7 5 9v18c0 3 2 5 5 5h36c3 0 5-2 5-5V34c3-2 5-5 5-9 0-6-5-11-12-11H16Z"
            />
            <path
                fill="#f1d7a8"
                d="M17 19c-5 0-8 3-8 6 0 3 2 5 5 6v19c0 1 1 2 2 2h32c1 0 2-1 2-2V31c3-1 5-3 5-6 0-3-3-6-8-6H17Z"
            />
        </svg>
    );
}

export function Lemon(props: Props) {
    return (
        <svg viewBox="0 0 64 64" aria-hidden="true" {...props}>
            <path
                fill="#f2c53d"
                d="M50 13c-9-6-24-5-34 5S5 41 11 49c1 2 1 4 3 5 8 6 22 4 32-6s12-24 7-32c-1-2-1-3-3-3Z"
            />
            <path
                fill="#f8dd7c"
                d="M20 22c4-4 10-7 15-7-7 3-13 9-17 17-2-3 0-7 2-10Z"
            />
        </svg>
    );
}

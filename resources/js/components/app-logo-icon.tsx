import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M9.6 2.5a1 1 0 0 1 .4 1.36L7.7 8h8.6l-2.3-4.14a1 1 0 1 1 1.75-.97L18.6 8H21a1 1 0 0 1 .97 1.24l-2.2 9.48A3 3 0 0 1 16.85 21H7.15a3 3 0 0 1-2.92-2.28L2.03 9.24A1 1 0 0 1 3 8h2.4l2.85-5.11A1 1 0 0 1 9.6 2.5Z" />
        </svg>
    );
}

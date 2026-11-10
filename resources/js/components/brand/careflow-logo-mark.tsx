import { cn } from '@/lib/utils';

export function CareflowLogoMark({
    className,
}: {
    className?: string;
}) {
    return (
        <svg
            viewBox="0 0 48 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className={cn('size-full', className)}
            aria-hidden
        >
            <rect width="48" height="48" rx="14" fill="url(#cf-mark-bg)" />
            <path
                d="M24 11c.8 0 1.5.7 1.5 1.5V21H34c.8 0 1.5.7 1.5 1.5v3c0 .8-.7 1.5-1.5 1.5h-8.5v8.5c0 .8-.7 1.5-1.5 1.5h-3c-.8 0-1.5-.7-1.5-1.5V27H14c-.8 0-1.5-.7-1.5-1.5v-3c0-.8.7-1.5 1.5-1.5h8.5v-8.5c0-.8.7-1.5 1.5-1.5h3Z"
                fill="white"
            />
            <circle cx="36.5" cy="12.5" r="3.5" fill="#7DD3FC" />
            <defs>
                <linearGradient
                    id="cf-mark-bg"
                    x1="8"
                    y1="4"
                    x2="44"
                    y2="46"
                    gradientUnits="userSpaceOnUse"
                >
                    <stop stopColor="#4F8CFF" />
                    <stop offset="1" stopColor="#2563EB" />
                </linearGradient>
            </defs>
        </svg>
    );
}

import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

const APP_TIME_ZONE = 'Asia/Manila';

function getAppDateParts(value: string): Record<string, string> | null {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone: APP_TIME_ZONE,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        })
            .formatToParts(date)
            .filter((part) => part.type !== 'literal')
            .map((part) => [part.type, part.value]),
    );
}

export function formatAppDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    const parts = getAppDateParts(value);

    if (!parts) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        timeZone: APP_TIME_ZONE,
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

export function toAppDateInput(value: string | null): string {
    if (!value) {
        return '';
    }

    const parts = getAppDateParts(value);

    return parts ? `${parts.year}-${parts.month}-${parts.day}` : value.slice(0, 10);
}

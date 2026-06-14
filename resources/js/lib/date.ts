const ISO_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

export function parseIsoDate(value: string | null | undefined): Date | null {
    if (!value) return null;

    const match = ISO_DATE_PATTERN.exec(value);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const date = new Date(year, month - 1, day);

    if (
        date.getFullYear() !== year ||
        date.getMonth() !== month - 1 ||
        date.getDate() !== day
    ) {
        return null;
    }

    return date;
}

/**
 * Format a single ISO date. Pass the user's locale to render in their
 * convention (e.g. `cs` -> `1. 6. 2026`, `en` -> `6/1/2026`,
 * `de` -> `1.6.2026`). Falls back to the d.m.Y convention when the
 * locale is unknown.
 */
export function formatDate(
    value: string | null | undefined,
    locale: string = 'en',
    fallback = '—',
): string {
    const date = parseIsoDate(value);
    if (!date) return fallback;

    try {
        return new Intl.DateTimeFormat(locale, {
            day: 'numeric',
            month: 'numeric',
            year: 'numeric',
        }).format(date);
    } catch {
        return `${date.getDate()}.${date.getMonth() + 1}.${date.getFullYear()}`;
    }
}

/**
 * Format a start/end date range. Returns the fallback when either
 * end is invalid; otherwise uses `Intl.DateTimeFormat` to produce
 * locale-conventional output (e.g. `1. 6. 2026 – 30. 6. 2026`).
 */
export function formatDateRange(
    start: string | null | undefined,
    end: string | null | undefined,
    locale: string = 'en',
    fallback = '—',
): string {
    const startDate = parseIsoDate(start);
    const endDate = parseIsoDate(end);

    if (!startDate || !endDate) {
        return fallback;
    }

    try {
        const fmt = new Intl.DateTimeFormat(locale, {
            day: 'numeric',
            month: 'numeric',
            year: 'numeric',
        });

        return `${fmt.format(startDate)} - ${fmt.format(endDate)}`;
    } catch {
        const f = (d: Date): string =>
            `${d.getDate()}.${d.getMonth() + 1}.${d.getFullYear()}`;

        return `${f(startDate)} - ${f(endDate)}`;
    }
}

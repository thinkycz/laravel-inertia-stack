import { describe, expect, test } from 'vitest';
import { formatDate, formatDateRange } from '@/lib/date';

describe('date formatting', () => {
    test('returns the fallback for invalid or empty values', () => {
        expect(formatDate('', 'en')).toBe('—');
        expect(formatDate('not-a-date', 'en')).toBe('—');
        expect(formatDate('2026-02-31', 'en')).toBe('—');
    });

    test('formats ISO dates in the user locale (cs -> d. m. y)', () => {
        expect(formatDate('2026-06-01', 'cs')).toBe('1. 6. 2026');
        expect(formatDate('2028-02-29', 'cs')).toBe('29. 2. 2028');
    });

    test('formats ISO dates in the user locale (en -> m/d/y)', () => {
        expect(formatDate('2026-06-01', 'en')).toBe('6/1/2026');
    });

    test('formats date ranges in the user locale', () => {
        expect(formatDateRange('2026-06-01', '2026-06-30', 'cs')).toBe(
            '1. 6. 2026 - 30. 6. 2026',
        );
        expect(formatDateRange('2026-06-01', '2026-06-30', 'en')).toBe(
            '6/1/2026 - 6/30/2026',
        );
    });

    test('returns the fallback when either end of the range is invalid', () => {
        expect(formatDateRange('2026-06-01', '', 'en')).toBe('—');
        expect(formatDateRange('', '2026-06-30', 'en')).toBe('—');
    });
});

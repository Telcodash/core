import type { Dispatch, SetStateAction } from 'react';
import { useCallback, useEffect, useState } from 'react';

function read<T>(key: string, fallback: T): T {
    try {
        const stored = window.localStorage.getItem(key);

        return stored === null ? fallback : (JSON.parse(stored) as T);
    } catch {
        // Storage unavailable (private mode, blocked) or corrupt value.
        return fallback;
    }
}

/**
 * `useState` that survives reloads and layout swaps by persisting to localStorage.
 * Falls back to `initial` when nothing is stored or storage is unavailable, and
 * stays in sync across browser tabs.
 */
export function usePersistentState<T>(
    key: string,
    initial: T,
): [T, Dispatch<SetStateAction<T>>] {
    const [value, setValue] = useState<T>(() => read(key, initial));

    const set: Dispatch<SetStateAction<T>> = useCallback(
        (next) => {
            setValue((previous) => {
                const resolved =
                    typeof next === 'function'
                        ? (next as (previous: T) => T)(previous)
                        : next;

                try {
                    window.localStorage.setItem(key, JSON.stringify(resolved));
                } catch {
                    // Ignore: the in-memory state still updates.
                }

                return resolved;
            });
        },
        [key],
    );

    useEffect(() => {
        const onStorage = (event: StorageEvent) => {
            if (event.key === key) {
                setValue(read(key, initial));
            }
        };

        window.addEventListener('storage', onStorage);

        return () => window.removeEventListener('storage', onStorage);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [key]);

    return [value, set];
}

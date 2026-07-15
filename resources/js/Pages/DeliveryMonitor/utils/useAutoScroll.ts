import { RefObject, useEffect, useRef } from 'react';

interface UseAutoScrollOptions {
    enabled?: boolean;
    pixelsPerTick?: number;
    tickMs?: number;
    resumeDelayMs?: number;
}

/**
 * Slowly and continuously scrolls a container down, looping back to the top
 * once it reaches the bottom — for unattended TV displays where content
 * would otherwise sit outside the visible area. Pauses temporarily on any
 * manual interaction (wheel/touch/pointer) so it doesn't fight a person
 * who walks up and scrolls by hand.
 */
export function useAutoScroll<T extends HTMLElement>(
    ref: RefObject<T | null>,
    { enabled = true, pixelsPerTick = 1, tickMs = 40, resumeDelayMs = 4000 }: UseAutoScrollOptions = {}
) {
    const pausedUntilRef = useRef(0);

    useEffect(() => {
        const el = ref.current;
        if (!el || !enabled) return;

        const pause = () => {
            pausedUntilRef.current = Date.now() + resumeDelayMs;
        };
        el.addEventListener('wheel', pause, { passive: true });
        el.addEventListener('touchstart', pause, { passive: true });
        el.addEventListener('pointerdown', pause);

        const interval = setInterval(() => {
            if (Date.now() < pausedUntilRef.current) return;

            const current = ref.current;
            if (!current) return;

            const maxScroll = current.scrollHeight - current.clientHeight;
            if (maxScroll <= 0) return;

            if (current.scrollTop >= maxScroll - 1) {
                current.scrollTop = 0;
            } else {
                current.scrollTop += pixelsPerTick;
            }
        }, tickMs);

        return () => {
            clearInterval(interval);
            el.removeEventListener('wheel', pause);
            el.removeEventListener('touchstart', pause);
            el.removeEventListener('pointerdown', pause);
        };
    }, [ref, enabled, pixelsPerTick, tickMs, resumeDelayMs]);
}

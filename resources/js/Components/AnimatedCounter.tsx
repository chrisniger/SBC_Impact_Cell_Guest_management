import { useEffect, useState } from 'react';

type Props = {
    /** Final value to land on. Animated from 0 → value over duration ms. */
    value: number;
    /** Animation duration in ms */
    duration?: number;
    /** Decimal places when formatting. Default 0. */
    decimals?: number;
    className?: string;
};

/**
 * Phase 06d.0 — animated number counter.
 *
 *  requestAnimationFrame + easeOutQuad per Implementation/06_Dashboard_Design_System.md § KPI card
 *  ("Animated number counter — card hover").
 *
 *  Respects prefers-reduced-motion (snapshot to final value immediately).
 *
 *  Skips animation when value ≤ 0 or when typeof !== 'number' (defensive).
 */
export default function AnimatedCounter({ value, duration = 800, decimals = 0, className = '' }: Props) {
    const [display, setDisplay] = useState<number>(0);

    useEffect(() => {
        if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0) {
            setDisplay(value ?? 0);
            return;
        }

        // Respect prefers-reduced-motion
        if (typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
            setDisplay(value);
            return;
        }

        let raf = 0;
        const start = performance.now();
        const easeOutQuad = (t: number) => t * (2 - t);

        const tick = (now: number) => {
            const elapsed = now - start;
            const t = Math.min(1, elapsed / duration);
            const eased = easeOutQuad(t);
            setDisplay(value * eased);
            if (t < 1) {
                raf = requestAnimationFrame(tick);
            } else {
                setDisplay(value);
            }
        };

        raf = requestAnimationFrame(tick);
        return () => cancelAnimationFrame(raf);
    }, [value, duration]);

    return (
        <span className={className} data-testid="animated-counter">
            {display.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}
        </span>
    );
}

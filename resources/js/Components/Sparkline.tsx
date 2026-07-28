import { useMemo } from 'react';

type Tone = 'indigo' | 'emerald' | 'amber' | 'rose' | 'blue' | 'default';

type Props = {
    /** Daily values (oldest to newest). Empty / single-point arrays render nothing (NaN safe). */
    series: number[];
    /** Accent color for the line + area gradient */
    tone?: Tone;
    /** Width × Height in px (defaults to 60×24 — sized to fit a KPI card) */
    width?: number;
    height?: number;
    /** Optional extra className for the SVG element */
    className?: string;
};

const TONE_MAP: Record<Tone, { stroke: string; gradient: [string, string] }> = {
    indigo:  { stroke: '#6366F1', gradient: ['rgba(99,102,241,0.30)', 'rgba(99,102,241,0)'] },
    emerald: { stroke: '#10B981', gradient: ['rgba(16,185,129,0.30)', 'rgba(16,185,129,0)'] },
    amber:   { stroke: '#F59E0B', gradient: ['rgba(245,158,11,0.30)', 'rgba(245,158,11,0)'] },
    rose:    { stroke: '#F43F5E', gradient: ['rgba(244,63,94,0.30)', 'rgba(244,63,94,0)'] },
    blue:    { stroke: '#3B82F6', gradient: ['rgba(59,130,246,0.30)', 'rgba(59,130,246,0)'] },
    default: { stroke: '#6B7280', gradient: ['rgba(107,114,128,0.30)', 'rgba(107,114,128,0)'] },
};

/**
 * Phase 06d.0 — inline SVG sparkline.
 *
 * Per Implementation/06_Dashboard_Design_System.md design tokens.
 *
 * Robustness (per thinker-with-files-gemini risk #2):
 *  - series.length < 2: returns null (avoids div-by-zero in path math).
 *  - All values NaN/undefined: returns null.
 *  - Flat series (all equal): draws a horizontal midpoint line.
 *
 * Width is configurable; viewBox is locked at 60×24 so the line scales on resize.
 */
export default function Sparkline({ series, tone = 'indigo', width = 60, height = 24, className = '' }: Props) {
    const { linePath, areaPath } = useMemo(() => {
        // SAFETY: empty or single-point series would divide-by-zero on (i / (len-1)).
        if (!Array.isArray(series) || series.length < 2) {
            return { linePath: null, areaPath: null };
        }

        const safeSeries = series.map((v) => (Number.isFinite(v) ? Number(v) : 0));

        const min = Math.min(...safeSeries);
        const max = Math.max(...safeSeries);
        const range = max - min || 1; // prevent div-by-zero on flat data
        const len = safeSeries.length;
        const xStep = 60 / (len - 1);

        const points = safeSeries.map((value, i) => {
            const x = i * xStep;
            const y = 24 - ((value - min) / range) * 22 - 1; // 1px top/bottom padding
            return [x, y] as const;
        });

        const line = points.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(2)},${y.toFixed(2)}`).join(' ');
        const area = `${line} L60,24 L0,24 Z`;

        return { linePath: line, areaPath: area };
    }, [series]);

    if (linePath === null) return null;

    const { stroke, gradient } = TONE_MAP[tone];
    const gradId = `spark-grad-${tone}`;

    return (
        <svg
            viewBox="0 0 60 24"
            width={width}
            height={height}
            preserveAspectRatio="none"
            className={`overflow-visible ${className}`}
            data-testid="kpi-sparkline"
            aria-label="KPI trend sparkline"
            role="img"
        >
            <defs>
                <linearGradient id={gradId} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={gradient[0]} />
                    <stop offset="100%" stopColor={gradient[1]} />
                </linearGradient>
            </defs>
            <path d={areaPath ?? ''} fill={`url(#${gradId})`} stroke="none" />
            <path d={linePath} fill="none" stroke={stroke} strokeWidth={1.6} strokeLinecap="round" strokeLinejoin="round" />
        </svg>
    );
}

type Series = { label: string; values: number[]; className: string };

const WIDTH = 720;
const HEIGHT = 220;
const PAD_Y = 14;

function smoothPath(points: [number, number][]): string {
    if (points.length === 0) return '';
    if (points.length === 1) return `M${points[0][0]},${points[0][1]}`;

    let d = `M${points[0][0]},${points[0][1]}`;
    for (let i = 0; i < points.length - 1; i++) {
        const [x0, y0] = points[Math.max(0, i - 1)];
        const [x1, y1] = points[i];
        const [x2, y2] = points[i + 1];
        const [x3, y3] = points[Math.min(points.length - 1, i + 2)];
        const c1x = x1 + (x2 - x0) / 6;
        const c1y = y1 + (y2 - y0) / 6;
        const c2x = x2 - (x3 - x1) / 6;
        const c2y = y2 - (y3 - y1) / 6;
        d += ` C${c1x},${c1y} ${c2x},${c2y} ${x2},${y2}`;
    }
    return d;
}

export default function OverviewChart({ labels, series }: { labels: string[]; series: Series[] }) {
    const max = Math.max(1, ...series.flatMap((s) => s.values));
    const step = labels.length > 1 ? WIDTH / (labels.length - 1) : 0;
    const y = (v: number) => HEIGHT - PAD_Y - (v / max) * (HEIGHT - PAD_Y * 2);
    const labelEvery = labels.length > 10 ? Math.ceil(labels.length / 7) : 1;

    return (
        <div className="chart">
            <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} preserveAspectRatio="none" role="img" aria-label={`Daily ${series.map((s) => s.label).join(' and ')}`}>
                {[0.25, 0.5, 0.75].map((f) => (
                    <line key={f} x1={0} x2={WIDTH} y1={HEIGHT * f} y2={HEIGHT * f} className="chart-grid" />
                ))}
                {series.map((s) => {
                    const points = s.values.map((v, i) => [i * step, y(v)] as [number, number]);
                    const line = smoothPath(points);
                    return (
                        <g key={s.label} className={s.className}>
                            <path d={`${line} L${WIDTH},${HEIGHT} L0,${HEIGHT} Z`} className="chart-area" />
                            <path d={line} className="chart-line" />
                            {points.map(([px, py], i) => (
                                <circle key={i} cx={px} cy={py} r={3} className="chart-point">
                                    <title>{`${labels[i]}: ${s.values[i]} ${s.label.toLowerCase()}`}</title>
                                </circle>
                            ))}
                        </g>
                    );
                })}
            </svg>
            <div className="chart-labels">
                {labels.map((label, i) => (
                    <span key={i}>{i % labelEvery === 0 || i === labels.length - 1 ? label : ''}</span>
                ))}
            </div>
        </div>
    );
}

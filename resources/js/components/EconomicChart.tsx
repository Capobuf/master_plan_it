import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    DoughnutController,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
    type ChartType,
} from "chart.js";
import { useEffect, useRef, useState } from "react";
import type { DashboardChart } from "../types";

ChartJS.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
);

export default function EconomicChart({
    data,
    type = "bar",
    label,
}: {
    data?: DashboardChart;
    type?: "bar" | "line" | "doughnut";
    label: string;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [error, setError] = useState(false);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas || !data || data.labels.length === 0) return;

        setError(false);
        let chart: ChartJS | null = null;
        try {
            const datasets = data.datasets.map((dataset, index) => ({
                ...dataset,
                borderColor: dataset.borderColor ?? chartPalette[index % chartPalette.length],
                backgroundColor:
                    dataset.backgroundColor ??
                    (type === "doughnut"
                        ? data.labels.map(
                              (_, labelIndex) =>
                                  chartPalette[labelIndex % chartPalette.length],
                          )
                        : chartFills[index % chartFills.length]),
            }));
            chart = new ChartJS(canvas, {
                type: type as ChartType,
                data: {
                    labels: data.labels,
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: data.datasets.length > 1 || type === "doughnut",
                            position: "bottom",
                            labels: { boxWidth: 10, usePointStyle: true },
                        },
                        tooltip: { intersect: false },
                    },
                    scales:
                        type === "doughnut"
                            ? undefined
                            : {
                                  x: { grid: { display: false } },
                                  y: { beginAtZero: true },
                              },
                },
            });
        } catch {
            setError(true);
        }

        return () => chart?.destroy();
    }, [data, type]);

    if (error) {
        return (
            <div role="alert" className="grid h-72 place-items-center text-sm text-red-700">
                This chart could not be rendered.
            </div>
        );
    }

    if (!data || data.labels.length === 0 || data.datasets.length === 0) {
        return (
            <div className="grid h-72 place-items-center text-center text-sm text-slate-500">
                No values to chart for this planning year.
            </div>
        );
    }

    return (
        <div className="relative h-72">
            <canvas ref={canvasRef} role="img" aria-label={label}>
                {label}
            </canvas>
        </div>
    );
}

const chartPalette = ["#2563eb", "#0f766e", "#d97706", "#7c3aed", "#dc2626"];
const chartFills = ["#dbeafe", "#ccfbf1", "#fef3c7", "#ede9fe", "#fee2e2"];

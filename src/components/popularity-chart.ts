interface ChartData {
    labels: number[];
    datasets: { label: string; data: (number | null)[] }[];
}

const colors = [
    "#08c",
    "#dc3545",
    "#198754",
    "#ffc107",
    "#0dcaf0",
    "#d63384",
    "#ff9800",
    "#434434",
    "#673ab7",
    "#adb5bd",
    "#8bc34a",
    "#3f51b5",
    "#ffeb3b",
    "#ff5722",
    "#795548",
];

function renderYearMonth(yearMonth: number | string): string {
    const s = yearMonth.toString();
    return `${s.substring(0, 4)}-${s.substring(4, 6)}`;
}

class PopularityChart extends HTMLElement {
    connectedCallback() {
        const script = this.querySelector('script[type="application/json"]');
        if (!script?.textContent) return;

        let data: ChartData;
        try {
            data = JSON.parse(script.textContent);
        } catch {
            return;
        }

        if (!data.labels?.length) return;

        const canvas = document.createElement("canvas");
        canvas.width = 1280;
        canvas.height = 720;
        this.appendChild(canvas);

        this.drawChart(canvas, data);
    }

    private async drawChart(canvas: HTMLCanvasElement, data: ChartData) {
        const {
            Chart,
            LineElement,
            PointElement,
            LineController,
            CategoryScale,
            LinearScale,
            Legend,
            Tooltip,
        } = await import("chart.js");

        Chart.register(
            LineElement,
            PointElement,
            LineController,
            CategoryScale,
            LinearScale,
            Legend,
            Tooltip,
        );

        const style = getComputedStyle(document.documentElement);
        const textColor = style.getPropertyValue("--bs-body-color");
        const gridColor = style.getPropertyValue("--bs-border-color");

        new Chart(canvas, {
            type: "line",
            data,
            options: {
                interaction: {
                    mode: "index",
                    intersect: false,
                },
                plugins: {
                    tooltip: {
                        displayColors: false,
                        itemSort: (a, b) =>
                            (b.raw as number) - (a.raw as number),
                        callbacks: {
                            title: (items) =>
                                renderYearMonth(items[0].label),
                        },
                    },
                    legend: {
                        labels: {
                            color: textColor,
                        },
                    },
                },
                normalized: true,
                scales: {
                    x: {
                        ticks: {
                            callback(val) {
                                return renderYearMonth(
                                    this.getLabelForValue(val as number),
                                );
                            },
                            color: textColor,
                            autoSkipPadding: 30,
                        },
                        grid: {
                            display: false,
                            color: gridColor,
                        },
                    },
                    y: {
                        type: "linear",
                        min: 0,
                        grid: {
                            color: gridColor,
                        },
                        ticks: {
                            color: textColor,
                        },
                    },
                },
                elements: {
                    line: {
                        borderColor: colors,
                    },
                    point: {
                        radius: 0,
                        hoverRadius: 4,
                        hoverBackgroundColor: textColor,
                    },
                },
            },
        });
    }
}

customElements.define("popularity-chart", PopularityChart);

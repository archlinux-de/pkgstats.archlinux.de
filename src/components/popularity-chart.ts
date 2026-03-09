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

function getTooltipElement(chart: {
    canvas: HTMLCanvasElement;
}): HTMLDivElement {
    const parent = chart.canvas.parentElement;
    if (!parent) {
        throw new Error("chart-tooltip: canvas has no parent element");
    }

    let el = parent.querySelector(".chart-tooltip") as HTMLDivElement | null;

    if (!el) {
        el = document.createElement("div");
        el.className = "chart-tooltip";
        parent.appendChild(el);
    }

    return el;
}

class PopularityChart extends HTMLElement {
    connectedCallback() {
        const script = this.querySelector('script[type="application/json"]');
        if (!script?.textContent) {
            console.error("popularity-chart: script textContent is missing.");
            return;
        }

        let data: ChartData;
        try {
            data = JSON.parse(script.textContent);
        } catch (error) {
            console.error("popularity-chart: Failed to parse data:", error);
            return;
        }

        if (!data.labels?.length) {
            console.error(
                "popularity-chart: data labels are missing or empty.",
            );
            return;
        }

        const canvas = document.createElement("canvas");
        canvas.width = 1280;
        canvas.height = 720;
        this.appendChild(canvas);

        const style = getComputedStyle(document.documentElement);
        const textColor = style.getPropertyValue("--bs-body-color");
        const gridColor = style.getPropertyValue("--bs-border-color");

        if (data.labels.length < 3) {
            this.drawBarChart(canvas, data, textColor, gridColor);
        } else {
            this.drawLineChart(canvas, data, textColor, gridColor);
        }
    }

    private async drawBarChart(
        canvas: HTMLCanvasElement,
        data: ChartData,
        textColor: string,
        gridColor: string,
    ) {
        const {
            Chart,
            BarElement,
            BarController,
            CategoryScale,
            LinearScale,
            Tooltip,
        } = await import("chart.js");

        Chart.register(
            BarElement,
            BarController,
            CategoryScale,
            LinearScale,
            Tooltip,
        );

        const lastIndex = data.labels.length - 1;
        const barData = {
            labels: data.datasets.map((ds) => ds.label),
            datasets: [
                {
                    label: renderYearMonth(data.labels[lastIndex]),
                    data: data.datasets.map((ds) => ds.data[lastIndex] ?? 0),
                    backgroundColor: colors.slice(0, data.datasets.length),
                },
            ],
        };

        new Chart(canvas, {
            type: "bar",
            data: barData,
            options: {
                indexAxis: "y",
                animation: false,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        enabled: false,
                        external: ({ chart, tooltip }) => {
                            const el = getTooltipElement(chart);

                            if (tooltip.opacity === 0) {
                                el.style.opacity = "0";
                                return;
                            }

                            const item = tooltip.dataPoints[0];
                            el.innerHTML = (item.raw as number).toFixed(2);
                            el.style.opacity = "1";
                            el.style.left = `${tooltip.caretX}px`;
                            el.style.top = `${tooltip.caretY}px`;
                        },
                    },
                },
                scales: {
                    x: {
                        min: 0,
                        max: 100,
                        border: {
                            color: gridColor,
                        },
                        grid: {
                            color: gridColor,
                        },
                        ticks: {
                            color: textColor,
                        },
                    },
                    y: {
                        border: {
                            color: gridColor,
                        },
                        grid: {
                            display: false,
                        },
                        ticks: {
                            color: textColor,
                        },
                    },
                },
            },
        });
    }

    private async drawLineChart(
        canvas: HTMLCanvasElement,
        data: ChartData,
        textColor: string,
        gridColor: string,
    ) {
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

        new Chart(canvas, {
            type: "line",
            data,
            options: {
                animation: false,
                maintainAspectRatio: false,
                interaction: {
                    mode: "index",
                    intersect: false,
                },
                plugins: {
                    tooltip: {
                        enabled: false,
                        itemSort: (a, b) =>
                            (b.raw as number) - (a.raw as number),
                        external: ({ chart, tooltip }) => {
                            const el = getTooltipElement(chart);

                            if (tooltip.opacity === 0) {
                                el.style.opacity = "0";
                                return;
                            }

                            const rows = tooltip.dataPoints
                                .map((item) => {
                                    const color =
                                        colors[
                                            item.datasetIndex % colors.length
                                        ];
                                    return `<tr>
                                        <td style="color:${color}">&#9679;</td>
                                        <td>${item.dataset.label}</td>
                                        <td>${(item.raw as number).toFixed(2)}</td>
                                    </tr>`;
                                })
                                .join("");

                            el.innerHTML = `<div class="chart-tooltip-title">${renderYearMonth(tooltip.title[0])}</div><table>${rows}</table>`;
                            el.style.opacity = "1";
                            el.style.left = `${tooltip.caretX}px`;
                            el.style.top = `${tooltip.caretY}px`;
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
                        border: {
                            color: gridColor,
                        },
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
                        border: {
                            color: gridColor,
                        },
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

class CountryMap extends HTMLElement {
    connectedCallback() {
        const script = this.querySelector('script[type="application/json"]');
        if (!script?.textContent) {
            console.error(
                "country-map: script element or textContent missing.",
            );
            return;
        }

        let values: Record<string, { popularity: number }>;
        try {
            values = JSON.parse(script.textContent);
        } catch (error) {
            console.error("Failed to parse country-map data:", error);
            return;
        }

        if (!Object.keys(values).length) {
            console.error("country-map data is empty.");
            return;
        }

        const isSmallScreen = window.matchMedia("(max-width: 768px)").matches;

        if (isSmallScreen) {
            this.renderBarChart(values);
        } else {
            const container = document.createElement("div");
            container.id = `countries-map-${Math.random().toString(36).slice(2)}`;
            this.appendChild(container);
            this.renderMap(container.id, values);
        }
    }

    private async renderBarChart(
        values: Record<string, { popularity: number }>,
    ) {
        const sorted = Object.entries(values).sort(
            ([, a], [, b]) => b.popularity - a.popularity,
        );

        const regionNames = new Intl.DisplayNames(["en"], { type: "region" });
        const labels = sorted.map(([code]) => regionNames.of(code) ?? code);
        const data = sorted.map(([, v]) => v.popularity);

        const { Chart, BarElement, BarController, CategoryScale, LinearScale } =
            await import("chart.js");

        Chart.register(BarElement, BarController, CategoryScale, LinearScale);

        const canvas = document.createElement("canvas");
        canvas.style.height = `${sorted.length * 25}px`;
        this.appendChild(canvas);

        const style = getComputedStyle(document.documentElement);
        const textColor = style.getPropertyValue("--bs-body-color");
        const gridColor = style.getPropertyValue("--bs-border-color");
        const primaryColor = style.getPropertyValue("--bs-primary");

        new Chart(canvas, {
            type: "bar",
            data: {
                labels,
                datasets: [
                    {
                        data,
                        backgroundColor: primaryColor,
                    },
                ],
            },
            options: {
                indexAxis: "y",
                animation: false,
                maintainAspectRatio: false,
                events: [],
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    x: {
                        min: 0,
                        border: { color: gridColor },
                        grid: { color: gridColor },
                        ticks: { color: textColor },
                    },
                    y: {
                        border: { color: gridColor },
                        grid: { display: false },
                        ticks: { color: textColor },
                    },
                },
            },
        });
    }

    private async renderMap(
        targetId: string,
        values: Record<string, { popularity: number }>,
    ) {
        const { default: SvgMap } = await import("svgmap/src/js/core/svg-map");

        const style = getComputedStyle(document.documentElement);
        const cssVar = (name: string) => style.getPropertyValue(name).trim();

        new SvgMap({
            targetElementID: targetId,
            colorNoData: cssVar("--bs-secondary-bg"),
            colorMin: cssVar("--bs-border-color"),
            colorMax: cssVar("--bs-primary"),
            flagType: "emoji",
            showZoomReset: true,
            data: {
                data: {
                    popularity: {
                        name: "share",
                        format: "{0}%",
                        thousandSeparator: ",",
                    },
                },
                applyData: "popularity",
                values,
            },
        });
    }
}

customElements.define("country-map", CountryMap);

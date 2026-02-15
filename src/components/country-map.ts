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

        const container = document.createElement("div");
        container.id = `countries-map-${Math.random().toString(36).slice(2)}`;
        this.appendChild(container);

        this.renderMap(container.id, values);
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

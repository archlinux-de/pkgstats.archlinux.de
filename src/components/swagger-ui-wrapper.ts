class SwaggerUIWrapper extends HTMLElement {
    connectedCallback() {
        this.renderSwagger();
    }

    private async renderSwagger() {
        // swagger-ui's dependency chain uses Node's Buffer; provide a browser shim
        const g = globalThis as Record<string, unknown>;
        if (typeof g.Buffer === "undefined") {
            const { Buffer } = await import("buffer");
            g.Buffer = Buffer;
        }

        const SwaggerUI = (await import("swagger-ui")).default;
        const swaggerCSS = (await import("swagger-ui/dist/swagger-ui.css?raw"))
            .default;

        const shadow = this.attachShadow({ mode: "open" });

        const style = document.createElement("style");
        style.textContent =
            // store this as external css file and compile it in here
            // makes it easier to work with due to ide support
            swaggerCSS +
            `
            .information-container { display: none; }
            .swagger-ui .opblock .opblock-summary-path { max-width: calc(100% - 10rem); }
            @media (prefers-color-scheme: dark) {
                .swagger-ui { filter: invert(88%) hue-rotate(180deg); }
                .swagger-ui .microlight { filter: invert(100%) hue-rotate(180deg); }
                .swagger-ui input[type=text] { color: #3b4151; }
            }
        `;
        shadow.appendChild(style);

        const container = document.createElement("div");
        shadow.appendChild(container);

        SwaggerUI({
            domNode: container,
            url: "/api/doc.json",
            defaultModelsExpandDepth: 0,
            supportedSubmitMethods: ["get"],
        });
    }
}

customElements.define("swagger-ui-wrapper", SwaggerUIWrapper);

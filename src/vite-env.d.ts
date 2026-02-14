/// <reference types="vite/client" />

declare module "swagger-ui" {
    interface SwaggerUIOptions {
        domNode?: HTMLElement;
        url?: string;
        defaultModelsExpandDepth?: number;
        supportedSubmitMethods?: string[];
    }
    function SwaggerUI(options: SwaggerUIOptions): void;
    export default SwaggerUI;
}

declare module "swagger-ui/dist/swagger-ui.css?inline" {
    const css: string;
    export default css;
}

declare module "svgmap/src/js/core/svg-map" {
    interface SvgMapOptions {
        targetElementID: string;
        colorNoData?: string;
        colorMin?: string;
        colorMax?: string;
        flagType?: string;
        showZoomReset?: boolean;
        data?: Record<string, unknown>;
    }
    export default class SvgMap {
        constructor(options: SvgMapOptions);
    }
}

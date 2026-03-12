/// <reference types="vite/client" />

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

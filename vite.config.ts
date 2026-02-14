import { fileURLToPath } from "url";
import { dirname, resolve } from "path";
import type { UserConfig } from "vite";

const __dirname = dirname(fileURLToPath(import.meta.url));

export default {
    resolve: {
        alias: [
            {
                // swagger-ui's ESM entry excludes React; use the CJS bundle which includes it
                find: /^swagger-ui$/,
                replacement: resolve(
                    __dirname,
                    "node_modules/swagger-ui/dist/swagger-ui.js",
                ),
            },
            {
                // svgmap doesn't export src/ in package.json "exports"; alias to bypass
                find: /^svgmap-variables$/,
                replacement: resolve(
                    __dirname,
                    "node_modules/svgmap/src/scss/variables",
                ),
            },
            {
                find: /^svgmap-styles$/,
                replacement: resolve(
                    __dirname,
                    "node_modules/svgmap/src/scss/svg-map",
                ),
            },
            {
                // svgmap doesn't export src/js in package.json "exports"; alias to bypass
                find: "svgmap/src/js/core/svg-map",
                replacement: resolve(
                    __dirname,
                    "node_modules/svgmap/src/js/core/svg-map.js",
                ),
            },
        ],
    },
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ["import"],
                loadPaths: [resolve(__dirname, "node_modules")],
            },
        },
    },
    build: {
        manifest: "manifest.json",
        minify: "terser",
        rollupOptions: {
            input: "src/main.ts",
        },
    },
} satisfies UserConfig;

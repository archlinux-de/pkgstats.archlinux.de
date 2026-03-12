import { writeFileSync } from "fs";
import { fileURLToPath } from "url";
import { dirname, resolve } from "path";
import type { Plugin, UserConfig } from "vite";

const isWatch = process.argv.includes("--watch");

const __dirname = dirname(fileURLToPath(import.meta.url));

function notifyAir(): Plugin {
    return {
        name: "notify-air",
        writeBundle() {
            writeFileSync(".assets-rebuilt", String(Date.now()));
        },
    };
}

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
    define: {
        // swagger-ui's CJS deps reference Node's `global`; Rolldown no longer shims it
        global: "globalThis",
    },
    plugins: isWatch ? [notifyAir()] : [],
    publicDir: false,
    build: {
        manifest: "manifest.json",
        minify: !isWatch,
        rolldownOptions: {
            input: "src/main.ts",
        },
    },
} satisfies UserConfig;

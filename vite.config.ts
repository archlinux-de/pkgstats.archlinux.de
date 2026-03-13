import { writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
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

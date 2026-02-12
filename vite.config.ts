import type { UserConfig } from "vite";

export default {
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ["import"],
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

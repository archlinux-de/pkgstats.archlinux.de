import purgeCSSPlugin from "@fullhuman/postcss-purgecss";
import cssnano from "cssnano";

module.exports = {
    plugins: [
        purgeCSSPlugin({
            content: ["**/*.js", "**/*.html", "**/*.templ"],
            skippedContentGlobs: ["node_modules/**", "tests/**", "tmp/**"],
            variables: true,
            safelist: {
                greedy: [/^svgMap-/],
                variables: [
                    "--bs-primary",
                    "--bs-body-color",
                    "--bs-body-bg",
                    "--bs-secondary-bg",
                    "--bs-secondary-color",
                    "--bs-border-color",
                    "--bs-link-hover-color",
                ],
            },
        }),
        cssnano({ preset: ["cssnano-preset-advanced"] }),
    ],
};

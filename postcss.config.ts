import purgeCSSPlugin from "@fullhuman/postcss-purgecss";
import cssnano from "cssnano";

module.exports = {
    plugins: [
        purgeCSSPlugin({
            content: ["**/*.templ", "**/*.ts"],
            skippedContentGlobs: ["node_modules/**", "tests/**", "tmp/**"],
            variables: true,
            safelist: {
                greedy: [/^svgMap-/],
                // --bs-primary is only consumed via getPropertyValue() in country-map.ts
                // and never referenced in a CSS var() expression, so PurgeCSS cannot
                // detect it automatically and it must be safelisted explicitly.
                variables: ["--bs-primary"],
            },
        }),
        cssnano({ preset: ["cssnano-preset-advanced"] }),
    ],
};

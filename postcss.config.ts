import purgeCSSPlugin from "@fullhuman/postcss-purgecss";
import cssnano from "cssnano";

module.exports = {
    plugins: [
        purgeCSSPlugin({
            content: ["**/*.js", "**/*.html", "**/*.templ"],
            skippedContentGlobs: ["node_modules/**", "tests/**", "tmp/**"],
            variables: true,
        }),
        cssnano({ preset: ["cssnano-preset-advanced"] }),
    ],
};

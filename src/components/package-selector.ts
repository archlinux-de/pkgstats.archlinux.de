import plusIcon from "bootstrap-icons/icons/plus-lg.svg?raw";
import trashIcon from "bootstrap-icons/icons/trash.svg?raw";

const maxCompare = 10;

interface SelectedPackage {
    name: string;
    popularity: number;
}

class PackageSelector extends HTMLElement {
    private selected = new Map<string, number>();

    connectedCallback() {
        this.readFromURL();
        this.applyButtonIcons();
        this.patchPaginationLinks();

        this.addEventListener("click", (e) => {
            const btn = (e.target as Element).closest<HTMLButtonElement>(
                "[data-toggle-package]",
            );
            if (!btn) {
                return;
            }
            e.preventDefault();

            const name = btn.dataset.togglePackage;
            if (!name) {
                return;
            }
            if (this.selected.has(name)) {
                this.selected.delete(name);
            } else {
                const popularity = parseFloat(btn.dataset.popularity || "0");
                this.selected.set(name, popularity);
            }

            this.applyButtonIcons();
            this.renderSummary();
            this.updateURL();
            this.patchPaginationLinks();
        });
    }

    private readFromURL() {
        const params = new URLSearchParams(window.location.search);
        const compare = params.get("compare");
        if (compare) {
            for (const name of compare.split(",")) {
                const trimmed = name.trim();
                if (trimmed) {
                    this.selected.set(trimmed, 0);
                }
            }
        }

        // Pick up popularity values from buttons on the page
        for (const btn of this.querySelectorAll<HTMLButtonElement>(
            "[data-toggle-package]",
        )) {
            const name = btn.dataset.togglePackage;
            if (!name || !this.selected.has(name)) {
                continue;
            }
            this.selected.set(name, parseFloat(btn.dataset.popularity || "0"));
        }
    }

    private applyButtonIcons() {
        for (const btn of this.querySelectorAll<HTMLButtonElement>(
            "[data-toggle-package]",
        )) {
            const name = btn.dataset.togglePackage;
            const iconSpan = btn.querySelector("[data-icon]");
            if (name && iconSpan) {
                iconSpan.innerHTML = this.selected.has(name)
                    ? trashIcon
                    : plusIcon;
            }
        }
    }

    private renderSummary() {
        const container = this.querySelector("[data-compare-summary]");
        if (!container) {
            return;
        }

        const count = this.selected.size;

        if (count === 0) {
            container.innerHTML = `<div class="mb-2">
                No packages selected. Use the search below to add packages
                and allow the generation of a comparison graph over time.
            </div>`;
            return;
        }

        const packages = this.sortedPackages();

        const rows = packages
            .map(
                (pkg) => `<tr>
                <td class="text-nowrap">
                    <a href="/packages/${encodeURIComponent(pkg.name)}">${this.escapeHTML(pkg.name)}</a>
                </td>
                <td class="w-75">
                    <div class="progress bg-transparent progress-large" title="${pkg.popularity.toFixed(2)}%">
                        <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                             style="width: ${pkg.popularity.toFixed(2)}%"
                             aria-valuenow="${pkg.popularity.toFixed(2)}">${pkg.popularity > 5 ? `${pkg.popularity.toFixed(2)}%` : ""}</div>
                    </div>
                </td>
                <td class="align-middle">
                    <button type="button" class="d-flex btn w-100 p-0 b-none"
                            data-toggle-package="${this.escapeAttr(pkg.name)}"
                            data-popularity="${pkg.popularity.toFixed(2)}">
                        <span class="d-inline-flex text-primary justify-content-center w-100" data-icon>${trashIcon}</span>
                    </button>
                </td>
            </tr>`,
            )
            .join("");

        let action = "";
        if (count > 1 && count <= maxCompare) {
            const url = `/compare/packages/${packages.map((p) => encodeURIComponent(p.name)).join(",")}`;
            action = `<a href="${url}" class="d-inline-flex btn btn-primary">Compare</a>`;
        }
        if (count > maxCompare) {
            action = `<div role="alert" class="alert alert-info mb-4">You can only compare up to ${maxCompare} packages.</div>`;
        }

        container.innerHTML = `<div class="mb-2">
            <table class="table table-striped table-borderless table-sm">
                <thead>
                    <tr>
                        <th scope="col">Package</th>
                        <th scope="col">Popularity</th>
                        <th scope="col" class="d-none d-lg-block text-center">Compare</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
            ${action}
        </div>`;
    }

    private sortedPackages(): SelectedPackage[] {
        return [...this.selected.entries()]
            .map(([name, popularity]) => ({ name, popularity }))
            .sort((a, b) => b.popularity - a.popularity);
    }

    private compareParam(): string {
        if (this.selected.size === 0) {
            return "";
        }
        return [...this.selected.keys()].sort().join(",");
    }

    private buildQuery(base: URLSearchParams): string {
        const compare = this.compareParam();
        base.delete("compare");
        const parts: string[] = [];
        for (const [key, value] of base) {
            parts.push(
                `${encodeURIComponent(key)}=${encodeURIComponent(value)}`,
            );
        }
        if (compare) {
            parts.push(`compare=${compare}`);
        }
        return parts.length > 0 ? `?${parts.join("&")}` : "";
    }

    private updateURL() {
        const params = new URLSearchParams(window.location.search);
        const url = window.location.pathname + this.buildQuery(params);
        history.replaceState(null, "", url);
    }

    private patchPaginationLinks() {
        for (const a of this.querySelectorAll<HTMLAnchorElement>(
            "nav[aria-label] a.page-link",
        )) {
            const url = new URL(a.href, window.location.origin);
            a.href = url.pathname + this.buildQuery(url.searchParams);
        }
    }

    private escapeHTML(s: string): string {
        const el = document.createElement("span");
        el.textContent = s;
        return el.innerHTML;
    }

    private escapeAttr(s: string): string {
        return s
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    }
}

customElements.define("package-selector", PackageSelector);

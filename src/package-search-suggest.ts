const DEBOUNCE_MS = 200;
const MIN_QUERY_LENGTH = 2;
const SUGGESTION_LIMIT = 10;

const input = document.querySelector<HTMLInputElement>("#package-search");
const datalist = document.querySelector<HTMLDataListElement>(
    "#package-suggestions",
);

if (input && datalist) {
    let controller: AbortController | null = null;
    let timer: ReturnType<typeof setTimeout>;
    let previousValue = input.value;

    const isSuggestion = (query: string): boolean =>
        datalist.querySelector(`option[value="${CSS.escape(query)}"]`) !== null;

    const isSelection = (query: string): boolean => {
        const lengthDiff = query.length - previousValue.length;
        return lengthDiff > 1 && isSuggestion(query);
    };

    const fetchSuggestions = async (
        query: string,
        signal: AbortSignal,
    ): Promise<string[]> => {
        const res = await fetch(
            `/api/packages?query=${encodeURIComponent(query)}&limit=${SUGGESTION_LIMIT}`,
            { signal },
        );
        const data = await res.json();
        return data.packagePopularities.map((p: { name: string }) => p.name);
    };

    const updateDatalist = (names: string[]): void => {
        datalist.replaceChildren(
            ...names.map((name) => {
                const option = document.createElement("option");
                option.value = name;
                return option;
            }),
        );
    };

    input.addEventListener("input", () => {
        clearTimeout(timer);
        const query = input.value.trim();

        if (isSelection(query)) {
            input.form?.submit();
            return;
        }

        previousValue = query;

        if (query.length < MIN_QUERY_LENGTH) {
            updateDatalist([]);
            return;
        }

        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();

            try {
                updateDatalist(
                    await fetchSuggestions(query, controller.signal),
                );
            } catch (e) {
                if (e instanceof DOMException && e.name === "AbortError") {
                    return;
                }
                throw e;
            }
        }, DEBOUNCE_MS);
    });
}

document
    .querySelectorAll<HTMLSelectElement>("select[data-tab-nav]")
    .forEach((select) => {
        select.addEventListener("change", () => {
            window.location.href = select.value;
        });
    });

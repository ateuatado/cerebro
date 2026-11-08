(() => {
    const search = document.getElementById("part-work-query");
    const select = document.getElementById("part-work");
    const status = document.getElementById("part-work-count");
    if (!search || !select || !status) return;

    const normalize = (value) => value.normalize("NFD").replace(/\p{M}/gu, "").toLowerCase();
    const options = Array.from(select.options).slice(1);
    search.addEventListener("input", () => {
        const query = normalize(search.value.trim());
        const matches = options.filter((option) =>
            normalize(option.textContent + " " + (option.dataset.identifier || "")).includes(query)
        );
        const selected = select.value;
        select.replaceChildren(select.options[0], ...matches);
        select.value = matches.some((option) => option.value === selected) ? selected : "";
        status.textContent = matches.length ? `${matches.length} obra(s) disponível(is).` : "Nenhuma obra corresponde à busca.";
    });
})();

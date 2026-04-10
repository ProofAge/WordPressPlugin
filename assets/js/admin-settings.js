(() => {
    const config = window.ProofAgeAdminSettings;

    if (!config) {
        return;
    }

    const selectorRoots = document.querySelectorAll("[data-proofage-selector]");

    if (selectorRoots.length === 0) {
        return;
    }

    const debounce = (callback, delay) => {
        let timeoutId = 0;

        return (...args) => {
            window.clearTimeout(timeoutId);
            timeoutId = window.setTimeout(() => callback(...args), delay);
        };
    };

    const getSelectedIds = (selectedContainer) =>
        Array.from(selectedContainer.querySelectorAll("[data-proofage-selected-item]")).map((node) =>
            Number.parseInt(node.getAttribute("data-proofage-selected-item") || "0", 10)
        ).filter((id) => Number.isInteger(id) && id > 0);

    const createSelectedItem = (inputName, item) => {
        const wrapper = document.createElement("span");
        wrapper.className = "proofage-selector-item";
        wrapper.dataset.proofageSelectedItem = String(item.id);

        const hiddenInput = document.createElement("input");
        hiddenInput.type = "hidden";
        hiddenInput.name = inputName;
        hiddenInput.value = String(item.id);

        const label = document.createElement("span");
        label.className = "proofage-selector-item__label";
        label.textContent = item.label;

        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "button-link proofage-selector-item__remove";
        removeButton.dataset.proofageRemoveSelected = "1";
        removeButton.setAttribute("aria-label", "Remove selected item");
        removeButton.textContent = "×";

        wrapper.append(hiddenInput, label, removeButton);

        return wrapper;
    };

    const renderResults = (resultsContainer, items) => {
        resultsContainer.replaceChildren();

        if (items.length === 0) {
            const emptyState = document.createElement("div");
            emptyState.className = "proofage-selector-field__empty";
            emptyState.textContent = config.messages.noResults;
            resultsContainer.append(emptyState);
            return;
        }

        items.forEach((item) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "proofage-selector-result";
            button.dataset.proofageAddResult = String(item.id);
            button.dataset.label = item.label;
            button.textContent = item.label;
            resultsContainer.append(button);
        });
    };

    const searchItems = async (source, query, selectedIds) => {
        const body = new URLSearchParams();
        body.set("action", "proofage_search_scope_items");
        body.set("nonce", config.searchNonce);
        body.set("source", source);
        body.set("query", query);
        selectedIds.forEach((id) => body.append("excluded_ids[]", String(id)));

        const response = await fetch(config.ajaxUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
            },
            body,
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload?.success || !Array.isArray(payload.data?.items)) {
            return [];
        }

        return payload.data.items;
    };

    selectorRoots.forEach((root) => {
        const toggle = root.querySelector(".proofage-selector-field__toggle input[type=\"checkbox\"]:not([disabled])");
        const panel = root.querySelector(".proofage-selector-field__panel");
        const searchInput = root.querySelector("[data-proofage-selector-search=\"1\"]");
        const resultsContainer = root.querySelector("[data-proofage-selector-results=\"1\"]");
        const selectedContainer = root.querySelector("[data-proofage-selector-selected=\"1\"]");

        if (toggle && panel) {
            toggle.addEventListener("change", () => {
                panel.hidden = !toggle.checked;
            });
        }

        if (!searchInput || !resultsContainer || !selectedContainer) {
            return;
        }

        const inputName = selectedContainer.dataset.inputName;

        if (!inputName) {
            return;
        }

        let searchToken = 0;

        const performSearch = debounce(async () => {
            const currentToken = searchToken + 1;
            searchToken = currentToken;

            const items = await searchItems(
                searchInput.dataset.source || "",
                searchInput.value.trim(),
                getSelectedIds(selectedContainer)
            );

            if (currentToken !== searchToken) {
                return;
            }

            renderResults(resultsContainer, items);
        }, 180);

        searchInput.addEventListener("focus", () => {
            if (resultsContainer.childElementCount === 0) {
                performSearch();
            }
        });

        searchInput.addEventListener("input", () => {
            performSearch();
        });

        resultsContainer.addEventListener("click", (event) => {
            const button = event.target.closest("[data-proofage-add-result]");

            if (!button) {
                return;
            }

            const id = Number.parseInt(button.dataset.proofageAddResult || "0", 10);

            if (!Number.isInteger(id) || id <= 0 || getSelectedIds(selectedContainer).includes(id)) {
                return;
            }

            selectedContainer.append(createSelectedItem(inputName, {
                id,
                label: button.dataset.label || button.textContent || "",
            }));

            searchInput.focus();
            performSearch();
        });

        selectedContainer.addEventListener("click", (event) => {
            const button = event.target.closest("[data-proofage-remove-selected=\"1\"]");

            if (!button) {
                return;
            }

            button.closest("[data-proofage-selected-item]")?.remove();
            performSearch();
        });
    });
})();

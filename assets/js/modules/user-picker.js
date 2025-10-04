// assets/js/modules/user-picker.js
"use strict";

import { debounce } from "./form-utils.js";

export function escapeHtml(value = "") {
        return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
}

const DEFAULT_MESSAGES = {
        empty: "Sin usuarios disponibles",
        noResults: "No se han encontrado coincidencias",
        loading: "Buscando usuarios...",
        error: "No ha sido posible cargar los usuarios.",
};

function defaultRenderItem(option) {
        const avatarHtml = option.avatar
                ? `<img src="${escapeHtml(option.avatar)}" alt="" class="user-picker__avatar" loading="lazy" decoding="async" />`
                : `<span class="user-picker__avatar user-picker__avatar--placeholder">${escapeHtml(
                                option.initials || "?",
                        )}</span>`;
        const secondary = option.secondary
                ? `<span class="user-picker__meta">${escapeHtml(option.secondary)}</span>`
                : "";
        return `
                ${avatarHtml}
                <span class="user-picker__info">
                        <span class="user-picker__primary">${escapeHtml(option.label)}</span>
                        ${secondary}
                </span>
        `;
}

export default class UserPicker {
        constructor(options = {}) {
                this.container = options.container || null;
                this.input = options.input || null;
                this.valueInput = options.valueInput || null;
                this.resultsContainer = options.resultsContainer || null;
                this.clearButton = options.clearButton || null;
                this.labelElement = options.labelElement || null;
                this.renderItem =
                        typeof options.renderItem === "function" ? options.renderItem : defaultRenderItem;
                this.messages = Object.assign({}, DEFAULT_MESSAGES, options.messages || {});
                this.onChange = typeof options.onChange === "function" ? options.onChange : () => {};
                this.searchProvider =
                        typeof options.onSearch === "function"
                                ? options.onSearch
                                : async () => [];
                this.resolveById =
                        typeof options.resolveById === "function" ? options.resolveById : null;
                this.debounceMs = Number(options.debounceMs || 250);

                this.items = [];
                this.activeIndex = -1;
                this.searchToken = 0;
                this.isOpen = false;
                this.selectedUser = null;
                this.handleDocumentClick = this.handleDocumentClick.bind(this);
                this.handleResultsClick = this.handleResultsClick.bind(this);
                this.handleResultsMouseOver = this.handleResultsMouseOver.bind(this);

                if (!this.input || !this.resultsContainer || !this.valueInput) {
                        return;
                }

                this.setupAria();
                this.setupEvents();
                this.initFromExistingValue();
        }

        setupAria() {
                const resultsId =
                        this.resultsContainer.id || `${this.input.id || "user-picker"}-results`;
                this.resultsContainer.id = resultsId;
                this.resultsContainer.setAttribute("role", "listbox");
                this.resultsContainer.setAttribute("aria-hidden", "true");

                this.input.setAttribute("role", "combobox");
                this.input.setAttribute("aria-autocomplete", "list");
                this.input.setAttribute("aria-expanded", "false");
                this.input.setAttribute("aria-controls", resultsId);
                this.input.setAttribute("autocomplete", "off");
        }

        setupEvents() {
                this.debouncedSearch = debounce((term) => {
                        this.performSearch(term);
                }, this.debounceMs);

                this.input.addEventListener("input", (event) => {
                        const term = event.target.value || "";
                        if (this.selectedUser && term !== this.selectedUser.displayValue) {
                                this.clear({ preserveText: true, silent: true });
                        }
                        this.updateValueState();
                        this.open();
                        this.debouncedSearch(term);
                });

                this.input.addEventListener("focus", () => {
                        this.open();
                        if (!this.items.length) {
                                this.performSearch(this.input.value || "");
                        }
                });

                this.input.addEventListener("keydown", (event) => {
                        switch (event.key) {
                                case "ArrowDown":
                                        event.preventDefault();
                                        if (!this.isOpen) this.open();
                                        this.moveHighlight(1);
                                        break;
                                case "ArrowUp":
                                        event.preventDefault();
                                        if (!this.isOpen) this.open();
                                        this.moveHighlight(-1);
                                        break;
                                case "Enter":
                                        if (this.isOpen && this.activeIndex >= 0) {
                                                event.preventDefault();
                                                this.selectIndex(this.activeIndex);
                                        }
                                        break;
                                case "Escape":
                                        if (this.isOpen) {
                                                event.preventDefault();
                                                this.close();
                                        }
                                        break;
                                default:
                                        break;
                        }
                });

                if (this.clearButton) {
                        this.clearButton.addEventListener("click", () => {
                                this.clear({ preserveText: false, silent: false, focus: true });
                        });
                }

                this.resultsContainer.addEventListener("mousedown", (event) => {
                        // Prevent the input from losing focus when clicking an option
                        event.preventDefault();
                });
                this.resultsContainer.addEventListener("click", this.handleResultsClick);
                this.resultsContainer.addEventListener("mouseover", this.handleResultsMouseOver);

                document.addEventListener("click", this.handleDocumentClick);
        }

        initFromExistingValue() {
                if (!this.valueInput.value) {
                        this.updateValueState();
                        return;
                }

                const label = this.valueInput.dataset.displayLabel || "";
                if (label) {
                        this.applySelection({
                                id: this.valueInput.value,
                                label,
                                secondary: this.valueInput.dataset.secondaryLabel || "",
                                avatar: this.valueInput.dataset.avatar || "",
                                initials: this.valueInput.dataset.initials || "",
                                displayValue: label,
                                raw: null,
                        }, { silent: true });
                        return;
                }

                if (this.resolveById) {
                        const id = this.valueInput.value;
                        this.resolveById(id)
                                .then((user) => {
                                        if (user) {
                                                this.applySelection(user, { silent: true });
                                        }
                                })
                                .catch(() => {
                                        this.clear({ preserveText: false, silent: true });
                                });
                } else {
                        this.clear({ preserveText: false, silent: true });
                }
        }

        async performSearch(term = "") {
                if (!this.searchProvider) return;

                const currentToken = ++this.searchToken;
                if (this.resultsContainer) {
                        this.renderStatus("loading", this.messages.loading);
                }

                try {
                        const results = await this.searchProvider(term.trim());
                        if (currentToken !== this.searchToken) return;
                        this.items = Array.isArray(results) ? results : [];
                        if (!this.items.length) {
                                const message = term.trim()
                                        ? this.messages.noResults
                                        : this.messages.empty;
                                this.renderStatus("empty", message);
                                return;
                        }
                        this.renderItems();
                } catch (error) {
                        if (currentToken !== this.searchToken) return;
                        console.warn("[UserPicker] search error", error);
                        this.renderStatus("error", this.messages.error);
                }
        }

        renderStatus(type, message) {
                        if (!this.resultsContainer) return;
                        this.resultsContainer.innerHTML = "";
                        const li = document.createElement("div");
                        li.className = `user-picker__status user-picker__status--${type}`;
                        li.textContent = message;
                        this.resultsContainer.appendChild(li);
                        this.open();
                        this.activeIndex = -1;
        }

        renderItems() {
                if (!this.resultsContainer) return;
                this.resultsContainer.innerHTML = "";
                this.items.forEach((item, index) => {
                        const option = document.createElement("div");
                        option.className = "user-picker__item";
                        option.setAttribute("role", "option");
                        option.dataset.userPickerIndex = String(index);
                        option.id = `${this.resultsContainer.id}-option-${index}`;
                        option.innerHTML = this.renderItem(item);
                        this.resultsContainer.appendChild(option);
                });
                this.activeIndex = -1;
                this.open();
        }

        handleResultsClick(event) {
                const option = event.target.closest("[data-user-picker-index]");
                if (!option) return;
                const index = parseInt(option.dataset.userPickerIndex, 10);
                if (!Number.isNaN(index)) {
                        this.selectIndex(index);
                }
        }

        handleResultsMouseOver(event) {
                const option = event.target.closest("[data-user-picker-index]");
                if (!option) return;
                const index = parseInt(option.dataset.userPickerIndex, 10);
                if (!Number.isNaN(index)) {
                        this.setActiveIndex(index, { scroll: false });
                }
        }

        handleDocumentClick(event) {
                if (!this.container) return;
                if (this.container.contains(event.target)) return;
                this.close();
        }

        setActiveIndex(index, { scroll = true } = {}) {
                if (!this.items.length) return;
                const bounded = Math.max(0, Math.min(index, this.items.length - 1));
                this.activeIndex = bounded;
                Array.from(this.resultsContainer.children).forEach((child, idx) => {
                        if (!(child instanceof HTMLElement)) return;
                        const isActive = idx === bounded;
                        child.classList.toggle("user-picker__item--active", isActive);
                        child.setAttribute("aria-selected", isActive ? "true" : "false");
                        if (isActive) {
                                this.input.setAttribute("aria-activedescendant", child.id);
                                if (scroll) {
                                        child.scrollIntoView({ block: "nearest" });
                                }
                        }
                });
        }

        moveHighlight(delta) {
                if (!this.items.length) return;
                let next = this.activeIndex + delta;
                if (next < 0) next = this.items.length - 1;
                if (next >= this.items.length) next = 0;
                this.setActiveIndex(next);
        }

        selectIndex(index) {
                const item = this.items[index];
                if (!item) return;
                this.applySelection(item);
        }

        applySelection(item, { silent = false } = {}) {
                this.selectedUser = Object.assign({}, item, {
                        id: item.id,
                        displayValue: item.displayValue || item.label || "",
                });
                if (this.input) {
                        this.input.value = this.selectedUser.displayValue || "";
                }
                if (this.valueInput) {
                        this.valueInput.value = item.id || "";
                        this.valueInput.dataset.displayLabel = this.selectedUser.displayValue || "";
                        this.valueInput.dataset.secondaryLabel = item.secondary || "";
                        this.valueInput.dataset.avatar = item.avatar || "";
                        this.valueInput.dataset.initials = item.initials || "";
                        if (item.email) this.valueInput.dataset.email = item.email;
                        else delete this.valueInput.dataset.email;
                        if (item.personalName)
                                this.valueInput.dataset.personalName = item.personalName;
                        else delete this.valueInput.dataset.personalName;
                        if (item.companyName)
                                this.valueInput.dataset.companyName = item.companyName;
                        else delete this.valueInput.dataset.companyName;
                        this.valueInput.dispatchEvent(new Event("change", { bubbles: true }));
                }
                this.updateValueState();
                this.close();
                if (this.clearButton) this.clearButton.hidden = !this.input.value;
                if (!silent) this.onChange(item);
        }

        updateValueState() {
                if (!this.container || !this.input) return;
                const hasValue = (this.input.value || "").trim() !== "";
                this.container.classList.toggle("has-value", hasValue);
                if (this.clearButton) {
                        this.clearButton.hidden = !hasValue;
                }
        }

        clear({ preserveText = false, silent = false, focus = false } = {}) {
                if (this.selectedUser) {
                        this.selectedUser = null;
                        if (this.valueInput) {
                                this.valueInput.value = "";
                                this.valueInput.dataset.displayLabel = "";
                                this.valueInput.dataset.secondaryLabel = "";
                                this.valueInput.dataset.avatar = "";
                                this.valueInput.dataset.initials = "";
                                delete this.valueInput.dataset.email;
                                delete this.valueInput.dataset.personalName;
                                delete this.valueInput.dataset.companyName;
                                if (!silent) {
                                        this.valueInput.dispatchEvent(new Event("change", { bubbles: true }));
                                }
                        }
                }
                if (!preserveText && this.input) {
                        this.input.value = "";
                }
                this.updateValueState();
                if (focus && this.input) {
                        this.input.focus();
                }
                if (!silent) {
                        this.onChange(null);
                }
        }

        open() {
                if (!this.resultsContainer || this.isOpen) return;
                this.resultsContainer.hidden = false;
                this.resultsContainer.setAttribute("aria-hidden", "false");
                this.input.setAttribute("aria-expanded", "true");
                this.isOpen = true;
        }

        close() {
                if (!this.resultsContainer || !this.isOpen) return;
                this.resultsContainer.hidden = true;
                this.resultsContainer.setAttribute("aria-hidden", "true");
                this.input.setAttribute("aria-expanded", "false");
                this.input.removeAttribute("aria-activedescendant");
                this.isOpen = false;
                this.activeIndex = -1;
        }

        setLabel(text) {
                if (!this.labelElement) return;
                this.labelElement.textContent = text;
        }

        setPlaceholder(text) {
                if (!this.input) return;
                this.input.setAttribute("placeholder", text || "");
        }

        setMessages(messages = {}) {
                this.messages = Object.assign({}, DEFAULT_MESSAGES, messages || {});
        }

        setSearchProvider(fn) {
                if (typeof fn === "function") {
                        this.searchProvider = fn;
                }
        }

        setResolveHandler(fn) {
                if (typeof fn === "function") {
                        this.resolveById = fn;
                }
        }

        async setValueById(id, { silent = false } = {}) {
                        if (!id) {
                                this.clear({ preserveText: false, silent, focus: false });
                                return;
                        }
                        if (!this.resolveById) return;
                        try {
                                const user = await this.resolveById(id);
                                if (user) {
                                        this.applySelection(user, { silent });
                                }
                        } catch (error) {
                                console.warn("[UserPicker] resolveById error", error);
                        }
        }
}

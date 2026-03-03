export function searchableDropdown({
    items = [],
    selectedId = '',
    name = '',
    selectedName = '',
    placeholder = 'Select an option',
    showAllOnOpen = false // NEW PROP
}) {
    return {
        open: false,
        search: '',
        selectedId,
        selectedName,
        all: items,
        filtered: [],
        selectedIndex: -1,
        placeholder,
        showAllOnOpen, // store the prop
        originalId: selectedId,
        originalName: selectedName,
        hasChanged: false,

        init() {
            this.filtered = [...this.all];
            const selectedItem = this.all.find(item => item.id == this.selectedId);
            if (!this.selectedName && selectedItem) {
                this.selectedName = selectedItem.name;
            }

            // Auto-select if there's only one item and nothing is selected
            if (this.all.length === 1 && !this.selectedId) {
                this.select(this.all[0]);
            }

            window.addEventListener('dropdown-opened', (e) => {
                if (e.detail && e.detail.name !== name) {
                    this.open = false;
                }
            });

            window.addEventListener('reset-dropdowns', () => {
                if (!this.hasChanged) {
                    this.selectedId = this.originalId;
                    this.selectedName = this.originalName;
                } else {
                    this.selectedId = '';
                    this.selectedName = '';
                }
                this.search = '';
                this.filtered = [...this.all];
                this.hasChanged = false;
            });
        },

        filterOptions() {
            const term = this.search.toLowerCase();
            this.filtered = this.all.filter(item => item.name.toLowerCase().includes(term));
            this.selectedIndex = -1;
        },

        moveSelection(direction) {
            if (direction === 'down' && this.selectedIndex < this.filtered.length - 1) {
                this.selectedIndex++;
            } else if (direction === 'up' && this.selectedIndex > 0) {
                this.selectedIndex--;
            }
        },

        select(option) {
            this.selectedId = option.id;
            this.selectedName = option.name;
            this.hasChanged = true;
            this.search = '';
            this.open = false;

            this.$dispatch('dropdown-select', {
                name: name,
                value: option.id,
                displayName: option.name
            });

            if (name === 'supplier_id') {
                const selectElem = document.getElementById('select-supplier-task');
                if (selectElem) {
                    const matchingOption = Array.from(selectElem.options).find(opt => opt.value == option.id);
                    if (matchingOption) {
                        selectElem.value = matchingOption.value;
                        selectElem.dispatchEvent(new Event('change'));
                    }
                }
            }

            if (name === 'agent_id') window.selectedAgentName = option.name;
            if (name === 'supplier_id') window.selectedSupplierName = option.name;

            if (window.selectedAgentName && window.selectedSupplierName) {
                searchableDropdownSupplierInstance?.renderSupplierInput?.(window.selectedSupplierName);
            }
        },

        focusSearch($refs) {
            window.dispatchEvent(new CustomEvent('dropdown-opened', { detail: { name } }));
            this.open = true;
            this.search = '';

            // Force refresh of filtered list
            this.filtered = [...this.all];

            this.$nextTick(() => {
                $refs.searchInput.focus();

                // Trigger a dummy input event to ensure rendering
                $refs.searchInput.dispatchEvent(new Event('input'));
            });
        },

        highlightMatch(name) {
            if (!this.search) return name;
            const regex = new RegExp(`(${this.search})`, 'gi');
            return name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
        },

        handleKeydown(event) {
            if (event.key === 'ArrowDown') {
                this.moveSelection('down');
            } else if (event.key === 'ArrowUp') {
                this.moveSelection('up');
            } else if (event.key === 'Enter' && this.selectedIndex >= 0) {
                this.select(this.filtered[this.selectedIndex]);
            }
        }
    };
}

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

        init() {
            this.filtered = [...this.all];
            const selectedItem = this.all.find(item => item.id == this.selectedId);
            if (!this.selectedName && selectedItem) {
                this.selectedName = selectedItem.name;
            }

            window.addEventListener('reset-dropdowns', () => {
                this.selectedId = '';
                this.selectedName = '';
                this.search = '';
                this.filtered = [...this.all];
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
            this.search = '';
            this.open = false;

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

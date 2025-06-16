export function searchableDropdown({ items = [], selectedId = '', name = '', selectedName = '', placeholder = 'Select an option' }) {
    return {
        open: false,
        search: '',
        selectedId,
        selectedName,
        all: items,
        filtered: [],
        placeholder,
        init() {
            this.filtered = [...this.all];
            const selectedItem = this.all.find(item => item.id == this.selectedId);
            if (!this.selectedName && selectedItem) {
                this.selectedName = selectedItem.name;
            }
        },
        filterOptions() {
            const term = this.search.toLowerCase();
            this.filtered = this.all.filter(item => item.name.toLowerCase().includes(term));
        },
        select(option) {
            this.selectedId = option.id;
            this.selectedName = option.name;
            this.search = '';
            this.open = false;

            // Trigger hidden select change if supplier
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

            // Store for logic triggering
            if (name === 'agent_id') window.selectedAgentName = option.name;
            if (name === 'supplier_id') window.selectedSupplierName = option.name;

            if (window.selectedAgentName && window.selectedSupplierName) {
                searchableDropdownSupplierInstance?.renderSupplierInput?.(window.selectedSupplierName);
            }
        },
        focusSearch($refs) {
            this.open = true;
            this.$nextTick(() => $refs.searchInput.focus());
        },
        highlightMatch(name) {
            if (!this.search) return name;
            const regex = new RegExp(`(${this.search})`, 'gi');
            return name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
        }
    };
}

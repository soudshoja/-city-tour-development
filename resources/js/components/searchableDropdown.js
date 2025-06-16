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
            this.$dispatch('dropdown-selected', { name, option });
        },
        highlightMatch(name) {
            if (!this.search) return name;
            const regex = new RegExp(`(${this.search})`, 'gi');
            return name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
        }
    };
}

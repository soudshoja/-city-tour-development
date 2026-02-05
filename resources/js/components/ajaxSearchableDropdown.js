export function ajaxSearchableDropdown({
    selectedId = '',
    name = '',
    selectedName = '',
    placeholder = 'Select an option',
    dataId = '',
    ajaxUrl = '',
    // responseKey = 'tasks',
}) {
    return {
        open: false,
        search: '',
        selectedId,
        selectedName,
        name,
        filtered: [],
        loading: false,
        placeholder,
        dataId,
        ajaxUrl,
        debounceTimer: null,
        originalId: selectedId,
        originalName: selectedName,

        debouncedSearch() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.searchData();
            }, 300);
        },

        async searchData() {
            if (!this.ajaxUrl) return;

            this.loading = true;
            try {
                const url = new URL(this.ajaxUrl, window.location.origin);
                url.searchParams.append('id', this.dataId);
                url.searchParams.append('search', this.search);

                const response = await fetch(url);

                if (!response.ok) {
                    console.error('Error fetching tasks: HTTP', response.status, response.statusText);
                    this.filtered = [];
                    return;
                }

                const data = await response.json();

                this.filtered = [];

                if (data && Array.isArray(data)) {
                    this.filtered = data; 
                }
            } catch (error) {
                console.error('Error fetching tasks:', error);
                this.filtered = [];
            } finally {
                this.loading = false;
            }
        },

        select(option) {
            this.selectedId = option.id;
            this.selectedName = option.name;
            this.search = '';
            this.open = false;

            // ONLY dispatch if value actually changed
            if (String(option.id) !== String(this.originalId)) {
                this.$dispatch('dropdown-select', {
                    name: this.name,
                    value: option.id,
                    displayName: option.name
                });
            }
        },

        focusSearch($refs) {
            this.open = true;
            this.search = '';

            // Trigger initial search when opening
            this.searchData();

            this.$nextTick(() => {
                $refs.searchInput.focus();
            });
        },

        escapeRegex(value) {
            return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        highlightMatch(name) {
            if (!this.search) return name;
            const escapedSearch = this.escapeRegex(this.search);
            const regex = new RegExp(`(${escapedSearch})`, 'gi');
            return name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
        }
    };
}

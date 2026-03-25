export function ajaxSearchableDropdown({
    selectedId = '',
    name = '',
    selectedName = '',
    placeholder = 'Select an option',
    dataId = '',
    ajaxUrl = '',
    columns = [],
    displayColumn = 'name',
    mode = 'dropdown',
    watchDropdown = '',
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
        columns,
        displayColumn,
        mode,
        watchDropdown,
        debounceTimer: null,
        originalId: selectedId,
        originalName: selectedName,

        init() {
            if (this.watchDropdown) {
                window.addEventListener('dropdown-select', (e) => {
                    if (e.detail && e.detail.name === this.watchDropdown) {
                        this.dataId = e.detail.value;
                        this.selectedId = '';
                        this.selectedName = '';
                        this.filtered = [];
                    }
                });
            }

            window.addEventListener('dropdown-opened', (e) => {
                if (e.detail && e.detail.name !== name) {
                    this.open = false;
                }
            });
        },

        toggleOpen() {
            this.open = !this.open;
            if (this.open) {
                window.dispatchEvent(new CustomEvent('dropdown-opened', { detail: { name } }));
                this.$nextTick(() => this.focusSearch());
            }
        },

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

                if (this.columns && this.columns.length > 0) {
                    const keys = this.columns.map(c => c.key);
                    url.searchParams.append('columns', keys.join(','));
                }

                const response = await fetch(url);

                if (!response.ok) {
                    console.error('Search error: HTTP', response.status, response.statusText);
                    this.filtered = [];
                    return;
                }

                const data = await response.json();
                this.filtered = Array.isArray(data) ? data : [];
            } catch (error) {
                console.error('Search error:', error);
                this.filtered = [];
            } finally {
                this.loading = false;
            }
        },

        select(option) {
            this.selectedId = option.id;
            this.selectedName = option[this.displayColumn] || option.name || '';
            this.search = '';
            this.open = false;

            if (String(option.id) !== String(this.originalId)) {
                this.$dispatch('dropdown-select', {
                    name: this.name,
                    value: option.id,
                    displayName: this.selectedName,
                });
            }
        },

        focusSearch() {
            this.search = '';
            this.searchData();

            this.$nextTick(() => {
                const ref = this.mode === 'modal'
                    ? this.$refs.modalSearchInput
                    : this.$refs.searchInput;
                if (ref) ref.focus();
            });
        },

        escapeRegex(value) {
            return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        highlightMatch(text) {
            if (!text || !this.search) return text || '';
            const escapedSearch = this.escapeRegex(this.search);
            const regex = new RegExp(`(${escapedSearch})`, 'gi');
            return String(text).replace(regex, '<mark class="bg-blue-200 rounded px-0.5">$1</mark>');
        },

        renderOption(option) {
            if (!this.columns || this.columns.length === 0) {
                return this.highlightMatch(option[this.displayColumn] || option.name);
            }

            const primary = this.columns.find(c => c.key === this.displayColumn) || this.columns[0];
            const secondary = this.columns.filter(c => c.key !== primary.key);

            let html = '<div class="text-sm font-medium text-gray-900">'
                     + this.highlightMatch(option[primary.key])
                     + '</div>';

            const parts = secondary
                .filter(col => option[col.key] != null && String(option[col.key]).trim() !== '')
                .map(col => {
                    return '<span class="text-gray-400">' + col.label + ':</span> '
                         + this.highlightMatch(option[col.key]);
                });

            if (parts.length > 0) {
                html += '<div class="text-xs text-gray-500 mt-0.5">'
                      + parts.join(' <span class="text-gray-300 mx-1">&middot;</span> ')
                      + '</div>';
            }

            return html;
        },
    };
}

export function ajaxSearchableDropdown({
    selectedId = '',
    name = '',
    selectedName = '',
    placeholder = 'Select an option',
    taskId = '',
    ajaxUrl = ''
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
        taskId,
        ajaxUrl,
        debounceTimer: null,
        originalId: selectedId,
        originalName: selectedName,

        init() {
            // If there's a selected name but filtered is empty, populate it
            if (this.selectedName && this.selectedId) {
                this.filtered = [{
                    id: this.selectedId,
                    name: this.selectedName
                }];
            }
        },

        debouncedSearch() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => {
                this.searchTasks();
            }, 300);
        },

        async searchTasks() {
            if (!this.ajaxUrl) return;

            this.loading = true;
            try {
                const url = new URL(this.ajaxUrl, window.location.origin);
                url.searchParams.append('task_id', this.taskId);
                url.searchParams.append('search', this.search);

                const response = await fetch(url);
                const data = await response.json();

                if (data.tasks) {
                    this.filtered = data.tasks;
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
            this.searchTasks();

            this.$nextTick(() => {
                $refs.searchInput.focus();
            });
        },

        highlightMatch(name) {
            if (!this.search) return name;
            const regex = new RegExp(`(${this.search})`, 'gi');
            return name.replace(regex, '<mark class="bg-blue-200">$1</mark>');
        }
    };
}

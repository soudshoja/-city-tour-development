export function multiPicker({
    items,
    preselected = [],
    allLabel = "All",
    placeholder = "Select items",
}) {
    return {
        open: false,
        q: "",
        items,
        selected: [...preselected],
        get allSelected() {
            return (
                this.items.length > 0 &&
                this.selected.length === this.items.length
            );
        },
        filtered() {
            const s = this.q.trim().toLowerCase();
            return s
                ? this.items.filter((i) => i.name.toLowerCase().includes(s))
                : this.items;
        },
        toggle(id) {
            const i = this.selected.indexOf(id);
            i > -1 ? this.selected.splice(i, 1) : this.selected.push(id);
        },
        toggleAll() {
            this.allSelected
                ? (this.selected = [])
                : (this.selected = this.items.map((i) => i.id));
        },
        summary() {
            if (this.selected.length === 0 || this.allSelected)
                return `${allLabel}`;
            return `${this.selected.length} selected`;
        },
        placeholder,
    };
}

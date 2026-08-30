@push('styles')
@vite(['resources/css/task/index.css'])
@endpush
<x-app-layout>
    <div x-data="followUpTab()" x-init="init()">
        <div class="flex justify-between items-center gap-5 my-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('tasks.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </a>
                <h2 class="text-3xl font-bold">Follow-up</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400">On-hold and confirmed bookings awaiting ticketing</span>
            </div>
        </div>

        <div x-show="flashMessage" x-cloak class="mb-3 text-sm font-medium text-emerald-700 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-300 rounded-lg px-4 py-2" x-text="flashMessage"></div>
        <div x-show="flashError" x-cloak class="mb-3 text-sm font-medium text-red-700 bg-red-50 dark:bg-red-900/30 dark:text-red-300 rounded-lg px-4 py-2" x-text="flashError"></div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wide">Client</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wide">Passenger</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wide">Supplier</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wide">Owner agent</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wide">Deposit held</th>
                            <th class="text-left px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wide">Time left</th>
                            <th class="text-right px-4 py-3 font-semibold text-gray-600 dark:text-gray-300 uppercase text-xs tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tasks as $task)
                            @php
                                $hoursLeft = $task->deadline_at ? ($task->deadline_at->timestamp - now()->timestamp) / 3600 : null;
                                $timeLeftClass = match(true) {
                                    $hoursLeft === null => 'text-gray-400',
                                    $hoursLeft <= 0 => 'text-red-700 bg-red-100 dark:bg-red-900/40 dark:text-red-300',
                                    $hoursLeft <= 2 => 'text-red-700 bg-red-100 dark:bg-red-900/40 dark:text-red-300',
                                    $hoursLeft <= 24 => 'text-amber-700 bg-amber-100 dark:bg-amber-900/40 dark:text-amber-300',
                                    default => 'text-emerald-700 bg-emerald-100 dark:bg-emerald-900/40 dark:text-emerald-300',
                                };
                            @endphp
                            <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                <td class="px-4 py-3">{{ $task->client?->full_name ?? $task->client_name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $task->passenger_name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $task->supplier?->name ?? '-' }}</td>
                                <td class="px-4 py-3">{{ $task->agent?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono">{{ number_format($task->deposit_held ?? 0, 3) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $timeLeftClass }}" title="{{ $task->deadline_at }}">
                                        @if (!$task->deadline_at) No deadline
                                        @elseif ($hoursLeft <= 0) Past due
                                        @elseif ($hoursLeft < 24) {{ round($hoursLeft) }}h left
                                        @else {{ round($hoursLeft / 24, 1) }}d left
                                        @endif
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-1.5 flex-wrap">
                                        <button type="button" @click="issue({{ $task->id }})" class="px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:text-emerald-300 rounded-lg">Issue</button>
                                        <button type="button" @click="openExtend({{ $task->id }})" class="px-2.5 py-1 text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-300 rounded-lg">Extend</button>
                                        <button type="button" @click="cancel({{ $task->id }})" class="px-2.5 py-1 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-300 rounded-lg">Cancel</button>
                                        <button type="button" @click="openNote({{ $task->id }})" class="px-2.5 py-1 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 rounded-lg">Note</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center px-4 py-10 text-gray-400">No on-hold or confirmed tasks awaiting ticketing.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Extend-deadline modal -->
        <div x-show="extendTaskId" x-cloak @keydown.escape.window="extendTaskId = null" class="fixed inset-0 z-[10004] flex items-center justify-center bg-gray-900/60">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4 p-6 space-y-3">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Extend deadline</h3>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block">New deadline</label>
                <input type="datetime-local" x-model="extendDeadlineAt" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg px-3 py-2 text-sm">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 block">Reason (required)</label>
                <textarea x-model="extendReason" rows="2" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg px-3 py-2 text-sm"></textarea>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="extendTaskId = null" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg">Cancel</button>
                    <button type="button" @click="submitExtend()" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg">Save</button>
                </div>
            </div>
        </div>

        <!-- Note modal -->
        <div x-show="noteTaskId" x-cloak @keydown.escape.window="noteTaskId = null" class="fixed inset-0 z-[10004] flex items-center justify-center bg-gray-900/60">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl max-w-md w-full mx-4 p-6 space-y-3">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add note</h3>
                <textarea x-model="noteText" rows="3" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-900 rounded-lg px-3 py-2 text-sm"></textarea>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="noteTaskId = null" class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg">Cancel</button>
                    <button type="button" @click="submitNote()" class="px-4 py-2 text-sm font-semibold text-white bg-gray-700 hover:bg-gray-800 rounded-lg">Save note</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function followUpTab() {
            return {
                flashMessage: null,
                flashError: null,
                extendTaskId: null,
                extendDeadlineAt: '',
                extendReason: '',
                noteTaskId: null,
                noteText: '',

                init() {},

                async issue(taskId) {
                    if (!confirm('Issue this task now? This posts the sale immediately.')) return;
                    const res = await this.post(`/tasks/${taskId}/follow-up/issue`, {});
                    if (res.success) { this.flashMessage = 'Task issued.'; setTimeout(() => location.reload(), 900); }
                    else { this.flashError = res.message || 'Failed to issue task.'; }
                },

                openExtend(taskId) {
                    this.extendTaskId = taskId;
                    this.extendDeadlineAt = '';
                    this.extendReason = '';
                },

                async submitExtend() {
                    if (!this.extendDeadlineAt || !this.extendReason) {
                        this.flashError = 'A new deadline and a reason are both required.';
                        return;
                    }
                    const res = await this.post(`/tasks/${this.extendTaskId}/follow-up/extend`, {
                        deadline_at: this.extendDeadlineAt,
                        reason: this.extendReason,
                    });
                    this.extendTaskId = null;
                    if (res.success) { this.flashMessage = 'Deadline extended.'; setTimeout(() => location.reload(), 900); }
                    else { this.flashError = res.message || 'Failed to extend deadline.'; }
                },

                async cancel(taskId) {
                    const reason = prompt('Reason for cancelling (optional):') ?? '';
                    if (!confirm('Cancel this booking?')) return;
                    const res = await this.post(`/tasks/${taskId}/follow-up/cancel`, { reason });
                    if (res.success) { this.flashMessage = 'Booking cancelled.'; setTimeout(() => location.reload(), 900); }
                    else { this.flashError = res.message || 'Failed to cancel booking.'; }
                },

                openNote(taskId) {
                    this.noteTaskId = taskId;
                    this.noteText = '';
                },

                async submitNote() {
                    if (!this.noteText) { this.flashError = 'Note text is required.'; return; }
                    const res = await this.post(`/tasks/${this.noteTaskId}/follow-up/note`, { note: this.noteText });
                    this.noteTaskId = null;
                    if (res.success) { this.flashMessage = 'Note saved.'; }
                    else { this.flashError = res.message || 'Failed to save note.'; }
                },

                async post(url, body) {
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify(body)
                        });
                        return await res.json();
                    } catch (e) {
                        return { success: false, message: 'Network error.' };
                    }
                }
            }
        }
    </script>
</x-app-layout>

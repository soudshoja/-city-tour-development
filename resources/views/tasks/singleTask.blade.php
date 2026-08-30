<!-- singleTask.blade.php -->
<div id="taskModal" onclick="closeModalbgC(event)"
    class="fixed inset-0 hidden  bg-opacity-50 flex items-center justify-center z-50">
    <div class="panel my-8 w-full max-w-xl overflow-hidden rounded-lg border-0 p-0">

        <div class="flex items-center justify-between bg-[#fbfbfb] px-5 py-3 dark:bg-[#121c2c]">
            <div class="flex items-center rounded-full p-1 font-semibold text-white pr-3 ">
                <x-application-logo
                    class="block h-8 w-8 rounded-full border-2 border-white/50 object-cover ltr:mr-1 rtl:ml-1" />

                <h3 class="text-lg font-bold px-2 text-black">Task Details</h3>
            </div>
            <button class="text-white-dark hover:text-dark" onclick="closeTaskModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                    class="h-6 w-6">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Task details content -->
        <div id="taskDetails" class="text-gray-700 dark:text-gray-300 p-5 overflow-y-auto" style="max-height: 60vh">
            <div class="flex justify-between items-center mb-2 font-semibold text-white-dark">
                <h6 class="text-left">Status</h6>
                <p id="taskStatus" class="text-right"></p>
            </div>
            {{-- W6.U 'Task list/detail' (w6-brief.md 'W6.U -- UI'): ticket_status / client_status
                 badges alongside the legacy status line above, populated by loadTaskActions()
                 below (this modal's own status line is fed by ShowTask()'s separate, already
                 broken `/task/{id}` fetch -- these two new fields come from the same working
                 `/tasks/show/{id}` endpoint the rest of W6.U's task-action UI already uses). --}}
            <div id="taskTicketStatusRow" class="hidden flex justify-between items-center mb-2 text-sm">
                <h6 class="text-left text-gray-500">Ticket status</h6>
                <p id="taskTicketStatus" class="text-right font-semibold"></p>
            </div>
            <div id="taskClientStatusRow" class="hidden flex justify-between items-center mb-2 text-sm">
                <h6 class="text-left text-gray-500">Client status</h6>
                <p id="taskClientStatus" class="text-right font-semibold"></p>
            </div>
            <div id="flightDetailsContainer" class="space-y-2 text-gray-700 dark:text-gray-300 p-5">
                <!-- Flight details will be populated here by JavaScript -->
            </div>

            {{-- W6.U 'Task actions' (w6-brief.md 'W6.U -- UI'): void / void-with-fee / reissue,
                 mirroring the pattern already shipped on tasks/partial/view-task-modal.blade.php
                 and tasks/detail.blade.php in this same wave, in this page's plain vanilla-JS
                 style (no Alpine root exists on this legacy modal). Hidden until
                 loadTaskActions() confirms can_void/can_reissue from the server; every button
                 still posts through TaskController -> TaskStatusService -> PostingSeam, which
                 re-authorizes and re-checks preconditions regardless of what this UI shows. --}}
            <div id="taskActionsCard" class="hidden mt-3 mb-3 border border-red-100 rounded-lg p-3">
                <div class="flex items-center justify-between mb-2">
                    <h6 class="font-semibold text-gray-700">Task actions</h6>
                    <span id="taskActionsLockedBadge" class="hidden text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-1 rounded-full">Invoice locked</span>
                </div>
                <div class="flex flex-wrap gap-2 mb-2">
                    <button type="button" id="taskActionVoidBtn" class="hidden px-3 py-1.5 bg-red-50 hover:bg-red-100 disabled:opacity-40 disabled:cursor-not-allowed text-red-700 font-semibold text-xs rounded-lg transition" onclick="taskActionOpenPanel('void')">Void</button>
                    <button type="button" id="taskActionVoidFeeBtn" class="hidden px-3 py-1.5 bg-red-50 hover:bg-red-100 disabled:opacity-40 disabled:cursor-not-allowed text-red-700 font-semibold text-xs rounded-lg transition" onclick="taskActionOpenPanel('void-fee')">Void with fee</button>
                    <button type="button" id="taskActionReissueBtn" class="hidden px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 disabled:opacity-40 disabled:cursor-not-allowed text-indigo-700 font-semibold text-xs rounded-lg transition" onclick="taskActionOpenPanel('reissue')">Reissue</button>
                </div>
                <div id="taskActionError" class="hidden mb-2 text-xs font-medium text-red-700 bg-red-50 rounded-lg px-2 py-1.5"></div>
                <div id="taskActionMessage" class="hidden mb-2 text-xs font-medium text-emerald-700 bg-emerald-50 rounded-lg px-2 py-1.5"></div>

                <div id="taskActionVoidPanel" class="hidden bg-gray-50 rounded-lg p-3 space-y-2">
                    <p class="text-xs text-gray-700">This reverses the ticket and the client's sale line. This cannot be undone (a correction afterwards would be a new reversal, not an edit).</p>
                    <button type="button" id="taskActionVoidConfirmBtn" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-semibold text-xs rounded-lg transition" onclick="taskActionSubmitVoid(false)">Confirm void</button>
                </div>

                <div id="taskActionVoidFeePanel" class="hidden bg-gray-50 rounded-lg p-3 space-y-2">
                    <label class="text-xs font-medium text-gray-700 block">Fee amount (KWD)</label>
                    <input type="number" id="taskActionFeeAmount" step="0.001" min="0" class="w-32 border border-gray-300 rounded-md px-2 py-1 text-xs">
                    <p id="taskActionFeeScheduleNote" class="text-xs text-gray-500"></p>
                    <button type="button" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-semibold text-xs rounded-lg transition" onclick="taskActionSubmitVoid(true)">Confirm void with fee</button>
                </div>

                <div id="taskActionReissuePanel" class="hidden bg-gray-50 rounded-lg p-3 space-y-2">
                    <label class="text-xs font-medium text-gray-700 block">Link the new (replacement) task</label>
                    <input type="text" id="taskActionReissueSearch" placeholder="Search by reference or client" class="w-full border border-gray-300 rounded-md px-2 py-1 text-xs" oninput="taskActionSearchReissue(this.value)">
                    <div id="taskActionReissueResults" class="hidden border border-gray-200 rounded-lg bg-white max-h-32 overflow-y-auto"></div>
                    <div id="taskActionReissuePreview" class="hidden bg-indigo-50 rounded-lg p-2 text-xs"></div>
                    <button type="button" id="taskActionReissueConfirmBtn" disabled class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-semibold text-xs rounded-lg transition" onclick="taskActionSubmitReissue()">Confirm reissue</button>
                </div>
            </div>

            <form action="" method="POST">
                @csrf
                @method('PUT')
                <x-input-label>Client Name</x-input-label>
                <select name="client_id" id="" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-30">
                    @foreach($clients as $client)
                    <option value="{{ $client->id }}" id="client_{{ $client->id }}">
                        {{ $client->full_name }}
                    </option>
                    @endforeach
                </select>
                <x-input-label>Agent Name</x-input-label>
                <select name="agent_id" id="" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-30">
                    @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" id="agent_{{ $agent->id }}">
                        {{ $agent->name }}
                    </option>
                    @endforeach
                </select>
                <x-input-label>Suppliers</x-input-label>
                <select name="supplier_id" id="" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring focus:ring-indigo-100 focus:border-indigo-30">
                    @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" id="supplier_{{ $supplier->id }}">
                        {{ $supplier->name }}
                    </option>
                    @endforeach
                </select>

                <x-input-label>Type</x-input-label>
                <x-text-input label="Type" id="taskType" class="w-full" />
                <x-input-label>Net Price</x-input-label>
                <x-text-input label="Net Price" id="taskPrice" class="w-full" />
                <x-input-label>Surcharge</x-input-label>
                <x-text-input label="Surcharge" id="taskSurcharge" class="w-full" />
                <x-input-label>Tax</x-input-label>
                <x-text-input label="Tax" id="taskTax" class="w-full" />
                <x-input-label>Total</x-input-label>
                <x-text-input label="Total" id="taskTotal" class="w-full" />
                <x-input-label>Reference</x-input-label>
                <x-text-input label="Reference" id="taskReference" class="w-full" disabled />
                <x-primary-button type="submit" class="mt-2">Update Task</x-primary-button>
            </form>
        </div>
    </div>
</div>

<script>
    // JavaScript to handle showing the task modal
    function ShowTask(taskId) {
        console.log('Opening task modal for task ID:', taskId); // Debugging line to ensure function is triggered

        // W6.U 'Task actions': independent of this function's own (pre-existing) fetch below --
        // loads can_void/can_reissue/is_locked/ticket_status/client_status from the working
        // `/tasks/show/{id}` endpoint and shows/hides the task-actions card accordingly.
        loadTaskActions(taskId);

        // Fetch task details using AJAX
        fetch(`/task/${taskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.task) {
                    console.log('Task data loaded:', data.task); // Debugging line to see the task data in console

                    // Populate modal with task details
                    const statusElement = document.getElementById('taskStatus');
                    statusElement.textContent = data.task.status;

                    // Apply conditional styling based on the task status
                    const statusLower = data.task.status.toLowerCase(); // Standardize status for comparison
                    statusElement.classList.remove('text-green-500', 'text-red-500', 'text-blue-500',
                        'text-gray-500'); // Reset previous classes

                    if (statusLower === 'confirmed') {
                        statusElement.classList.add('text-green-500'); // Green for confirmed
                    } else if (statusLower === 'pending') {
                        statusElement.classList.add('text-red-500'); // Red for pending
                    } else if (statusLower === 'completed') {
                        statusElement.classList.add('text-blue-500'); // Blue for completed
                    } else {
                        statusElement.classList.add('text-gray-500'); // Gray for unlisted statuses
                    }

                    form = document.querySelector('#taskModal form');
                    form.action = `/tasks-update/${taskId}`;
                    // Populate modal with task details
                    document.getElementById('taskStatus').textContent = data.task.status;
                    document.getElementById('taskType').value = data.task.type;
                    document.getElementById('taskPrice').value = data.task.price;
                    document.getElementById('taskSurcharge').value = data.task.surcharge;
                    document.getElementById('taskTax').value = data.task.tax;
                    document.getElementById('taskTotal').value = data.task.total;
                    document.getElementById('taskReference').value = data.task.reference;

                    if (data.task.client != null) {
                        clientOption = document.querySelector(`#client_${data.task.client.id}`);
                        clientOption.selected = true;
                    }

                    if (data.task.agent != null) {
                        agentOption = document.querySelector(`#agent_${data.task.agent.id}`);
                        agentOption.selected = true;
                    }

                    if(data.task.supplier != null) {
                        supplierOption = document.querySelector(`#supplier_${data.task.supplier.id}`);
                        supplierOption.selected = true;
                    }

                    // Display flight details
                    const flightDetailsContainer = document.getElementById('flightDetailsContainer');
                    flightDetailsContainer.innerHTML = ''; // Clear previous flight details
                    if (data.task.flightDetails && data.task.flightDetails.length > 0) {
                        data.task.flightDetails.forEach(detail => {
                            const detailItem = document.createElement('div');
                            detailItem.classList.add('mb-4', 'p-2', 'border', 'rounded');

                            // Populate each flight detail
                            detailItem.innerHTML = `
                            <p><strong>Farebase:</strong> ${detail.farebase}</p>
                            <p><strong>Departure Time:</strong> ${detail.departure_time}</p>
                            <p><strong>Departure From:</strong> ${detail.departure_from} (${detail.airport_from})</p>
                            <p><strong>Arrival Time:</strong> ${detail.arrival_time}</p>
                            <p><strong>Arrival To:</strong> ${detail.arrive_to} (${detail.airport_to})</p>
                            <p><strong>Flight Number:</strong> ${detail.flight_number}</p>
                            <p><strong>Class Type:</strong> ${detail.class_type}</p>
                            <p><strong>Baggage Allowed:</strong> ${detail.baggage_allowed}</p>
                            <p><strong>Equipment:</strong> ${detail.equipment}</p>
                            <p><strong>Seat No:</strong> ${detail.seat_no}</p>
                        `;
                            flightDetailsContainer.appendChild(detailItem);
                        });
                    } else {
                        flightDetailsContainer.textContent = 'No flight details available.';
                    }

                    // Show the modal
                    document.getElementById('taskModal').classList.remove('hidden');
                    console.log('Modal is now visible'); // Debugging line to confirm the modal is shown
                } else {
                    alert('Task details could not be loaded.');
                }
            })
            .catch(error => {
                console.error('Error fetching task details:', error);
            });
    }


    // Close the modal
    function closeTaskModal() {
        document.getElementById('taskModal').classList.add('hidden');
        console.log('Modal has been closed'); // Debugging line to confirm the modal is closed
    }

    // Close the modal when clicking outside the modal content (on the background)
    function closeModalOnBgClick(event) {
        const modal = document.getElementById('taskModal');
        const modalContent = document.querySelector('#taskModal > div');

        // If the clicked target is the modal itself (background), close the modal
        if (event.target === modal) {
            closeTaskModal();
        }
    }

    // Add event listener to close modal when clicking on the background
    document.getElementById('taskModal').addEventListener('click', closeModalOnBgClick);

    // Optional: Close the modal by pressing the Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeTaskModal();
        }
    });

    // W6.U 'Task actions' (w6-brief.md 'W6.U -- UI'): void / void-with-fee / reissue on this
    // legacy voucher task modal, plain vanilla JS to match this file's own style (no Alpine root
    // exists here). Each action is a thin fetch() against
    // TaskController -> TaskStatusService -> PostingSeam; the server routes themselves
    // re-authorize and re-check preconditions regardless of what this UI shows/hides.
    let taskActionCurrentTaskId = null;
    let taskActionReissueSelected = null;

    function taskActionEl(id) {
        return document.getElementById(id);
    }

    function taskActionShow(id, show) {
        const el = taskActionEl(id);
        if (!el) return;
        el.classList.toggle('hidden', !show);
    }

    function taskActionResetPanels() {
        taskActionShow('taskActionVoidPanel', false);
        taskActionShow('taskActionVoidFeePanel', false);
        taskActionShow('taskActionReissuePanel', false);
        taskActionShow('taskActionError', false);
        taskActionShow('taskActionMessage', false);
    }

    async function loadTaskActions(taskId) {
        taskActionCurrentTaskId = taskId;
        taskActionReissueSelected = null;
        taskActionShow('taskActionsCard', false);
        taskActionResetPanels();

        try {
            const res = await fetch(`/tasks/show/${taskId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();

            if (data.ticket_status) {
                taskActionEl('taskTicketStatus').textContent = data.ticket_status;
                taskActionShow('taskTicketStatusRow', true);
            } else {
                taskActionShow('taskTicketStatusRow', false);
            }
            if (data.client_status) {
                taskActionEl('taskClientStatus').textContent = data.client_status;
                taskActionShow('taskClientStatusRow', true);
            } else {
                taskActionShow('taskClientStatusRow', false);
            }

            const canVoid = !!data.can_void;
            const canReissue = !!data.can_reissue;
            const isLocked = !!data.is_locked;

            if (!canVoid && !canReissue) return;

            taskActionShow('taskActionsCard', true);
            taskActionShow('taskActionsLockedBadge', isLocked);

            ['taskActionVoidBtn', 'taskActionVoidFeeBtn'].forEach((id) => {
                taskActionShow(id, canVoid);
                if (canVoid) taskActionEl(id).disabled = isLocked;
            });
            taskActionShow('taskActionReissueBtn', canReissue);
            if (canReissue) taskActionEl('taskActionReissueBtn').disabled = isLocked;
        } catch (e) {
            console.error('Error loading task actions:', e);
        }
    }

    function taskActionOpenPanel(panel) {
        taskActionResetPanels();
        const panelId = panel === 'void' ? 'taskActionVoidPanel'
            : panel === 'void-fee' ? 'taskActionVoidFeePanel'
            : 'taskActionReissuePanel';
        taskActionShow(panelId, true);

        if (panel === 'void-fee') {
            taskActionLoadFeePreview();
        }
    }

    async function taskActionLoadFeePreview() {
        if (!taskActionCurrentTaskId) return;
        try {
            const res = await fetch(`/tasks/${taskActionCurrentTaskId}/void-fee-preview`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                taskActionEl('taskActionFeeAmount').value = data.schedule_fee ?? '';
                let note = 'Schedule fee: ' + data.schedule_fee + ' KWD.';
                if (data.override_policy === 'needs_approval') {
                    note += ' A different amount will require approval before it posts.';
                }
                taskActionEl('taskActionFeeScheduleNote').textContent = note;
            }
        } catch (e) {
            console.error('Error loading fee preview:', e);
        }
    }

    async function taskActionSubmitVoid(withFee) {
        if (!taskActionCurrentTaskId) return;
        taskActionShow('taskActionError', false);
        taskActionShow('taskActionMessage', false);
        try {
            const body = {};
            if (withFee) body.fee = parseFloat(taskActionEl('taskActionFeeAmount').value) || 0;

            const res = await fetch(`/tasks/${taskActionCurrentTaskId}/void`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(body)
            });
            const data = await res.json();

            if (res.status === 202 && data.pending_approval) {
                taskActionEl('taskActionMessage').textContent = data.message + ' (request #' + data.pending_action_id + ')';
                taskActionShow('taskActionMessage', true);
                taskActionResetPanels();
            } else if (data.success) {
                taskActionEl('taskActionMessage').textContent = 'Task voided.';
                taskActionShow('taskActionMessage', true);
                taskActionResetPanels();
                loadTaskActions(taskActionCurrentTaskId);
            } else {
                taskActionEl('taskActionError').textContent = data.message || 'Failed to void task.';
                taskActionShow('taskActionError', true);
            }
        } catch (e) {
            taskActionEl('taskActionError').textContent = 'Network error while voiding task.';
            taskActionShow('taskActionError', true);
        }
    }

    let taskActionReissueDebounce = null;

    function taskActionSearchReissue(term) {
        clearTimeout(taskActionReissueDebounce);
        taskActionReissueDebounce = setTimeout(() => taskActionDoSearchReissue(term), 300);
    }

    async function taskActionDoSearchReissue(term) {
        if (!taskActionCurrentTaskId || !term || term.length < 2) {
            taskActionShow('taskActionReissueResults', false);
            return;
        }
        try {
            const res = await fetch(`/tasks/search-original-tasks?id=${taskActionCurrentTaskId}&search=${encodeURIComponent(term)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const results = await res.json();
            const container = taskActionEl('taskActionReissueResults');
            container.innerHTML = '';
            if (Array.isArray(results) && results.length > 0) {
                results.forEach((result) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full text-left px-2 py-1 text-xs hover:bg-indigo-50';
                    btn.textContent = `${result.reference} — ${result.client_name ?? ''}`;
                    btn.onclick = () => taskActionSelectReissueTarget(result);
                    container.appendChild(btn);
                });
                taskActionShow('taskActionReissueResults', true);
            } else {
                taskActionShow('taskActionReissueResults', false);
            }
        } catch (e) {
            console.error('Error searching reissue targets:', e);
        }
    }

    async function taskActionSelectReissueTarget(task) {
        taskActionReissueSelected = task;
        taskActionShow('taskActionReissueResults', false);
        taskActionEl('taskActionReissueSearch').value = task.reference;
        taskActionEl('taskActionReissueConfirmBtn').disabled = false;

        try {
            const res = await fetch(`/tasks/${taskActionCurrentTaskId}/reissue-preview?new_task_id=${task.id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                const type = data.fare_difference?.type === 'dbn' ? 'Client owes (DBN)'
                    : data.fare_difference?.type === 'crn' ? 'Client credited (CRN)'
                    : 'No fare difference';
                const preview = taskActionEl('taskActionReissuePreview');
                preview.innerHTML = `
                    <div class="flex justify-between"><span>Old sell</span><span>${data.old_sell}</span></div>
                    <div class="flex justify-between"><span>New sell</span><span>${data.new_sell}</span></div>
                    <div class="flex justify-between font-bold border-t border-indigo-200 mt-1 pt-1"><span>${type}</span><span>${data.fare_difference?.amount ?? ''}</span></div>
                `;
                taskActionShow('taskActionReissuePreview', true);
            }
        } catch (e) {
            console.error('Error loading reissue preview:', e);
        }
    }

    async function taskActionSubmitReissue() {
        if (!taskActionCurrentTaskId || !taskActionReissueSelected) {
            taskActionEl('taskActionError').textContent = 'Pick a task to reissue into first.';
            taskActionShow('taskActionError', true);
            return;
        }
        taskActionShow('taskActionError', false);
        taskActionShow('taskActionMessage', false);
        try {
            const res = await fetch(`/tasks/${taskActionCurrentTaskId}/reissue`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ new_task_id: taskActionReissueSelected.id })
            });
            const data = await res.json();

            if (res.status === 202 && data.pending_approval) {
                taskActionEl('taskActionMessage').textContent = data.message + ' (request #' + data.pending_action_id + ')';
                taskActionShow('taskActionMessage', true);
                taskActionResetPanels();
            } else if (data.success) {
                taskActionEl('taskActionMessage').textContent = 'Task reissued.';
                taskActionShow('taskActionMessage', true);
                taskActionResetPanels();
                loadTaskActions(taskActionCurrentTaskId);
            } else {
                taskActionEl('taskActionError').textContent = data.message || 'Failed to reissue task.';
                taskActionShow('taskActionError', true);
            }
        } catch (e) {
            taskActionEl('taskActionError').textContent = 'Network error while reissuing task.';
            taskActionShow('taskActionError', true);
        }
    }
</script>

<style>
    /* CSS for the modal */
    #taskModal .dark\:bg-gray-800 {
        background-color: #F3F4F6;
    }
</style>
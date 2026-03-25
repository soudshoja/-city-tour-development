<x-app-layout>
    @push('styles')
        @vite(['resources/css/lock-management/index.css'])
    @endpush

    <div class="lock-header">
        <div class="lock-header-left">
            <h2 class="lock-title">Lock Management</h2>
            <div data-tooltip="Financial record locking" class="lock-title-icon DarkBGcolor">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="white">
                    <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3zm0 10c1.1 0 2 .9 2 2s-.9 2-2 2-2-.9-2-2 .9-2 2-2z"/>
                </svg>
            </div>
        </div>
        <div class="lock-header-right">
            <div data-tooltip-left="Reload" class="lock-reload-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                    <path fill="currentColor" d="M12.079 2.25c-4.794 0-8.734 3.663-9.118 8.333H2a.75.75 0 0 0-.528 1.283l1.68 1.666a.75.75 0 0 0 1.056 0l1.68-1.666a.75.75 0 0 0-.528-1.283h-.893c.38-3.831 3.638-6.833 7.612-6.833a7.66 7.66 0 0 1 6.537 3.643a.75.75 0 1 0 1.277-.786A9.16 9.16 0 0 0 12.08 2.25" />
                    <path fill="currentColor" d="M20.841 10.467a.75.75 0 0 0-1.054 0L18.1 12.133a.75.75 0 0 0 .527 1.284h.899c-.381 3.83-3.651 6.833-7.644 6.833a7.7 7.7 0 0 1-6.565-3.644a.75.75 0 1 0-1.276.788a9.2 9.2 0 0 0 7.84 4.356c4.809 0 8.766-3.66 9.151-8.333H22a.75.75 0 0 0 .527-1.284z" opacity=".5" />
                </svg>
            </div>
            <div x-data="{ showBulkLock: false }">
                <button @click="showBulkLock = true" data-tooltip-left="Bulk lock by date" class="lock-bulk-btn btn-success">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                        <path fill="#fff" d="M16 8h-2v3h-3v2h3v3h2v-3h3v-2h-3M2 12c0-2.79 1.64-5.2 4-6.32V3.5C2.5 4.76 0 8.09 0 12s2.5 7.24 6 8.5v-2.18C3.64 17.2 2 14.79 2 12m13-9c-4.96 0-9 4.04-9 9s4.04 9 9 9s9-4.04 9-9s-4.04-9-9-9m0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7s7 3.14 7 7s-3.14 7-7 7" />
                    </svg>
                </button>

                {{-- Bulk Lock Modal --}}
                <div x-cloak x-show="showBulkLock" class="lock-modal-overlay">
                    <div class="lock-modal-backdrop" @click="showBulkLock = false"></div>
                    <div class="lock-modal-container">
                        <div class="lock-modal">
                            <form action="{{ route('lock-management.lock-by-period') }}" method="POST" x-data="{ hasInvoices: true }"
                                onsubmit="return confirm('Are you sure? This will lock ALL matching records in the selected date range.')">
                                @csrf

                                <div class="lock-modal-header">
                                    <div>
                                        <h3>Bulk Lock by Date</h3>
                                        <p class="lock-modal-subtitle">Lock all records within the selected date range</p>
                                    </div>
                                    <button type="button" @click="showBulkLock = false" class="lock-modal-close">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                <div class="lock-modal-body">
                                    <div class="lock-form-section">
                                        <h4 class="lock-section-title blue">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            Lock Period
                                        </h4>
                                        <div class="lock-form-grid">
                                            <div class="lock-form-group">
                                                <label class="lock-form-label">From date <span class="required">*</span></label>
                                                <input type="date" name="lock_from_date" required class="lock-form-input">
                                            </div>
                                            <div class="lock-form-group">
                                                <label class="lock-form-label">To date <span class="required">*</span></label>
                                                <input type="date" name="lock_to_date" required
                                                    value="{{ now()->subMonth()->endOfMonth()->format('Y-m-d') }}"
                                                    class="lock-form-input">
                                            </div>
                                        </div>
                                        <p class="lock-form-hint">Tip: To close last month, set from the start to the end of that month.</p>
                                    </div>

                                    <div class="lock-form-section">
                                        <h4 class="lock-section-title green">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            Record Types
                                        </h4>
                                        <label class="lock-form-label">Record types to lock <span class="required">*</span></label>
                                        <div class="lock-checkbox-grid">
                                            @foreach($recordTypes as $key => $config)
                                                <label class="lock-checkbox-card">
                                                    <input type="checkbox" name="record_types[]" value="{{ $key }}" checked
                                                        class="lock-checkbox"
                                                        @if($key === 'invoices') x-model="hasInvoices" @endif>
                                                    <span>{{ $config['label'] }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div x-show="hasInvoices" x-cloak class="lock-form-section">
                                        <h4 class="lock-section-title purple">
                                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                                            </svg>
                                            Status Filter
                                        </h4>
                                        <label class="lock-form-label">Invoice status filter:</label>
                                        <div class="lock-status-filters">
                                            @foreach(['paid', 'unpaid', 'partial'] as $status)
                                                <label class="lock-status-item">
                                                    <input type="checkbox" name="lock_status[]" value="{{ $status }}" {{ $status === 'paid' ? 'checked' : '' }}
                                                        class="lock-checkbox">
                                                    <span>{{ ucfirst($status) }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="lock-modal-footer">
                                    <button type="button" @click="showBulkLock = false" class="lock-btn secondary">Cancel</button>
                                    <button type="submit" class="lock-btn danger">
                                        <svg fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                                        </svg>
                                        Lock Records
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="lock-stats-grid lock-stats-cols-{{ count($stats) > 4 ? 5 : count($stats) }}">
        @foreach($stats as $key => $stat)
            <div class="lock-stat-card lock-stat-{{ $stat['color'] }}">
                <div class="lock-stat-header">
                    <span class="lock-stat-label">{{ $stat['label'] }}</span>
                    @if($stat['percentage'] == 100)
                        <span class="lock-badge-closed">✓ Closed</span>
                    @endif
                </div>
                <div class="lock-stat-total">{{ number_format($stat['total']) }}</div>
                <div class="lock-stat-counts">
                    <span class="lock-stat-locked">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $stat['locked'] }}
                    </span>
                    <span class="lock-stat-unlocked">
                        <svg fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 2a5 5 0 00-5 5v2a2 2 0 00-2 2v5a2 2 0 002 2h10a2 2 0 002-2v-5a2 2 0 00-2-2H7V7a3 3 0 015.905-.75 1 1 0 001.937-.5A5.002 5.002 0 0010 2z"/>
                        </svg>
                        {{ $stat['unlocked'] }}
                    </span>
                </div>
                <div class="lock-progress-bar">
                    <div class="lock-progress-fill lock-progress-{{ $stat['color'] }}" style="width: {{ $stat['percentage'] }}%"></div>
                </div>
                <div class="lock-stat-percentage">{{ $stat['percentage'] }}% locked</div>
            </div>
        @endforeach
    </div>

    {{-- Monthly Panel --}}
    <div class="panel rounded-lg">
        <div class="lock-panel-header">
            <h2>Monthly Period Closing</h2>
            <p>Lock or unlock records per section for each month.</p>
        </div>

        <div class="lock-months-list">
            @forelse($paginatedMonths as $monthKey => $month)
                @php
                    $monthDate = \Carbon\Carbon::parse($month['month'] . '-01');
                    $isFullyLocked = $month['unlocked'] == 0 && $month['total'] > 0;
                    $isPartiallyLocked = $month['locked'] > 0 && $month['unlocked'] > 0;
                    $percentage = $month['total'] > 0 ? round(($month['locked'] / $month['total']) * 100) : 0;
                @endphp
                <div x-data="{ expanded: false }"
                     class="lock-month-card {{ $isFullyLocked ? 'lock-month-fully' : ($isPartiallyLocked ? 'lock-month-partial' : '') }}">

                    <div class="lock-month-header" @click="expanded = !expanded">
                        <div class="lock-month-info">
                            <div class="lock-month-icon {{ $isFullyLocked ? 'fully' : ($isPartiallyLocked ? 'partial' : 'default') }}">
                                <span class="lock-month-abbr">{{ $monthDate->format('M') }}</span>
                                <span class="lock-month-year">{{ $monthDate->format('Y') }}</span>
                            </div>
                            <div class="lock-month-details">
                                <h4>{{ $monthDate->format('F Y') }}</h4>
                                <div class="lock-month-stats">
                                    <span>{{ $month['total'] }} total</span>
                                    @if($month['locked'] > 0)
                                        <span class="lock-count-locked">{{ $month['locked'] }} locked</span>
                                    @endif
                                    @if($month['unlocked'] > 0)
                                        <span class="lock-count-unlocked">{{ $month['unlocked'] }} unlocked</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="lock-month-actions">
                            <div class="lock-month-progress">
                                <div class="lock-month-progress-labels">
                                    <span>Locked</span>
                                    <span>{{ $percentage }}%</span>
                                </div>
                                <div class="lock-progress-bar">
                                    <div class="lock-progress-fill {{ $isFullyLocked ? 'lock-progress-green' : ($isPartiallyLocked ? 'lock-progress-amber' : 'lock-progress-gray') }}" style="width: {{ $percentage }}%"></div>
                                </div>
                            </div>

                            @if($isFullyLocked)
                                <span class="lock-badge-closed">✓ Closed</span>
                            @endif

                            <svg xmlns="http://www.w3.org/2000/svg" class="lock-chevron" :class="expanded ? 'lock-chevron-open' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <div x-show="expanded" x-cloak x-collapse class="lock-month-body">
                        @foreach($month['types'] as $typeKey => $typeData)
                            @php
                                $typeFullyLocked = $typeData['unlocked'] == 0 && $typeData['total'] > 0;
                                $typePercentage = $typeData['total'] > 0 ? round(($typeData['locked'] / $typeData['total']) * 100) : 0;
                            @endphp
                            <div class="lock-type-row {{ !$loop->last ? 'lock-type-border' : '' }}" x-data="{ showUnlock: false }">
                                <div class="lock-type-content">
                                    <div class="lock-type-info">
                                        <span class="lock-type-badge lock-type-{{ $typeData['color'] }}">{{ $typeData['label'] }}</span>
                                        <div class="lock-type-stats">
                                            <span>{{ $typeData['total'] }} total</span>
                                            @if($typeData['locked'] > 0)
                                                <span class="lock-count-locked">{{ $typeData['locked'] }} locked</span>
                                            @endif
                                            @if($typeData['unlocked'] > 0)
                                                <span class="lock-count-open">{{ $typeData['unlocked'] }} open</span>
                                            @endif
                                        </div>
                                        @if($typeFullyLocked)
                                            <span class="lock-type-check">✓</span>
                                        @endif
                                    </div>

                                    <div class="lock-type-actions">
                                        @if($typeData['unlocked'] > 0)
                                            <form action="{{ route('lock-management.lock-by-month') }}" method="POST"
                                                onsubmit="return confirm('Lock {{ $typeData['unlocked'] }} {{ $typeData['label'] }} for {{ $monthDate->format('F Y') }}?')">
                                                @csrf
                                                <input type="hidden" name="month" value="{{ $month['month'] }}">
                                                <input type="hidden" name="record_type" value="{{ $typeKey }}">
                                                <button type="submit" class="lock-btn danger small">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                                        <path d="M12 2C9.24 2 7 4.24 7 7v3H6c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2v-8c0-1.1-.9-2-2-2h-1V7c0-2.76-2.24-5-5-5zm0 2c1.66 0 3 1.34 3 3v3H9V7c0-1.66 1.34-3 3-3z"/>
                                                    </svg>
                                                    Lock ({{ $typeData['unlocked'] }})
                                                </button>
                                            </form>
                                        @endif

                                        @if($typeData['locked'] > 0)
                                            <button @click="showUnlock = true" class="lock-btn warning small">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                                </svg>
                                                Unlock ({{ $typeData['locked'] }})
                                            </button>

                                            {{-- Unlock Modal --}}
                                            <div x-cloak x-show="showUnlock" class="lock-modal-overlay">
                                                <div class="lock-modal-backdrop" @click="showUnlock = false"></div>
                                                <div class="lock-modal-container">
                                                    <div class="lock-modal lock-modal-sm">
                                                        <div class="lock-modal-header">
                                                            <div>
                                                                <h3>Unlock {{ $typeData['label'] }} — {{ $monthDate->format('F Y') }}</h3>
                                                                <p class="lock-modal-subtitle">This will unlock {{ $typeData['locked'] }} {{ strtolower($typeData['label']) }}.</p>
                                                            </div>
                                                            <button type="button" @click="showUnlock = false" class="lock-modal-close">
                                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <form action="{{ route('lock-management.unlock-by-month') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="month" value="{{ $month['month'] }}">
                                                            <input type="hidden" name="record_type" value="{{ $typeKey }}">
                                                            <div class="lock-modal-body">
                                                                <label class="lock-form-label">Reason for unlocking <span class="required">*</span></label>
                                                                <textarea name="reason" rows="3" required placeholder="e.g., Need to correct entries for reconciliation..."
                                                                    class="lock-form-textarea"></textarea>
                                                            </div>
                                                            <div class="lock-modal-footer">
                                                                <button type="button" @click="showUnlock = false" class="lock-btn secondary">Cancel</button>
                                                                <button type="submit" class="lock-btn warning">Unlock</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="lock-progress-bar lock-progress-thin">
                                    <div class="lock-progress-fill {{ $typeFullyLocked ? 'lock-progress-green' : 'lock-progress-' . $typeData['color'] }}" style="width: {{ $typePercentage }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="lock-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <p class="lock-empty-title">No records found</p>
                    <p>Financial records will appear here once created.</p>
                </div>
            @endforelse
        </div>

        <x-pagination :data="$paginatedMonths" />
    </div>
</x-app-layout>

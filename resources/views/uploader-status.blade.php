@php
    $hosts = \Illuminate\Support\Facades\DB::table('uploader_heartbeats')->orderBy('host_id')->get();

    function _sec_ago($ts) {
        if (!$ts) return 99999999;
        return abs(\Carbon\Carbon::now()->diffInSeconds(\Carbon\Carbon::parse($ts), false));
    }
    function _status_color($sec) {
        if ($sec < 300)   return ['green',  'ONLINE'];
        if ($sec < 1800)  return ['yellow', 'STALE'];
        return                    ['red',    'OFFLINE'];
    }
    function _human_ago($sec) {
        if ($sec < 60)   return $sec . 's ago';
        if ($sec < 3600) return floor($sec/60) . 'm ago';
        if ($sec < 86400) return floor($sec/3600) . 'h ' . floor(($sec%3600)/60) . 'm ago';
        return floor($sec/86400) . 'd ago';
    }

    // ── Today's ingestion breakdown (cheap glob counts — no file reads) ──
    $base = '/home/citycomm/AIR';
    $today = date('dmY');
    $loadedToday = is_dir("$base/LOADED/$today") ? count(glob("$base/LOADED/$today/*.AIR") ?: []) : 0;
    $heldPending = is_dir("$base/NOT LOADED/unregistered_agent") ? count(glob("$base/NOT LOADED/unregistered_agent/*.AIR") ?: []) : 0;
    $errPending  = count(glob("$base/NOT LOADED/*.AIR") ?: []);   // root-level only (glob is non-recursive)
    $totalState  = max(1, $loadedToday + $heldPending + $errPending);
    $pLoad = round($loadedToday / $totalState * 100);
    $pHeld = round($heldPending / $totalState * 100);
    $pErr  = max(0, 100 - $pLoad - $pHeld);
@endphp

<div class="mb-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
            </svg>
            AIR File Uploader Status
            
        </h3>
        <div class="flex items-center gap-3">
            <a href="{{ url('air/uploader/logs') }}" target="_blank" class="text-xs text-blue-600 hover:underline">review logs</a>
            <button onclick="window.location.reload()" class="text-xs text-blue-600 hover:underline">refresh</button>
        </div>
    </div>

    {{-- ── Today's ingestion summary ── --}}
    <div class="px-4 pt-3">
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
            <span>Today &middot; {{ date('d M Y') }}</span>
            <span>{{ number_format($loadedToday + $heldPending + $errPending) }} files in flow</span>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center mb-2">
            <div>
                <div class="text-xl font-semibold text-green-600 dark:text-green-400">{{ number_format($loadedToday) }}</div>
                <div class="text-[10px] uppercase tracking-wide text-gray-500">✓ Loaded</div>
            </div>
            <div>
                <div class="text-xl font-semibold text-amber-500 dark:text-amber-400">{{ number_format($heldPending) }}</div>
                <div class="text-[10px] uppercase tracking-wide text-gray-500">⏸ Held</div>
            </div>
            <div>
                <div class="text-xl font-semibold text-red-500 dark:text-red-400">{{ number_format($errPending) }}</div>
                <div class="text-[10px] uppercase tracking-wide text-gray-500">⚠ Errors</div>
            </div>
        </div>
        <div class="flex h-2 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-700 mb-1">
            <div class="bg-green-500" style="width: {{ $pLoad }}%"></div>
            <div class="bg-amber-400"  style="width: {{ $pHeld }}%"></div>
            <div class="bg-red-500"    style="width: {{ $pErr }}%"></div>
        </div>
        <div class="text-[11px] text-gray-400 dark:text-gray-500">
            <span class="text-green-600 dark:text-green-400">Loaded</span> = today &middot;
            <span class="text-amber-500">Held</span> = withheld by agent gate (unregistered / orphan modification) — recoverable, not failures &middot;
            <span class="text-red-500">Errors</span> = genuine processing failures
        </div>
    </div>

    {{-- ── Per-host heartbeats ── --}}
    <div class="p-3">
        @if($hosts->isEmpty())
            <div class="text-sm text-gray-500 italic">No uploaders registered yet.</div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                @foreach($hosts as $h)
                    @php
                        $sec = _sec_ago($h->last_seen_at);
                        [$color, $label] = _status_color($sec);
                        $dotClass = ['green'=>'bg-green-500','yellow'=>'bg-yellow-500 animate-pulse','red'=>'bg-red-500 animate-pulse'][$color];
                        $textClass = ['green'=>'text-green-700 dark:text-green-300','yellow'=>'text-yellow-700 dark:text-yellow-300','red'=>'text-red-700 dark:text-red-300'][$color];
                        $isDead = $sec > 172800; // >2 days
                        // Treat the "unregistered_agent" gate message as a neutral hold, not a red error
                        $errIsHold = $h->last_error && (stripos($h->last_error, 'http=200') !== false || stripos($h->last_error, 'unregistered') !== false);
                    @endphp
                    <div class="rounded border border-gray-200 dark:border-gray-700 p-3 {{ $isDead ? 'opacity-60' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full {{ $dotClass }}"></span>
                                <span class="text-sm font-semibold {{ $textClass }}">{{ $label }}</span>
                                <span class="text-xs text-gray-400">— {{ $h->host_id }}</span>
                            </div>
                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">v{{ $h->version ?? '?' }}</span>
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400 space-y-0.5">
                            <div>Last seen: <span class="font-medium">{{ _human_ago($sec) }}</span>
                                <span class="text-gray-400">({{ $h->last_seen_at }})</span></div>
                            <div>Files today: <span class="font-medium">{{ $h->files_today }}</span>
                                · Total: <span class="font-medium">{{ $h->files_total }}</span>
                                · <span class="text-gray-400">{{ $h->status ?? 'running' }}</span></div>
                            @if($h->last_error)
                                @if($errIsHold)
                                    <div class="text-amber-600 dark:text-amber-400 truncate" title="{{ $h->last_error }}">
                                        ⏸ Last hold: {{ $h->last_error }} <span class="text-gray-400">(normal — gate rejection)</span>
                                    </div>
                                @else
                                    <div class="text-red-600 dark:text-red-400 truncate" title="{{ $h->last_error }}">⚠ Last error: {{ $h->last_error }}</div>
                                @endif
                            @else
                                <div class="text-green-600 dark:text-green-400">✓ No errors</div>
                            @endif
                            @if($isDead)
                                <form method="POST" action="{{ url('air/uploader/remove-host') }}"
                                      onsubmit="return confirm('Remove dead host {{ $h->host_id }} from the dashboard?')"
                                      class="mt-1">
                                    @csrf
                                    <input type="hidden" name="host_id" value="{{ $h->host_id }}">
                                    <button type="submit" class="text-xs text-red-600 hover:underline">
                                        ✕ remove dead host
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
    (function() {
        let interval = null;
        function start() { if (!interval) interval = setInterval(() => { if (!document.hidden) location.reload(); }, 30000); }
        function stop() { if (interval) { clearInterval(interval); interval = null; } }
        document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
        start();
    })();
</script>

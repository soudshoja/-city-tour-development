<div x-data="aiConfigTab()" x-init="init()">
    <div x-show="loading" class="main-set-loading-container">
        <span class="main-set-loading-text">Loading AI configuration...</span>
    </div>

    <div x-show="!loading" x-cloak>
        <div class="main-set-header">
            <div class="main-set-header-content">
                <h3>AI Configuration</h3>
                <p>Models, API keys and fallback used for document reading (passports, PDFs) and AI features. Values saved here override the server defaults; leave a field blank and save to revert it to the server default.</p>
            </div>
        </div>

        {{-- Resayil AI models --}}
        <div class="noti-panel" style="margin-bottom:16px;">
            <div class="noti-panel-title">Resayil AI Models</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="noti-field">
                    <label>Passport / vision model <span class="text-xs text-gray-400">(WhatsApp client creation)</span></label>
                    <select x-model="values.resayil_model_passport" class="noti-input">
                        <template x-for="m in modelOptions('resayil_model_passport')" :key="m">
                            <option :value="m" x-text="m" :selected="m === values.resayil_model_passport"></option>
                        </template>
                    </select>
                </div>
                <div class="noti-field">
                    <label>Text model <span class="text-xs text-gray-400">(chat / text tasks)</span></label>
                    <select x-model="values.resayil_model_text" class="noti-input">
                        <template x-for="m in modelOptions('resayil_model_text')" :key="m">
                            <option :value="m" x-text="m" :selected="m === values.resayil_model_text"></option>
                        </template>
                    </select>
                </div>
                <div class="noti-field">
                    <label>Timeout (seconds)</label>
                    <input type="number" min="10" max="600" x-model="values.resayil_timeout" class="noti-input">
                </div>
                <div class="noti-field">
                    <label>Max PDF pages sent to AI</label>
                    <input type="number" min="1" max="30" x-model="values.resayil_max_pdf_pages" class="noti-input">
                </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                <button @click="runTest('text')" :disabled="testing" class="main-set-btn main-set-btn-primary">Test text model</button>
                <button @click="runTest('vision')" :disabled="testing" class="main-set-btn main-set-btn-primary">Test passport model</button>
                <span x-show="testResult" x-text="testResult" class="text-sm self-center" :class="testOk ? 'text-emerald-600 font-semibold' : 'text-red-600'"></span>
            </div>
        </div>

        {{-- API keys --}}
        <div class="noti-panel" style="margin-bottom:16px;">
            <div class="noti-panel-title">API Keys</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="noti-field">
                    <label>Resayil AI key <span class="text-xs text-gray-400" x-text="values.resayil_key ? '(current: ' + values.resayil_key + ')' : '(not set)'"></span></label>
                    <input type="password" x-model="secrets.resayil_key" placeholder="Leave blank to keep current key" class="noti-input" autocomplete="new-password">
                </div>
                <div class="noti-field">
                    <label>OpenAI key <span class="text-xs text-gray-400" x-text="values.openai_key ? '(current: ' + values.openai_key + ')' : '(not set)'"></span></label>
                    <input type="password" x-model="secrets.openai_key" placeholder="Leave blank to keep current key" class="noti-input" autocomplete="new-password">
                    <p class="text-xs text-gray-500 italic mt-1">A valid OpenAI key becomes the last-resort fallback when Resayil AI is down.</p>
                </div>
                <div class="noti-field">
                    <label>OpenAI model</label>
                    <input type="text" x-model="values.openai_model" class="noti-input" placeholder="e.g. gpt-4.1">
                </div>
            </div>
            <div class="flex flex-wrap gap-2 mt-3">
                <button @click="runTest('openai')" :disabled="testing" class="main-set-btn main-set-btn-primary">Test OpenAI key</button>
            </div>
        </div>

        {{-- Fallback + alerts --}}
        <div class="noti-panel" style="margin-bottom:16px;">
            <div class="noti-panel-title">Fallback &amp; Alerts</div>
            <div class="noti-field" style="margin-bottom:12px;">
                <label>Fallback chain — tried top to bottom until one answers (used for PDF/text AI tasks; passport reading uses the Passport model on each Resayil step)</label>
                <div class="space-y-2 mt-2">
                    <template x-for="(step, i) in values.chain" :key="i">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-gray-400 w-5" x-text="(i + 1) + '.'"></span>
                            <select x-model="step.provider" class="noti-input" style="max-width:150px;">
                                <option value="resayil">Resayil AI</option>
                                <option value="openai">OpenAI</option>
                            </select>
                            <template x-if="step.provider === 'resayil'">
                                <select x-model="step.model" class="noti-input" style="max-width:280px;">
                                    <template x-for="m in modelOptionsFor(step.model)" :key="m">
                                        <option :value="m" x-text="m" :selected="m === step.model"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="step.provider === 'openai'">
                                <span class="text-xs text-gray-400 italic">uses the OpenAI model &amp; key above</span>
                            </template>
                            <button type="button" @click="moveChain(i, -1)" :disabled="i === 0" class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-30" title="Move up">↑</button>
                            <button type="button" @click="moveChain(i, 1)" :disabled="i === values.chain.length - 1" class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-30" title="Move down">↓</button>
                            <button type="button" @click="values.chain.splice(i, 1)" class="px-2 py-1 text-xs rounded border border-red-300 text-red-600" title="Remove step">✕</button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addChainStep()" :disabled="values.chain && values.chain.length >= 6"
                    class="mt-2 px-3 py-1.5 text-xs rounded bg-gray-700 text-white hover:bg-gray-800 disabled:opacity-40">
                    + Add fallback step
                </button>
                <p class="text-xs text-gray-500 italic mt-1">Up to 6 steps. Removing all steps and saving reverts to the server default chain.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="noti-field">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" x-model="values.fallback_enabled" class="main-set-checkbox">
                        <span>Fallback chain enabled</span>
                    </label>
                </div>
                <div class="noti-field">
                    <label>Retries per provider</label>
                    <input type="number" min="0" max="5" x-model="values.retries" class="noti-input">
                </div>
                <div class="noti-field">
                    <label>AI-down alert recipients (agent emails, comma-separated)</label>
                    <input type="text" x-model="values.alert_agent_emails" class="noti-input" placeholder="Saeid@citytravelers.co, Soud@citytravelers.co">
                </div>
                <div class="noti-field">
                    <label>Alert throttle (minutes)</label>
                    <input type="number" min="1" max="1440" x-model="values.alert_throttle_minutes" class="noti-input">
                </div>
            </div>
        </div>

        <div class="noti-field" style="text-align: right;">
            <span x-show="saveMsg" x-text="saveMsg" class="text-sm mr-3" :class="saveOk ? 'text-emerald-600 font-semibold' : 'text-red-600'"></span>
            <button @click="save()" :disabled="saving" class="main-set-btn main-set-btn-primary">
                <span x-show="!saving">Save AI Configuration</span>
                <span x-show="saving">Saving...</span>
            </button>
        </div>
    </div>
</div>

<script>
    function aiConfigTab() {
        return {
            loading: false,
            loaded: false,
            saving: false,
            testing: false,
            values: {},
            secrets: { resayil_key: '', openai_key: '' },
            models: [],
            testResult: '',
            testOk: false,
            saveMsg: '',
            saveOk: false,

            init() {
                window.addEventListener('ai-config-tab-loaded', () => this.load());
            },

            modelOptions(field) {
                return this.modelOptionsFor(this.values[field]);
            },

            modelOptionsFor(current) {
                const list = [...this.models];
                if (current && !list.includes(current)) list.unshift(current);
                return list;
            },

            addChainStep() {
                if (!Array.isArray(this.values.chain)) this.values.chain = [];
                this.values.chain.push({ provider: 'resayil', model: this.models[0] || '' });
            },

            moveChain(i, dir) {
                const j = i + dir;
                if (j < 0 || j >= this.values.chain.length) return;
                const tmp = this.values.chain[i];
                this.values.chain[i] = this.values.chain[j];
                this.values.chain[j] = tmp;
            },

            async load() {
                if (this.loaded) return;
                this.loading = true;
                try {
                    const [cfgR, modR] = await Promise.all([
                        fetch('{{ route("settings.ai") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }),
                        fetch('{{ route("settings.ai.models") }}', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }),
                    ]);
                    const cfg = await cfgR.json();
                    if (cfg.success) this.values = cfg.values;
                    const mod = await modR.json();
                    if (mod.success) this.models = mod.models;
                    this.loaded = true;
                } catch (e) {
                    console.error('AI config load failed', e);
                } finally {
                    this.loading = false;
                }
            },

            async runTest(type) {
                this.testing = true;
                this.testResult = 'Testing…';
                this.testOk = false;
                try {
                    const r = await fetch('{{ route("settings.ai.test") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ type })
                    });
                    const d = await r.json();
                    this.testOk = !!d.success;
                    this.testResult = (d.success ? '✓ ' : '✗ ') + d.message + ' (' + d.seconds + 's)';
                } catch (e) {
                    this.testResult = '✗ ' + e.message;
                } finally {
                    this.testing = false;
                }
            },

            async save() {
                this.saving = true;
                this.saveMsg = '';
                try {
                    const payload = { ...this.values };
                    // masked "current key" strings must not be sent back as values
                    payload.resayil_key = this.secrets.resayil_key || '';
                    payload.openai_key = this.secrets.openai_key || '';
                    const r = await fetch('{{ route("settings.ai.update") }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify(payload)
                    });
                    const d = await r.json();
                    this.saveOk = !!d.success;
                    this.saveMsg = d.success ? '✓ Saved (' + (d.saved || []).length + ' fields)' : (d.message || 'Save failed');
                    if (d.success) {
                        this.secrets = { resayil_key: '', openai_key: '' };
                        this.loaded = false;
                        await this.load();
                    }
                } catch (e) {
                    this.saveOk = false;
                    this.saveMsg = 'Save failed: ' + e.message;
                } finally {
                    this.saving = false;
                }
            }
        }
    }
</script>

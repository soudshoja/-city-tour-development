{{--
    Settings -> Voucher Templates tab (plan §16 step 3, §8). A gallery of
    the five designs we ship in code — clients pick among these, they
    never edit or upload their own (plan §14.8, this step's own rule 5:
    "Clients cannot edit or upload templates"). No create/edit/delete
    action exists on this page; it only lists and previews.

    Mirrors settings/partial/terms_condition.blade.php's own
    fetch-on-tab-open Alpine pattern (loadVoucherCards(), called from
    settingsPage().init()/saveTab() in settings/index.blade.php) rather
    than inventing a second convention on the same page.
--}}
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Voucher Templates</h2>
        <p class="text-sm text-gray-500 mt-1">Designs we ship and maintain. Preview each against one of your own bookings — your logo, your terms, their layout.</p>
    </div>
</div>

<!-- Loading State -->
<div x-show="loadingVoucherCards" class="flex items-center justify-center py-12">
    <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
</div>

<div x-show="!loadingVoucherCards && voucherCardsError" class="text-center py-12 bg-red-50 dark:bg-red-900/20 rounded-lg">
    <p class="text-sm text-red-600" x-text="voucherCardsError"></p>
</div>

<div x-show="!loadingVoucherCards && !voucherCardsError" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <template x-for="card in voucherCards" :key="card.task_type">
        <div class="bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden shadow-sm flex flex-col">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-600 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="card.name"></h3>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                      :class="card.has_real_booking ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                      x-text="card.has_real_booking ? 'Live data' : 'Sample'"></span>
            </div>
            <div class="px-4 py-4 flex-1">
                <template x-if="card.has_real_booking">
                    <p class="text-xs text-gray-500">Previewing your latest booking<span x-show="card.source_reference"> &mdash; <span class="font-medium text-gray-700 dark:text-gray-300" x-text="card.source_reference"></span></span>.</p>
                </template>
                <template x-if="!card.has_real_booking">
                    <p class="text-xs text-amber-700 dark:text-amber-400" x-text="card.sample_note"></p>
                </template>
            </div>
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-100 dark:border-gray-600 flex items-center gap-2">
                <a :href="card.languages.EN.preview_url" target="_blank" rel="noopener"
                   class="flex-1 text-center text-xs font-medium px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                    Preview (EN)
                </a>
                <a :href="card.languages.ARB.preview_url" target="_blank" rel="noopener"
                   class="flex-1 text-center text-xs font-medium px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 transition-colors">
                    معاينة (عربي)
                </a>
            </div>
        </div>
    </template>
</div>

<div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-100 dark:border-gray-600">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        These five designs are the only voucher layouts available — there is no upload or raw-HTML editor here.
        Your logo, address and contact details print automatically from your company profile.
        Arabic previews open right-to-left with an Arabic-shaped font throughout.
    </p>
</div>

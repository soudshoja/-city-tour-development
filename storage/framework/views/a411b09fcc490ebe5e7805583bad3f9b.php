<div x-data="hotelsManager()" x-init="loadHotels()">
    <div class="main-sys-header">
        <div class="main-sys-header-text">
            <h2>Hotels Management</h2>
            <p>View, search, and manage hotel database</p>
        </div>
        <button @click="openAddModal()" class="main-sys-primary-btn">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Add Hotel
        </button>
    </div>

    <div class="hotel-header-actions">
        <div class="hotel-search-wrapper">
            <div class="hotel-search-input-group">
                <svg class="hotel-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text"
                    x-model="search"
                    @input.debounce.300ms="searchHotels()"
                    class="hotel-search-input"
                    placeholder="Search hotels by name, city, country">

                <div x-show="search.length > 0 && search.length < minSearchLength" 
                    x-cloak
                    class="hotel-search-hint">
                    Type at least <span x-text="minSearchLength"></span> characters to search
                </div>
            </div>
        </div>

        <div class="hotel-stats-badge">
            <span class="hotel-stats-label">Total Hotels</span>
            <span class="hotel-stats-count" x-text="totalHotels.toLocaleString()">0</span>
        </div>
    </div>

    <div x-show="loading" class="hotel-loading">
        <svg class="hotel-loading-spinner" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>Loading hotels</span>
    </div>

    <template x-if="!loading && hotels.length === 0">
        <div class="hotel-empty">
            <div class="hotel-empty-icon-wrapper">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
            <h3 x-text="search ? 'No hotels found' : 'No hotels yet'"></h3>
            <p x-text="search ? 'Try adjusting your search terms' : 'Add your first hotel to get started'"></p>
        </div>
    </template>

    <div x-show="!loading && hotels.length > 0" class="hotel-table-container">
        <table class="hotel-table">
            <thead>
                <tr>
                    <th>Hotel</th>
                    <th>Rating</th>
                    <th>Location</th>
                    <th>Contact</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="hotel in hotels" :key="hotel.id">
                    <tr>
                        <td>
                            <div class="hotel-name-cell">
                                <div class="hotel-name" x-text="hotel.name"></div>
                                <div class="hotel-address" x-text="hotel.address || 'No address'" :title="hotel.address"></div>
                            </div>
                        </td>
                        <td>
                            <template x-if="hotel.rating">
                                <span class="hotel-rating-badge" :class="getRatingBadgeClass(hotel.rating)">
                                    <svg fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                    <span x-text="hotel.rating"></span>
                                </span>
                            </template>
                            <template x-if="!hotel.rating">
                                <span class="hotel-text-muted">—</span>
                            </template>
                        </td>
                        <td>
                            <template x-if="hotel.country_data || hotel.city">
                                <div class="hotel-location">
                                    <div class="hotel-city" x-text="hotel.city || '—'"></div>
                                    <div class="hotel-country-badge" x-show="hotel.country_data">
                                        <span class="hotel-country-name" x-text="hotel.country_data?.name || ''"></span>
                                        <span class="hotel-country-iso" x-text="hotel.country_data?.iso_code ? '(' + hotel.country_data.iso_code + ')' : ''"></span>
                                    </div>
                                    <div class="hotel-country-code" x-show="!hotel.country_data && hotel.country" x-text="hotel.country"></div>
                                </div>
                            </template>
                            <template x-if="!hotel.country_data && !hotel.city && !hotel.country">
                                <span class="hotel-text-muted">—</span>
                            </template>
                        </td>
                        <td>
                            <div class="hotel-contact">
                                <template x-if="hotel.phone">
                                    <div class="hotel-contact-item">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        <span x-text="hotel.phone"></span>
                                    </div>
                                </template>
                                <template x-if="hotel.email">
                                    <a :href="'mailto:' + hotel.email" class="hotel-contact-item email">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        <span x-text="hotel.email"></span>
                                    </a>
                                </template>
                                <template x-if="!hotel.phone && !hotel.email">
                                    <span class="hotel-text-muted">—</span>
                                </template>
                            </div>
                        </td>
                        <td class="text-right">
                            <div class="hotel-actions">
                                <button @click="openEditModal(hotel)" class="hotel-btn-action edit" title="Edit">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button @click="confirmDelete(hotel)" class="hotel-btn-action delete" title="Delete">
                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>

        <div x-show="totalPages > 1" class="hotel-pagination">
            <div class="hotel-pagination-info">
                Showing <span x-text="((currentPage - 1) * perPage) + 1"></span> to
                <span x-text="Math.min(currentPage * perPage, totalHotels)"></span> of
                <span x-text="totalHotels.toLocaleString()"></span> hotels
            </div>
            <div class="hotel-pagination-buttons">
                <button @click="goToPage(1)" :disabled="currentPage === 1" class="hotel-pagination-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path>
                    </svg>
                </button>
                <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1" class="hotel-pagination-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>

                <template x-for="page in visiblePages" :key="page">
                    <button @click="goToPage(page)"
                        class="hotel-pagination-btn"
                        :class="{'active': page === currentPage}"
                        x-text="page">
                    </button>
                </template>

                <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages" class="hotel-pagination-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
                <button @click="goToPage(totalPages)" :disabled="currentPage === totalPages" class="hotel-pagination-btn">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div x-show="showModal" x-cloak class="hotel-modal-overlay">
        <div class="hotel-modal-backdrop" @click="closeModal()"></div>
        <div class="hotel-modal-container">
            <div class="hotel-modal">
                <form @submit.prevent="saveHotel()">
                    <div class="hotel-modal-header">
                        <div>
                            <h3 x-text="editingHotel ? 'Edit Hotel Details' : 'Add New Hotel'"></h3>
                            <p class="hotel-modal-subtitle">Please update the hotel details to ensure accurate information</p>
                        </div>
                        <button type="button" @click="closeModal()" class="hotel-modal-header-close">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="hotel-modal-body">
                        <div class="hotel-form-section">
                            <h4 class="hotel-form-section-title blue">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                Basic Information
                            </h4>
                            <div class="hotel-form-grid">
                                <div class="hotel-form-group hotel-full-width">
                                    <label class="hotel-form-label">Hotel Name <span class="required">*</span></label>
                                    <input type="text" x-model="form.name" class="hotel-form-input" required>
                                </div>
                                <div class="hotel-form-group hotel-full-width">
                                    <label class="hotel-form-label">Address</label>
                                    <input type="text" x-model="form.address" class="hotel-form-input">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Rating (1-5)</label>
                                    <select x-model="form.rating" class="hotel-form-select">
                                        <option value="">Select rating</option>
                                        <option value="1">⭐ 1 Star</option>
                                        <option value="2">⭐ 2 Stars</option>
                                        <option value="3">⭐ 3 Stars</option>
                                        <option value="4">⭐ 4 Stars</option>
                                        <option value="5">⭐ 5 Stars</option>
                                    </select>
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Website</label>
                                    <input type="url" x-model="form.website" class="hotel-form-input" placeholder="https://">
                                </div>
                            </div>
                        </div>

                        <div class="hotel-form-section">
                            <h4 class="hotel-form-section-title green">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Location
                            </h4>
                            <div class="hotel-form-grid">
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">City</label>
                                    <input type="text" x-model="form.city" class="hotel-form-input">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">State/Region</label>
                                    <input type="text" x-model="form.state" class="hotel-form-input">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Country</label>
                                    <div class="hotel-country-dropdown-wrapper">
                                        <?php if (isset($component)) { $__componentOriginal155d1ae1bb3c51973152675834ada9b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal155d1ae1bb3c51973152675834ada9b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ajax-searchable-dropdown','data' => ['name' => 'country_id','selectedId' => '','selectedName' => '','ajaxUrl' => route('system-settings.countries.search'),'placeholder' => 'Search country','xModelId' => 'form.country_id']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ajax-searchable-dropdown'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'country_id','selectedId' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(''),'selectedName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(''),'ajaxUrl' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('system-settings.countries.search')),'placeholder' => 'Search country','x-model-id' => 'form.country_id']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal155d1ae1bb3c51973152675834ada9b4)): ?>
<?php $attributes = $__attributesOriginal155d1ae1bb3c51973152675834ada9b4; ?>
<?php unset($__attributesOriginal155d1ae1bb3c51973152675834ada9b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal155d1ae1bb3c51973152675834ada9b4)): ?>
<?php $component = $__componentOriginal155d1ae1bb3c51973152675834ada9b4; ?>
<?php unset($__componentOriginal155d1ae1bb3c51973152675834ada9b4); ?>
<?php endif; ?>
                                        <p class="hotel-country-add-hint">
                                            Country not in list? 
                                            <a href="#" @click.prevent="openAddCountryModal()" class="hotel-add-country-link">Add Country</a>
                                        </p>
                                    </div>
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Zip Code</label>
                                    <input type="text" x-model="form.zip_code" class="hotel-form-input">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Latitude</label>
                                    <input type="text" x-model="form.latitude" class="hotel-form-input" placeholder="e.g. 25.2048">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Longitude</label>
                                    <input type="text" x-model="form.longitude" class="hotel-form-input" placeholder="e.g. 55.2708">
                                </div>
                            </div>
                        </div>

                        <div class="hotel-form-section">
                            <h4 class="hotel-form-section-title purple">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Contact Information
                            </h4>
                            <div class="hotel-form-grid">
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Phone</label>
                                    <input type="text" x-model="form.phone" class="hotel-form-input">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Email</label>
                                    <input type="email" x-model="form.email" class="hotel-form-input">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hotel-modal-footer">
                        <button type="button" @click="closeModal()" class="hotel-btn-modal secondary">Cancel</button>
                        <button type="submit" class="hotel-btn-modal primary" :disabled="saving">
                            <span x-show="!saving" x-text="editingHotel ? 'Update Hotel' : 'Add Hotel'"></span>
                            <span x-show="saving">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div x-show="showDeleteModal" x-cloak class="hotel-modal-overlay">
        <div class="hotel-modal-backdrop" @click="showDeleteModal = false"></div>
        <div class="hotel-modal-container">
            <div class="hotel-modal hotel-delete-modal">
                <div class="hotel-delete-modal-body">
                    <h3 class="hotel-delete-modal-title">Delete Hotel?</h3>
                    <p class="hotel-delete-modal-text">
                        Are you sure you want to delete <strong x-text="deletingHotel?.name"></strong>?
                    </p>
                    <p class="hotel-delete-modal-warning">
                        This action cannot be undone. All associated data will be permanently removed.
                    </p>
                </div>
                <div class="hotel-modal-footer">
                    <button type="button" @click="showDeleteModal = false" class="hotel-btn-modal secondary">Cancel</button>
                    <button type="button" @click="deleteHotel()" class="hotel-btn-modal danger" :disabled="deleting">
                        <span x-show="!deleting">Yes, Delete</span>
                        <span x-show="deleting">Deleting...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div x-show="showCountryModal" x-cloak class="hotel-modal-overlay" style="z-index: 60;">
        <div class="hotel-modal-backdrop" @click="closeCountryModal()"></div>
        <div class="hotel-modal-container">
            <div class="hotel-modal">
                <form @submit.prevent="saveCountry()">
                    <div class="hotel-modal-header">
                        <h3>Add New Country</h3>
                        <button type="button" @click="closeCountryModal()" class="hotel-modal-header-close">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="hotel-modal-body">
                        <div class="hotel-form-section">
                            <h4 class="hotel-form-section-title blue">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path>
                                </svg>
                                Country Information
                            </h4>
                            <div class="hotel-form-grid">
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Country Name (English) <span class="required">*</span></label>
                                    <input type="text" x-model="countryForm.name" class="hotel-form-input" required placeholder="e.g. Kuwait">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Country Name (Arabic)</label>
                                    <input type="text" x-model="countryForm.name_ar" class="hotel-form-input" dir="rtl" placeholder="مثال: الكويت">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">ISO Code (2 letters) <span class="required">*</span></label>
                                    <input type="text" x-model="countryForm.iso_code" class="hotel-form-input" required maxlength="2" placeholder="e.g. KW">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">ISO3 Code (3 letters)</label>
                                    <input type="text" x-model="countryForm.iso3_code" class="hotel-form-input" maxlength="3" placeholder="e.g. KWT">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Dialing Code</label>
                                    <input type="text" x-model="countryForm.dialing_code" class="hotel-form-input" placeholder="e.g. +965">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Currency Code</label>
                                    <input type="text" x-model="countryForm.currency_code" class="hotel-form-input" maxlength="3" placeholder="e.g. KWD">
                                </div>
                            </div>
                        </div>

                        <div class="hotel-form-section">
                            <h4 class="hotel-form-section-title green">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                Nationality
                            </h4>
                            <div class="hotel-form-grid">
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Nationality (English)</label>
                                    <input type="text" x-model="countryForm.nationality" class="hotel-form-input" placeholder="e.g. Kuwaiti">
                                </div>
                                <div class="hotel-form-group">
                                    <label class="hotel-form-label">Nationality (Arabic)</label>
                                    <input type="text" x-model="countryForm.nationality_ar" class="hotel-form-input" dir="rtl" placeholder="مثال: كويتي">
                                </div>
                            </div>
                        </div>

                        <div class="hotel-form-section">
                            <h4 class="hotel-form-section-title purple">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Region
                            </h4>
                            <div class="hotel-form-grid">
                                <div class="hotel-form-group hotel-full-width">
                                    <label class="hotel-form-label">Continent</label>
                                    <input type="text" x-model="countryForm.continent" class="hotel-form-input" placeholder="Asia">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="hotel-modal-footer">
                        <button type="button" @click="closeCountryModal()" class="hotel-btn-modal secondary">Cancel</button>
                        <button type="submit" class="hotel-btn-modal success" :disabled="savingCountry">
                            <span x-show="!savingCountry">Add Country</span>
                            <span x-show="savingCountry">Adding...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function hotelsManager() {
        return {
            hotels: [],
            countries: <?php echo json_encode(\App\Models\Country::where('is_active', 1)->orderBy('name')->get(), 512) ?>,
            totalHotels: 0,
            totalPages: 1,
            currentPage: 1,
            perPage: 20,
            search: '',
            loading: false,
            showModal: false,
            showDeleteModal: false,
            showCountryModal: false,
            editingHotel: null,
            deletingHotel: null,
            saving: false,
            deleting: false,
            savingCountry: false,
            abortController: null,
            minSearchLength: 2,
            form: {
                name: '',
                address: '',
                city: '',
                state: '',
                country_id: '',
                zip_code: '',
                phone: '',
                email: '',
                website: '',
                rating: '',
                latitude: '',
                longitude: ''
            },
            countryForm: {
                name: '',
                name_ar: '',
                iso_code: '',
                iso3_code: '',
                dialing_code: '',
                nationality: '',
                nationality_ar: '',
                currency_code: '',
                continent: ''
            },

            get visiblePages() {
                const pages = [];
                const maxVisible = 5;
                let start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
                let end = Math.min(this.totalPages, start + maxVisible - 1);

                if (end - start + 1 < maxVisible) {
                    start = Math.max(1, end - maxVisible + 1);
                }

                for (let i = start; i <= end; i++) {
                    pages.push(i);
                }
                return pages;
            },

            getRatingBadgeClass(rating) {
                const r = parseFloat(rating);
                if (r >= 5) return 'hotel-rating-5';
                if (r >= 4) return 'hotel-rating-4';
                if (r >= 3) return 'hotel-rating-3';
                if (r >= 2) return 'hotel-rating-2';
                return 'hotel-rating-1';
            },

            async loadHotels() {
                if (this.abortController) {
                    this.abortController.abort();
                }
                this.abortController = new AbortController();

                this.loading = true;
                try {
                    const params = new URLSearchParams({
                        page: this.currentPage,
                        per_page: this.perPage,
                        search: this.search
                    });

                    const response = await fetch(`<?php echo e(route('system-settings.hotels.list')); ?>?${params}`, {
                        signal: this.abortController.signal
                    });
                    const data = await response.json();

                    this.hotels = data.data;
                    this.totalHotels = data.total;
                    this.totalPages = data.last_page;
                    this.currentPage = data.current_page;
                } catch (error) {
                    if (error.name === 'AbortError') {
                        return;
                    }
                    console.error('Error loading hotels:', error);
                } finally {
                    this.loading = false;
                }
            },

            searchHotels() {
                if (this.search.length > 0 && this.search.length < this.minSearchLength) {
                    return;
                }

                this.currentPage = 1;
                this.loadHotels();
            },

            goToPage(page) {
                if (page >= 1 && page <= this.totalPages && page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadHotels();
                }
            },

            openAddModal() {
                this.editingHotel = null;
                this.resetForm();
                this.showModal = true;
            },

            openEditModal(hotel) {
                this.editingHotel = hotel;
                this.form = {
                    name: hotel.name || '',
                    address: hotel.address || '',
                    city: hotel.city || '',
                    state: hotel.state || '',
                    country_id: hotel.country_id || '',
                    zip_code: hotel.zip_code || '',
                    phone: hotel.phone || '',
                    email: hotel.email || '',
                    website: hotel.website || '',
                    rating: hotel.rating || '',
                    latitude: hotel.latitude || '',
                    longitude: hotel.longitude || ''
                };
                this.showModal = true;
            },

            closeModal() {
                this.showModal = false;
                this.editingHotel = null;
                this.resetForm();
            },

            resetForm() {
                this.form = {
                    name: '',
                    address: '',
                    city: '',
                    state: '',
                    country_id: '',
                    zip_code: '',
                    phone: '',
                    email: '',
                    website: '',
                    rating: '',
                    latitude: '',
                    longitude: ''
                };
            },

            openAddCountryModal() {
                this.resetCountryForm();
                this.showCountryModal = true;
            },

            closeCountryModal() {
                this.showCountryModal = false;
                this.resetCountryForm();
            },

            resetCountryForm() {
                this.countryForm = {
                    name: '',
                    name_ar: '',
                    iso_code: '',
                    iso3_code: '',
                    dialing_code: '',
                    nationality: '',
                    nationality_ar: '',
                    currency_code: '',
                    continent: ''
                };
            },

            async saveCountry() {
                this.savingCountry = true;
                try {
                    const response = await fetch(`<?php echo e(route('system-settings.countries.store')); ?>`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                        },
                        body: JSON.stringify({
                            ...this.countryForm,
                            iso_code: this.countryForm.iso_code.toUpperCase(),
                            iso3_code: this.countryForm.iso3_code.toUpperCase(),
                            currency_code: this.countryForm.currency_code.toUpperCase(),
                            is_active: 1
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.countries.push(data.country);
                        this.countries.sort((a, b) => a.name.localeCompare(b.name));
                        this.form.country_id = data.country.id;
                        this.closeCountryModal();
                    } else {
                        alert(data.message || 'Error adding country');
                    }
                } catch (error) {
                    console.error('Error saving country:', error);
                    alert('Error adding country');
                } finally {
                    this.savingCountry = false;
                }
            },

            async saveHotel() {
                this.saving = true;
                try {
                    const url = this.editingHotel ? `<?php echo e(route('system-settings.hotels.update', '')); ?>/${this.editingHotel.id}` : `<?php echo e(route('system-settings.hotels.store')); ?>`;
                    const method = this.editingHotel ? 'PUT' : 'POST';

                    const response = await fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                        },
                        body: JSON.stringify(this.form)
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.closeModal();
                        this.loadHotels();
                    } else {
                        alert(data.message || 'Error saving hotel');
                    }
                } catch (error) {
                    console.error('Error saving hotel:', error);
                    alert('Error saving hotel');
                } finally {
                    this.saving = false;
                }
            },

            confirmDelete(hotel) {
                this.deletingHotel = hotel;
                this.showDeleteModal = true;
            },

            async deleteHotel() {
                this.deleting = true;
                try {
                    const response = await fetch(`<?php echo e(route('system-settings.hotels.delete', '')); ?>/${this.deletingHotel.id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.showDeleteModal = false;
                        this.deletingHotel = null;
                        this.loadHotels();
                    } else {
                        alert(data.message || 'Error deleting hotel');
                    }
                } catch (error) {
                    console.error('Error deleting hotel:', error);
                    alert('Error deleting hotel');
                } finally {
                    this.deleting = false;
                }
            }
        }
    }
</script><?php /**PATH /home/soudshoja/soud-laravel/resources/views/admin/system-settings/partials/hotel.blade.php ENDPATH**/ ?>
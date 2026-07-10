/**
 * WC AI Canvas Configurator - Frontend JavaScript
 *
 * Handles the step-based configurator UI.
 */

(function() {
    'use strict';

    // Configuration from WordPress
    const config = window.wcAiccConfig || {};
    const { productId, variations, options, optionDefaults, customizeFlow, sizingGuide, photoGuidelines, restUrl, nonce, i18n } = config;

    /**
     * @return {Array<{key: string, title: string}>}
     */
    function getCustomizeFlow() {
        if (customizeFlow && customizeFlow.length) {
            return customizeFlow;
        }
        return [
            { key: 'style', title: '' },
            { key: 'background_color', title: '' }
        ];
    }

    function getCustomizeSubstepTotal() {
        return getCustomizeFlow().length;
    }

    /**
     * Payload sent to generate endpoint (style + background only).
     * @return {{style: string, background_color: string}}
     */
    function getCustomizationPayload() {
        const defaults = optionDefaults || {};
        return {
            style: state.customizationOptions.style || defaults.style || 'original',
            background_color: state.customizationOptions.background_color || defaults.background_color || 'auto'
        };
    }

    // State
    let state = {
        currentStep: 1,
        customizeSubStep: 1,
        buildUuid: null,
        selectedVariation: null,
        customizationOptions: Object.assign({}, optionDefaults || {}),
        uploadedImageUrl: null,
        status: 'idle', // idle, uploading, processing, ready, failed
        finalArtUrl: null,
        mockupUrl: null,
        errorMessage: null,
        pollInterval: null,
        /** Incremented when starting a new poll session so late/stale HTTP responses are ignored */
        pollEpoch: 0,
        /** Session key returned by API (sent as header when cookies are unreliable) */
        sessionKey: null,
        pollFailureCount: 0,
        /** Prevents duplicate createBuild() calls from skipping multiple steps */
        createBuildInFlight: false
    };

    /**
     * Headers for authenticated REST calls.
     *
     * @param {Object} extra Extra headers.
     * @return {Object}
     */
    function buildApiHeaders(extra) {
        const headers = Object.assign({}, extra || {}, {
            'X-WP-Nonce': nonce
        });
        if (state.sessionKey) {
            headers['X-WC-AICC-Session'] = state.sessionKey;
        }
        return headers;
    }

    /**
     * Ensure a guest session exists before create/upload/generate.
     */
    async function ensureSession() {
        if (state.sessionKey || !restUrl) {
            return;
        }
        try {
            const response = await fetch(`${restUrl}/session`, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: buildApiHeaders()
            });
            const data = await response.json();
            if (response.ok && data.session_key) {
                state.sessionKey = data.session_key;
            }
        } catch (err) {
            console.warn('WC AICC: session bootstrap failed', err);
        }
    }

    /**
     * @param {Object} data Build poll payload.
     * @return {boolean}
     */
    function isBuildReady(data) {
        if (!data || typeof data !== 'object') {
            return false;
        }
        if (data.status === 'processing' || data.status === 'draft') {
            return false;
        }
        if (data.status === 'ready' || data.is_ready === true) {
            return true;
        }
        return !!(data.urls && data.urls.final_art);
    }

    /**
     * @param {string} url Asset URL.
     * @param {string|number} token Cache-bust token (regen_count or timestamp).
     * @return {string}
     */
    function withAssetCacheBuster(url, token) {
        if (!url) {
            return '';
        }
        const sep = url.indexOf('?') >= 0 ? '&' : '?';
        return url + sep + 'v=' + encodeURIComponent(String(token != null ? token : Date.now()));
    }

    /**
     * Clear preview image elements (e.g. before regeneration).
     */
    function clearPreviewImages() {
        ['wc-aicc-final-art-preview', 'wc-aicc-mockup-preview', 'wc-aicc-cart-preview'].forEach(function(id) {
            const img = document.getElementById(id);
            if (img) {
                img.removeAttribute('src');
            }
        });
    }

    /**
     * @param {Object} data Build poll payload.
     * @return {boolean}
     */
    function isBuildFailed(data) {
        return !!(data && data.status === 'failed');
    }

    // DOM Elements
    let container = null;
    let stepsContainer = null;
    let stepIndicators = null;

    /** Original Step 2 drop zone HTML (restore after failed upload) */
    let uploadDropZoneMarkup = '';

    /**
     * Initialize the configurator
     */
    function init() {
        container = document.getElementById('wc-aicc-configurator');
        
        if (!container) {
            return;
        }

        stepsContainer = container.querySelector('.wc-aicc-steps');
        stepIndicators = container.querySelectorAll('.wc-aicc-step-indicator');

        const dzInit = container.querySelector('.wc-aicc-drop-zone');
        if (dzInit) {
            uploadDropZoneMarkup = dzInit.innerHTML;
        }

        // Set up event listeners
        setupEventListeners();
        setupSizingGuide();
        setupPhotoGuidelines();
        setupSizeCardKeyboard();
        ensureSession();

        // Render initial step
        renderCurrentStep();
    }

    /**
     * Build sizing guide panel markup from localized data.
     */
    function setupSizingGuide() {
        const root = document.getElementById('wc-aicc-sizing-guide');
        if (!root || !sizingGuide) {
            return;
        }

        const body = root.querySelector('.wc-aicc-sizing-guide__body');
        const titleEl = root.querySelector('#wc-aicc-sizing-guide-title');
        if (!body) {
            return;
        }

        if (titleEl && sizingGuide.title) {
            titleEl.textContent = sizingGuide.title;
        }

        let html = '';
        if ( sizingGuide.grid_image ) {
            html += `<img class="wc-aicc-sizing-guide__grid-image" src="${escapeAttr(sizingGuide.grid_image)}" alt="${escapeAttr(sizingGuide.title || 'Sizing guide')}" loading="lazy" />`;
        }

        body.innerHTML = html;

        root.querySelectorAll('.wc-aicc-sizing-guide__close, .wc-aicc-sizing-guide__backdrop').forEach(function(el) {
            el.addEventListener('click', closeSizingGuide);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && root.getAttribute('aria-hidden') === 'false') {
                closeSizingGuide();
            }
        });
    }

    function openSizingGuide() {
        const root = document.getElementById('wc-aicc-sizing-guide');
        if (!root) {
            return;
        }
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('wc-aicc-sizing-guide-open');
        const closeBtn = root.querySelector('.wc-aicc-sizing-guide__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closeSizingGuide() {
        const root = document.getElementById('wc-aicc-sizing-guide');
        if (!root) {
            return;
        }
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('wc-aicc-sizing-guide-open');
    }

    /**
     * Build photo guidelines modal markup from localized data.
     */
    function setupPhotoGuidelines() {
        const root = document.getElementById('wc-aicc-photo-guidelines');
        if (!root || !photoGuidelines) {
            return;
        }

        const body = root.querySelector('.wc-aicc-photo-guidelines__body');
        const titleEl = root.querySelector('#wc-aicc-photo-guidelines-title');
        if (!body) {
            return;
        }

        if (titleEl && photoGuidelines.title) {
            titleEl.textContent = photoGuidelines.title;
        }

        let html = '';
        if (photoGuidelines.intro) {
            html += `<p class="wc-aicc-photo-guidelines__intro">${escapeHtml(photoGuidelines.intro)}</p>`;
        }

        if (photoGuidelines.good_image || photoGuidelines.avoid_image) {
            html += '<div class="wc-aicc-photo-guidelines__examples">';
            if (photoGuidelines.good_image) {
                html += `<figure class="wc-aicc-photo-guidelines__example wc-aicc-photo-guidelines__example--good">
                    <div class="wc-aicc-photo-guidelines__example-frame">
                        <img src="${escapeAttr(photoGuidelines.good_image)}" alt="${escapeAttr(photoGuidelines.good_label || 'Good photo')}" loading="lazy" decoding="async" />
                    </div>
                    <figcaption class="wc-aicc-photo-guidelines__example-label">${escapeHtml(photoGuidelines.good_label || 'Good photo')}</figcaption>
                </figure>`;
            }
            if (photoGuidelines.avoid_image) {
                html += `<figure class="wc-aicc-photo-guidelines__example wc-aicc-photo-guidelines__example--avoid">
                    <div class="wc-aicc-photo-guidelines__example-frame">
                        <img src="${escapeAttr(photoGuidelines.avoid_image)}" alt="${escapeAttr(photoGuidelines.avoid_label || 'Avoid')}" loading="lazy" decoding="async" />
                    </div>
                    <figcaption class="wc-aicc-photo-guidelines__example-label">${escapeHtml(photoGuidelines.avoid_label || 'Avoid')}</figcaption>
                </figure>`;
            }
            html += '</div>';
        }

        if (photoGuidelines.tips && photoGuidelines.tips.length) {
            html += '<ul class="wc-aicc-photo-guidelines__tips">';
            photoGuidelines.tips.forEach(function(tip) {
                html += `<li>${escapeHtml(tip)}</li>`;
            });
            html += '</ul>';
        }

        if (photoGuidelines.footnote) {
            html += `<p class="wc-aicc-photo-guidelines__footnote">${escapeHtml(photoGuidelines.footnote)}</p>`;
        }

        body.innerHTML = html;

        root.querySelectorAll('.wc-aicc-photo-guidelines__close, .wc-aicc-photo-guidelines__backdrop').forEach(function(el) {
            el.addEventListener('click', closePhotoGuidelines);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && root.getAttribute('aria-hidden') === 'false') {
                closePhotoGuidelines();
            }
        });
    }

    function openPhotoGuidelines() {
        const root = document.getElementById('wc-aicc-photo-guidelines');
        if (!root) {
            return;
        }
        root.hidden = false;
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add('wc-aicc-photo-guidelines-open');
        const closeBtn = root.querySelector('.wc-aicc-photo-guidelines__close');
        if (closeBtn) {
            closeBtn.focus();
        }
    }

    function closePhotoGuidelines() {
        const root = document.getElementById('wc-aicc-photo-guidelines');
        if (!root) {
            return;
        }
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('wc-aicc-photo-guidelines-open');
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escapeAttr(str) {
        return escapeHtml(str);
    }

    function setupSizeCardKeyboard() {
        container.addEventListener('keydown', function(e) {
            const frame = e.target.closest('.wc-aicc-size-card__frame');
            if (!frame || (e.key !== 'Enter' && e.key !== ' ')) {
                return;
            }
            e.preventDefault();
            highlightSizeCard(parseInt(frame.dataset.variationId, 10));
        });
    }

    function highlightSizeCard(variationId) {
        container.querySelectorAll('.wc-aicc-size-card').forEach(function(card) {
            const frame = card.querySelector('.wc-aicc-size-card__frame');
            const match = frame && parseInt(frame.dataset.variationId, 10) === variationId;
            card.classList.toggle('wc-aicc-size-card--selected', !!match);
        });
    }

    function setSizeSelectButtonsDisabled(disabled) {
        container.querySelectorAll('.wc-aicc-size-select-btn').forEach(function(btn) {
            btn.disabled = disabled;
        });
    }

    /**
     * Set up event listeners
     */
    function setupEventListeners() {
        // Variation selection
        container.addEventListener('click', function(e) {
            const sizeSelectBtn = e.target.closest('.wc-aicc-size-select-btn');
            if (sizeSelectBtn) {
                handleSizeSelect(sizeSelectBtn);
                return;
            }

            const sizeFrame = e.target.closest('.wc-aicc-size-card__frame');
            if (sizeFrame) {
                highlightSizeCard(parseInt(sizeFrame.dataset.variationId, 10));
                return;
            }

            const guideOpen = e.target.closest('.wc-aicc-sizing-guide-open');
            if (guideOpen) {
                openSizingGuide();
                return;
            }

            const photoGuideOpen = e.target.closest('.wc-aicc-photo-guidelines-open');
            if (photoGuideOpen) {
                openPhotoGuidelines();
                return;
            }

            const variationBtn = e.target.closest('.wc-aicc-variation-btn');
            if (variationBtn) {
                handleVariationSelect(variationBtn);
            }

            const customizeBack = e.target.closest('.wc-aicc-customize-back-btn');
            if (customizeBack) {
                handleCustomizeBack();
                return;
            }

            const customizeNext = e.target.closest('.wc-aicc-customize-next-btn');
            if (customizeNext) {
                handleCustomizeNext();
                return;
            }

            const choiceCard = e.target.closest('.wc-aicc-choice-card');
            if (choiceCard) {
                handleChoiceCardSelect(choiceCard);
                return;
            }

            const backBtn = e.target.closest('.wc-aicc-back-btn');
            if (backBtn) {
                goToPreviousStep();
            }

            const nextBtn = e.target.closest('.wc-aicc-next-btn');
            if (nextBtn && !nextBtn.disabled) {
                goToNextStep();
            }

            const generateBtn = e.target.closest('.wc-aicc-generate-btn');
            if (generateBtn) {
                handleGenerate();
            }

            const retryBtn = e.target.closest('.wc-aicc-retry-btn');
            if (retryBtn) {
                handleRetry();
            }

            const addToCartBtn = e.target.closest('.wc-aicc-add-to-cart-btn');
            if (addToCartBtn && !addToCartBtn.disabled) {
                handleAddToCart();
            }
        });

        container.addEventListener('input', function() {
            // Reserved for future customize inputs.
        });

        // File upload
        container.addEventListener('change', function(e) {
            if (e.target.matches('#wc-aicc-file-input')) {
                handleFileSelect(e.target.files[0]);
            }
        });
    }

    /**
     * Set up drop zone for file upload
     */
    function setupDropZone(dropZone) {
        if (!dropZone || dropZone.dataset.wcAiccDndBound === '1') {
            return;
        }
        dropZone.dataset.wcAiccDndBound = '1';

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.add('wc-aicc-drop-zone--active');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.remove('wc-aicc-drop-zone--active');
            });
        });

        dropZone.addEventListener('drop', function(e) {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });
    }

    /**
     * Handle size Select button (step 1).
     */
    function handleSizeSelect(btn) {
        if (btn.disabled || state.createBuildInFlight) {
            return;
        }
        const variationId = parseInt(btn.dataset.variationId, 10);
        const variation = variations.find(function(v) { return v.id === variationId; });
        if (!variation) {
            return;
        }
        highlightSizeCard(variationId);
        state.selectedVariation = variation;
        createBuild();
    }

    /**
     * Handle variation selection (legacy buttons)
     */
    function handleVariationSelect(btn) {
        const variationId = parseInt(btn.dataset.variationId);
        const variation = variations.find(v => v.id === variationId);

        if (!variation) return;

        state.selectedVariation = variation;

        // Update UI
        container.querySelectorAll('.wc-aicc-variation-btn').forEach(b => {
            b.classList.remove('wc-aicc-variation-btn--selected');
        });
        btn.classList.add('wc-aicc-variation-btn--selected');

        // Create build
        createBuild();
    }

    /**
     * Handle file selection
     */
    function handleFileSelect(file) {
        if (!file) return;

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            showError(i18n.invalidFileType);
            return;
        }

        // Validate file size (10MB)
        if (file.size > 10 * 1024 * 1024) {
            showError(i18n.fileTooBig);
            return;
        }

        uploadFile(file);
    }

    /**
     * Create a new build
     */
    async function createBuild() {
        if (!state.selectedVariation || state.createBuildInFlight) {
            return;
        }

        state.createBuildInFlight = true;
        setSizeSelectButtonsDisabled(true);

        try {
            await ensureSession();

            const response = await fetch(`${restUrl}/builds`, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: buildApiHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    product_id: productId,
                    variation_id: state.selectedVariation.id,
                    size_label: state.selectedVariation.size_label,
                    aspect_ratio: state.selectedVariation.aspect_ratio
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to create build');
            }

            state.buildUuid = data.build_uuid;
            if (data.session_key) {
                state.sessionKey = data.session_key;
            }

            goToNextStep();
        } catch (error) {
            console.error('Create build error:', error);
            showError(error.message);
            setSizeSelectButtonsDisabled(false);
        } finally {
            state.createBuildInFlight = false;
        }
    }

    /**
     * Upload file to server
     */
    async function uploadFile(file) {
        if (!state.buildUuid) {
            showError('Please select a size first');
            return;
        }

        state.status = 'uploading';
        updateUploadUI();

        try {
            const formData = new FormData();
            formData.append('image', file);

            const response = await fetch(`${restUrl}/builds/${state.buildUuid}/upload`, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: buildApiHeaders(),
                body: formData
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || i18n.uploadError);
            }

            state.uploadedImageUrl = data.original_url;
            state.status = 'idle';
            
            updateUploadUI();

        } catch (error) {
            console.error('Upload error:', error);
            state.status = 'idle';
            showError(error.message);
            const dropZoneErr = container.querySelector('.wc-aicc-drop-zone');
            if (dropZoneErr && uploadDropZoneMarkup) {
                delete dropZoneErr.dataset.wcAiccDndBound;
                dropZoneErr.innerHTML = uploadDropZoneMarkup;
                dropZoneErr.style.display = '';
                setupDropZone(dropZoneErr);
            }
            updateUploadUI();
        }
    }

    /**
     * Handle generate button click
     */
    async function handleGenerate() {
        if (!state.buildUuid) {
            showError('Please select a size first and complete the upload.');
            return;
        }

        if (!restUrl || !nonce) {
            showError('Configuration error. Please refresh the page.');
            return;
        }

        syncCustomizeSelectionsFromState();

        state.status = 'processing';
        state.errorMessage = null;
        state.pollFailureCount = 0;
        state.finalArtUrl = null;
        state.mockupUrl = null;
        clearPreviewImages();
        updateGenerateUI();

        await ensureSession();

        try {
            const response = await fetch(`${restUrl}/builds/${state.buildUuid}/generate`, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: buildApiHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    customization_options: getCustomizationPayload()
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || (i18n && i18n.generateError) || 'Generation failed');
            }

            // Start polling for status
            startPolling();

        } catch (error) {
            console.error('Generate error:', error);
            state.status = 'failed';
            state.errorMessage = error.message;
            updateGenerateUI();
        }
    }

    /**
     * Apply a successful build status poll.
     *
     * @param {Object} data REST payload.
     */
    function handleBuildPollSuccess(data) {
        if (isBuildFailed(data)) {
            stopPolling();
            state.status = 'failed';
            state.errorMessage = data.error_message || (i18n && i18n.generateError) || 'Generation failed';
            updateGenerateUI();
            return;
        }

        if (!isBuildReady(data)) {
            return;
        }

        stopPolling();
        state.status = 'ready';
        state.pollFailureCount = 0;
        const cacheToken = data.regen_count != null ? data.regen_count : (data.updated_at || Date.now());
        state.finalArtUrl = (data.urls && data.urls.final_art)
            ? withAssetCacheBuster(data.urls.final_art, cacheToken)
            : null;
        state.mockupUrl = (data.urls && data.urls.mockup)
            ? withAssetCacheBuster(data.urls.mockup, cacheToken)
            : null;
        updateGenerateUI();

        if (state.currentStep === 3) {
            goToNextStep();
        } else if (state.currentStep === 4) {
            updatePreviewImages();
        }
    }

    /**
     * Start polling for build status
     */
    function startPolling() {
        stopPolling();
        state.pollEpoch = (state.pollEpoch || 0) + 1;
        state.pollFailureCount = 0;
        const sessionEpoch = state.pollEpoch;

        const pollOnce = async () => {
            if (!state.buildUuid) {
                return;
            }

            try {
                const pollUrl = `${restUrl}/builds/${state.buildUuid}?_=${Date.now()}`;
                const response = await fetch(pollUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: buildApiHeaders()
                });

                let data = null;
                const raw = await response.text();
                try {
                    data = raw ? JSON.parse(raw) : null;
                } catch (parseErr) {
                    throw new Error('Invalid status response from server');
                }

                if (!response.ok) {
                    throw new Error((data && data.message) ? data.message : 'Failed to get status');
                }

                // Ignore responses from a previous generate session or after a newer poll started
                if (sessionEpoch !== state.pollEpoch) {
                    return;
                }

                state.pollFailureCount = 0;

                // Fallback if JSON body is stale but response headers were refreshed (CDN edge cases).
                const headerStatus = response.headers.get('X-WC-AICC-Build-Status');
                if (headerStatus && data && data.status !== headerStatus) {
                    data.status = headerStatus;
                    if (headerStatus === 'ready') {
                        data.is_ready = true;
                    }
                }

                handleBuildPollSuccess(data);

            } catch (error) {
                console.error('Polling error:', error);
                if (sessionEpoch !== state.pollEpoch) {
                    return;
                }
                state.pollFailureCount = (state.pollFailureCount || 0) + 1;
                if (state.pollFailureCount >= 20) {
                    stopPolling();
                    state.status = 'failed';
                    state.errorMessage = error.message || 'Could not reach the server to check generation status. Please refresh and try again.';
                    updateGenerateUI();
                }
            }
        };

        pollOnce();
        state.pollInterval = setInterval(pollOnce, 2000);
    }

    /**
     * Stop polling
     */
    function stopPolling() {
        if (state.pollInterval) {
            clearInterval(state.pollInterval);
            state.pollInterval = null;
        }
    }

    /**
     * Handle retry button
     */
    function handleRetry() {
        state.status = 'idle';
        state.errorMessage = null;
        updateGenerateUI();
    }

    /**
     * Handle add to cart
     */
    async function handleAddToCart() {
        if (!state.buildUuid || state.status !== 'ready') return;

        try {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const addToCartInput = document.createElement('input');
            addToCartInput.type = 'hidden';
            addToCartInput.name = 'add-to-cart';
            addToCartInput.value = productId;
            form.appendChild(addToCartInput);

            const variationInput = document.createElement('input');
            variationInput.type = 'hidden';
            variationInput.name = 'variation_id';
            variationInput.value = state.selectedVariation.id;
            form.appendChild(variationInput);

            const buildInput = document.createElement('input');
            buildInput.type = 'hidden';
            buildInput.name = 'wc_aicc_build_uuid';
            buildInput.value = state.buildUuid;
            form.appendChild(buildInput);

            const quantityInput = document.createElement('input');
            quantityInput.type = 'hidden';
            quantityInput.name = 'quantity';
            quantityInput.value = '1';
            form.appendChild(quantityInput);

            document.body.appendChild(form);
            form.submit();

        } catch (error) {
            console.error('Add to cart error:', error);
            showError(error.message);
        }
    }

    /**
     * Go to next step
     */
    function goToNextStep() {
        if (state.currentStep < 5) {
            state.currentStep++;
            if (state.currentStep === 3) {
                state.customizeSubStep = 1;
            }
            renderCurrentStep();
            updateStepIndicators();
            updateGenerateUI();

            if (state.currentStep === 4) {
                updatePreviewImages();
            } else if (state.currentStep === 5) {
                updateSummary();
            }
        }
    }

    /**
     * Go to previous step
     */
    function goToPreviousStep() {
        if (state.currentStep > 1) {
            if (state.currentStep === 4) {
                stopPolling();
                state.status = 'idle';
                state.finalArtUrl = null;
                state.mockupUrl = null;
                clearPreviewImages();
                updateGenerateUI();
            }
            state.currentStep--;
            if (state.currentStep === 3) {
                state.customizeSubStep = getCustomizeSubstepTotal();
            }
            renderCurrentStep();
            updateStepIndicators();
        }
    }

    /**
     * Customize sub-step: back
     */
    function handleCustomizeBack() {
        if (state.customizeSubStep <= 1) {
            goToPreviousStep();
            return;
        }
        state.customizeSubStep--;
        renderCustomizeSubstep();
    }

    /**
     * Customize sub-step: continue
     */
    function handleCustomizeNext() {
        if (state.customizeSubStep >= getCustomizeSubstepTotal()) {
            return;
        }
        state.customizeSubStep++;
        renderCustomizeSubstep();
    }

    /**
     * Card selection for style / background
     */
    function handleChoiceCardSelect(card) {
        const key = card.dataset.optionKey;
        const value = card.dataset.value;
        if (!key || value === undefined) {
            return;
        }
        state.customizationOptions[key] = value;
        const panel = card.closest('.wc-aicc-customize-panel');
        if (panel) {
            panel.querySelectorAll('.wc-aicc-choice-card[data-option-key="' + key + '"]').forEach(c => {
                const selected = c === card;
                c.classList.toggle('wc-aicc-choice-card--selected', selected);
                c.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        }
    }

    /**
     * Show only the active customize panel
     */
    function renderCustomizeSubstep() {
        if (!container) {
            return;
        }
        const flow = getCustomizeFlow();
        const total = flow.length;
        const activeStep = flow[state.customizeSubStep - 1];
        const activeKey = activeStep ? activeStep.key : 'style';

        const isLastStep = state.customizeSubStep >= total;

        container.querySelectorAll('.wc-aicc-customize-panel').forEach(function(panel) {
            const panelKey = panel.dataset.customizeKey || '';
            const isActive = panelKey === activeKey;
            panel.style.display = isActive ? 'block' : 'none';

            const nextBtn = panel.querySelector('.wc-aicc-customize-next-btn');
            const genBtn = panel.querySelector('.wc-aicc-generate-btn');
            if (isActive && nextBtn && genBtn) {
                nextBtn.style.display = isLastStep ? 'none' : '';
                genBtn.style.display = isLastStep ? '' : 'none';
            }
        });
    }

    /**
     * Sync card UI from state (e.g. after entering step 3)
     */
    function syncChoiceCardsFromState() {
        if (!container || !options) {
            return;
        }
        Object.keys(state.customizationOptions || {}).forEach(key => {
            const value = state.customizationOptions[key];
            container.querySelectorAll('.wc-aicc-choice-card[data-option-key="' + key + '"]').forEach(card => {
                const selected = card.dataset.value === String(value);
                card.classList.toggle('wc-aicc-choice-card--selected', selected);
                card.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        });
    }

    /**
     * Update step indicators
     */
    function updateStepIndicators() {
        stepIndicators.forEach((indicator, index) => {
            const stepNum = index + 1;
            indicator.classList.remove('wc-aicc-step-indicator--active', 'wc-aicc-step-indicator--completed');
            
            if (stepNum === state.currentStep) {
                indicator.classList.add('wc-aicc-step-indicator--active');
            } else if (stepNum < state.currentStep) {
                indicator.classList.add('wc-aicc-step-indicator--completed');
            }
        });
    }

    /**
     * Render current step content
     */
    function renderCurrentStep() {
        const steps = stepsContainer.querySelectorAll('.wc-aicc-step');
        
        steps.forEach((step, index) => {
            const stepNum = index + 1;
            step.style.display = stepNum === state.currentStep ? 'block' : 'none';
        });

        // Step 2: sync preview / caption / next; only bind drop zone when upload area is visible
        if (state.currentStep === 2) {
            updateUploadUI();
            const dropZone = container.querySelector('.wc-aicc-drop-zone');
            if (dropZone && !state.uploadedImageUrl) {
                setupDropZone(dropZone);
            }
        }

        // Customize flow: align cards with state and show active sub-step
        if (state.currentStep === 3) {
            syncChoiceCardsFromState();
            renderCustomizeSubstep();
        }
    }

    /**
     * Ensure generate payload matches selected cards (state is source of truth)
     */
    function syncCustomizeSelectionsFromState() {
        syncChoiceCardsFromState();
    }

    /**
     * Resolve choice label for summary (supports { label, hint } or string)
     */
    function getChoiceLabel(optionKey, value) {
        if (!value || !options || !options[optionKey] || !options[optionKey].choices) {
            return '';
        }
        const ch = options[optionKey].choices[value];
        if (!ch) {
            return '';
        }
        if (typeof ch === 'object' && ch.label) {
            return ch.label;
        }
        return typeof ch === 'string' ? ch : '';
    }

    /**
     * Update upload UI
     */
    function updateUploadUI() {
        const dropZone = container.querySelector('.wc-aicc-drop-zone');
        const preview = container.querySelector('.wc-aicc-upload-preview');
        const nextBtn = container.querySelector('.wc-aicc-step-2 .wc-aicc-next-btn');
        const caption = container.querySelector('.wc-aicc-upload-preview-caption');

        if (state.status === 'uploading') {
            if (dropZone) {
                dropZone.style.display = '';
                dropZone.innerHTML = '<div class="wc-aicc-spinner"></div><p>' + (i18n.uploading || 'Uploading...') + '</p>';
            }
            if (caption) caption.hidden = true;
            if (preview) {
                preview.innerHTML = '';
                preview.style.display = 'none';
            }
            if (nextBtn) nextBtn.disabled = true;
        } else if (state.uploadedImageUrl) {
            if (dropZone) dropZone.style.display = 'none';
            if (preview) {
                preview.innerHTML = `<img src="${state.uploadedImageUrl}" alt="" />`;
                preview.style.display = 'block';
            }
            if (caption) caption.hidden = false;
            if (nextBtn) nextBtn.disabled = false;
        } else {
            if (caption) caption.hidden = true;
        }
    }

    /**
     * Update generate button state
     */
    function updateGenerateButton() {
        container.querySelectorAll('.wc-aicc-generate-btn').forEach(function(generateBtn) {
            generateBtn.disabled = false;
        });
    }

    /**
     * Update generate UI
     */
    function updateGenerateUI() {
        const generateBtns = container.querySelectorAll('.wc-aicc-generate-btn');
        const statusEl = container.querySelector('.wc-aicc-generate-status');
        const errorEl = container.querySelector('.wc-aicc-error-message');

        generateBtns.forEach(function(generateBtn) {
            if (state.status === 'processing') {
                generateBtn.disabled = true;
                generateBtn.textContent = i18n.generating;
            } else {
                generateBtn.disabled = false;
                generateBtn.textContent = i18n.generatePreview;
            }
        });

        if (statusEl) {
            if (state.status === 'processing') {
                const patience = (i18n && i18n.generatingPatience) ? `<p class="wc-aicc-generate-patience">${i18n.generatingPatience}</p>` : '';
                statusEl.innerHTML = `<div class="wc-aicc-spinner"></div><p>${i18n.processing}</p>${patience}`;
                statusEl.style.display = 'block';
            } else {
                statusEl.style.display = 'none';
            }
        }

        if (errorEl) {
            if (state.status === 'failed' && state.errorMessage) {
                errorEl.innerHTML = `
                    <p>${state.errorMessage}</p>
                    <button type="button" class="wc-aicc-retry-btn">${i18n.retry}</button>
                `;
                errorEl.style.display = 'block';
            } else {
                errorEl.style.display = 'none';
            }
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        const errorEl = container.querySelector('.wc-aicc-error-message');
        if (errorEl) {
            errorEl.innerHTML = `<p>${message}</p>`;
            errorEl.style.display = 'block';
            
            setTimeout(() => {
                errorEl.style.display = 'none';
            }, 5000);
        } else {
            alert(message);
        }
    }

    /**
     * Update preview images in step 4
     */
    function updatePreviewImages() {
        const finalArtImg = document.getElementById('wc-aicc-final-art-preview');
        const mockupImg = document.getElementById('wc-aicc-mockup-preview');

        [finalArtImg, mockupImg].forEach(function(img) {
            if (!img) {
                return;
            }
            img.removeAttribute('width');
            img.removeAttribute('height');
        });

        if (finalArtImg && state.finalArtUrl) {
            finalArtImg.src = state.finalArtUrl;
        }

        if (mockupImg && (state.mockupUrl || state.finalArtUrl)) {
            mockupImg.src = state.mockupUrl || state.finalArtUrl;
        }
    }

    /**
     * Update summary in step 5
     */
    function updateSummary() {
        const sizeEl = document.getElementById('wc-aicc-summary-size');
        const optionsEl = document.getElementById('wc-aicc-summary-options');
        const priceEl = document.getElementById('wc-aicc-summary-price');
        const cartPreviewImg = document.getElementById('wc-aicc-cart-preview');

        if (sizeEl && state.selectedVariation) {
            sizeEl.textContent = state.selectedVariation.size_inches
                ? state.selectedVariation.size_inches + (state.selectedVariation.size_cm ? ' ' + state.selectedVariation.size_cm : '')
                : state.selectedVariation.size_label;
        }

        if (optionsEl && options) {
            const flow = getCustomizeFlow();
            const parts = [];
            flow.forEach(function(step) {
                const key = step.key;
                const val = state.customizationOptions[key];
                if (!val) {
                    return;
                }
                if (key === 'background_color' && val === 'auto') {
                    return;
                }
                const label = getChoiceLabel(key, val);
                if (label) {
                    parts.push(label);
                }
            });
            optionsEl.textContent = parts.length ? parts.join(', ') : '-';
        }

        if (priceEl && state.selectedVariation) {
            priceEl.innerHTML = state.selectedVariation.price_html;
        }

        if (cartPreviewImg && (state.mockupUrl || state.finalArtUrl)) {
            cartPreviewImg.src = state.mockupUrl || state.finalArtUrl;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

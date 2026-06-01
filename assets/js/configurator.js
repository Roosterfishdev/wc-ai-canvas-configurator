/**
 * WC AI Canvas Configurator - Frontend JavaScript
 *
 * Handles the step-based configurator UI.
 */

(function() {
    'use strict';

    // Configuration from WordPress
    const config = window.wcAiccConfig || {};
    const { productId, variations, options, optionDefaults, customizeFlow, styleCustomizeFlows, sizingGuide, restUrl, nonce, i18n } = config;

    /**
     * @return {Array<{key: string, title: string}>}
     */
    function getCustomizeFlow() {
        const style = (state.customizationOptions && state.customizationOptions.style) || '';
        if (style && styleCustomizeFlows && styleCustomizeFlows[style] && styleCustomizeFlows[style].length) {
            return styleCustomizeFlows[style];
        }
        if (customizeFlow && customizeFlow.length) {
            return customizeFlow;
        }
        return [
            { key: 'style', title: '' },
            { key: 'situation', title: '' },
            { key: 'background_color', title: '' }
        ];
    }

    function getCustomizeSubstepTotal() {
        return getCustomizeFlow().length;
    }

    function styleRequiresPetName(styleKey) {
        return styleKey === 'black_studio';
    }

    function styleSkipsSituationStep(styleKey) {
        return styleKey === 'magazine_dogue'
            || styleKey === 'black_studio'
            || styleKey === 'royal_legacy'
            || styleKey === 'whiskey_office';
    }

    function applyStyleFlowDefaults(styleKey) {
        if (styleKey === 'black_studio' || styleKey === 'royal_legacy' || styleKey === 'whiskey_office') {
            state.customizationOptions.situation = 'neutral';
            state.customizationOptions.background_color = 'natural';
            state.customizationOptions.situation_custom = '';
        } else if (styleKey === 'magazine_dogue') {
            state.customizationOptions.situation = 'neutral';
            state.customizationOptions.situation_custom = '';
        }
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
        pollFailureCount: 0
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
        if (data.status === 'ready' || data.is_ready === true) {
            return true;
        }
        return !!(data.urls && data.urls.final_art);
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
        if (sizingGuide.intro) {
            html += `<p class="wc-aicc-sizing-guide__intro">${escapeHtml(sizingGuide.intro)}</p>`;
        }
        if (sizingGuide.grid_image) {
            html += `<img class="wc-aicc-sizing-guide__grid-image" src="${escapeAttr(sizingGuide.grid_image)}" alt="${escapeAttr(sizingGuide.title || 'Sizing guide')}" loading="lazy" />`;
        }

        const entries = Array.isArray(sizingGuide.entries) ? sizingGuide.entries : [];
        if (entries.length) {
            html += '<div class="wc-aicc-sizing-guide__entries">';
            entries.forEach(function(entry) {
                html += '<div class="wc-aicc-sizing-guide__entry">';
                if (entry.image) {
                    html += `<img class="wc-aicc-sizing-guide__entry-img" src="${escapeAttr(entry.image)}" alt="${escapeAttr(entry.inches || entry.label || '')}" loading="lazy" />`;
                }
                if (entry.label) {
                    html += `<span class="wc-aicc-sizing-guide__entry-label">${escapeHtml(entry.label)}</span>`;
                }
                if (entry.inches) {
                    html += `<span class="wc-aicc-sizing-guide__entry-inches">${escapeHtml(entry.inches)}</span>`;
                }
                if (entry.cm) {
                    html += `<span class="wc-aicc-sizing-guide__entry-cm">${escapeHtml(entry.cm)}</span>`;
                }
                html += '</div>';
            });
            html += '</div>';
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

        container.addEventListener('input', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('wc-aicc-situation-custom-input')) {
                syncSituationCustomFromDom();
            }
            if (e.target && e.target.classList && e.target.classList.contains('wc-aicc-pet-name-input')) {
                syncPetNameFromDom();
            }
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
        if (btn.disabled) {
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
        if (!state.selectedVariation) return;

        await ensureSession();

        try {
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
            
            // Move to next step
            goToNextStep();

        } catch (error) {
            console.error('Create build error:', error);
            showError(error.message);
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

        if (styleRequiresPetName(state.customizationOptions.style)) {
            syncPetNameFromDom();
            const name = (state.customizationOptions.pet_name || '').trim();
            if (!name) {
                showError((i18n && i18n.petNameRequired) ? i18n.petNameRequired : 'Please enter your pet\'s name.');
                const flow = getCustomizeFlow();
                const petIdx = flow.findIndex(function(step) { return step.key === 'pet_name'; });
                if (petIdx >= 0) {
                    state.customizeSubStep = petIdx + 1;
                    renderCustomizeSubstep();
                }
                return;
            }
        }

        state.status = 'processing';
        state.errorMessage = null;
        state.pollFailureCount = 0;
        updateGenerateUI();

        await ensureSession();

        try {
            const response = await fetch(`${restUrl}/builds/${state.buildUuid}/generate`, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: buildApiHeaders({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({
                    customization_options: state.customizationOptions
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
        state.finalArtUrl = (data.urls && data.urls.final_art) ? data.urls.final_art : null;
        state.mockupUrl = (data.urls && data.urls.mockup) ? data.urls.mockup : null;
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

            // Update previews when entering step 4 or 5
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
            state.currentStep--;
            if (state.currentStep === 3) {
                state.customizeSubStep = 1;
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
     * Card selection for style / situation / background
     */
    function handleChoiceCardSelect(card) {
        const key = card.dataset.optionKey;
        const value = card.dataset.value;
        if (!key || value === undefined) {
            return;
        }
        state.customizationOptions[key] = value;
        if (key === 'style') {
            applyStyleFlowDefaults(value);
            const flow = getCustomizeFlow();
            if (state.customizeSubStep > flow.length) {
                state.customizeSubStep = flow.length;
            }
            renderCustomizeSubstep();
        }
        const panel = card.closest('.wc-aicc-customize-panel');
        if (panel) {
            panel.querySelectorAll('.wc-aicc-choice-card[data-option-key="' + key + '"]').forEach(c => {
                c.classList.toggle('wc-aicc-choice-card--selected', c === card);
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

            const badge = panel.querySelector('.wc-aicc-customize-badge--dynamic');
            const meta = panel.querySelector('.wc-aicc-customize-panel__meta--dynamic');
            if (badge) {
                badge.textContent = '3.' + state.customizeSubStep;
            }
            if (meta && activeStep && isActive) {
                meta.textContent = ((i18n && i18n.step) ? i18n.step : 'Step') + ' ' + state.customizeSubStep + ' of ' + total;
            }

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
            });
        });
        syncSituationCustomToDom();
        syncPetNameToDom();
    }

    function syncPetNameFromDom() {
        const input = container.querySelector('.wc-aicc-pet-name-input');
        if (!input) {
            return;
        }
        let v = (input.value || '').slice(0, 40);
        if (input.value && input.value.length > 40) {
            input.value = v;
        }
        state.customizationOptions.pet_name = v;
    }

    function syncPetNameToDom() {
        const input = container.querySelector('.wc-aicc-pet-name-input');
        if (!input) {
            return;
        }
        const v = state.customizationOptions.pet_name;
        input.value = v != null ? String(v) : '';
    }

    /**
     * Read optional character / situation free text into state (max 500 client-side).
     */
    function syncSituationCustomFromDom() {
        const ta = container.querySelector('.wc-aicc-situation-custom-input');
        if (!ta) {
            return;
        }
        let v = (ta.value || '').slice(0, 500);
        if (ta.value && ta.value.length > 500) {
            ta.value = v;
        }
        state.customizationOptions.situation_custom = v;
    }

    function syncSituationCustomToDom() {
        const ta = container.querySelector('.wc-aicc-situation-custom-input');
        if (!ta) {
            return;
        }
        const v = state.customizationOptions.situation_custom;
        ta.value = v != null ? String(v) : '';
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

        // Customize flow: show first sub-step and align cards with state
        if (state.currentStep === 3) {
            state.customizeSubStep = 1;
            syncChoiceCardsFromState();
            renderCustomizeSubstep();
        }
    }

    /**
     * Ensure generate payload matches selected cards (state is source of truth)
     */
    function syncCustomizeSelectionsFromState() {
        syncSituationCustomFromDom();
        syncPetNameFromDom();
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
                if (key === 'pet_name') {
                    const pn = (state.customizationOptions.pet_name || '').trim();
                    if (pn) {
                        const prefix = (i18n && i18n.petNameLabel) ? i18n.petNameLabel : 'Pet name';
                        parts.push(prefix + ': ' + pn);
                    }
                    return;
                }
                const val = state.customizationOptions[key];
                if (!val) {
                    return;
                }
                if (key === 'background_color' && val === 'natural') {
                    return;
                }
                const label = getChoiceLabel(key, val);
                if (label) {
                    parts.push(label);
                }
            });
            const sc = (state.customizationOptions.situation_custom || '').trim();
            if (sc && !styleRequiresPetName(state.customizationOptions.style) && !styleSkipsSituationStep(state.customizationOptions.style)) {
                const prefix = (i18n && i18n.summaryCustomDirection) ? i18n.summaryCustomDirection : 'Custom direction';
                const short = sc.length > 80 ? sc.slice(0, 80) + '…' : sc;
                parts.push(prefix + ': ' + short);
            }
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

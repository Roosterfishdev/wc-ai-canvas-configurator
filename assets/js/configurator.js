/**
 * WC AI Canvas Configurator - Frontend JavaScript
 *
 * Handles the step-based configurator UI.
 */

(function() {
    'use strict';

    // Configuration from WordPress
    const config = window.wcAiccConfig || {};
    const { productId, variations, options, optionDefaults, customizeFlow, restUrl, nonce, i18n } = config;

    const CUSTOMIZE_SUBSTEP_TOTAL = (customizeFlow && customizeFlow.length) ? customizeFlow.length : 3;

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
        pollEpoch: 0
    };

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

        // Render initial step
        renderCurrentStep();
    }

    /**
     * Set up event listeners
     */
    function setupEventListeners() {
        // Variation selection
        container.addEventListener('click', function(e) {
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
     * Handle variation selection
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

        try {
            const response = await fetch(`${restUrl}/builds`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
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
                headers: {
                    'X-WP-Nonce': nonce
                },
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
        updateGenerateUI();

        try {
            const response = await fetch(`${restUrl}/builds/${state.buildUuid}/generate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
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
     * Start polling for build status
     */
    function startPolling() {
        stopPolling();
        state.pollEpoch = (state.pollEpoch || 0) + 1;
        const sessionEpoch = state.pollEpoch;

        state.pollInterval = setInterval(async () => {
            try {
                const response = await fetch(`${restUrl}/builds/${state.buildUuid}`, {
                    headers: {
                        'X-WP-Nonce': nonce
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Failed to get status');
                }

                // Ignore responses from a previous generate session or after a newer poll started
                if (sessionEpoch !== state.pollEpoch) {
                    return;
                }

                if (data.status === 'ready') {
                    stopPolling();
                    state.status = 'ready';
                    state.finalArtUrl = data.urls.final_art;
                    state.mockupUrl = data.urls.mockup;
                    updateGenerateUI();
                    // Only auto-advance once from the customize step. Concurrent poll ticks used to each call
                    // goToNextStep() (3→4→5). Late responses also fired goToNextStep from step 4 (e.g. after Back).
                    if (state.currentStep === 3) {
                        goToNextStep();
                    }
                } else if (data.status === 'failed') {
                    stopPolling();
                    state.status = 'failed';
                    state.errorMessage = data.error_message || (i18n && i18n.generateError) || 'Generation failed';
                    updateGenerateUI();
                }
                // Continue polling if still processing

            } catch (error) {
                console.error('Polling error:', error);
                // Don't stop polling on transient errors
            }
        }, 2000);
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
        if (state.customizeSubStep >= CUSTOMIZE_SUBSTEP_TOTAL) {
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
        container.querySelectorAll('.wc-aicc-customize-panel').forEach(panel => {
            const n = parseInt(panel.dataset.customizeSubstep, 10);
            panel.style.display = n === state.customizeSubStep ? 'block' : 'none';
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
        const generateBtn = container.querySelector('.wc-aicc-generate-btn');
        if (generateBtn) {
            generateBtn.disabled = false;
        }
    }

    /**
     * Update generate UI
     */
    function updateGenerateUI() {
        const generateBtn = container.querySelector('.wc-aicc-generate-btn');
        const statusEl = container.querySelector('.wc-aicc-generate-status');
        const errorEl = container.querySelector('.wc-aicc-error-message');

        if (generateBtn) {
            if (state.status === 'processing') {
                generateBtn.disabled = true;
                generateBtn.textContent = i18n.generating;
            } else {
                generateBtn.disabled = false;
                generateBtn.textContent = i18n.generatePreview;
            }
        }

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

        if (mockupImg && state.mockupUrl) {
            mockupImg.src = state.mockupUrl;
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
            sizeEl.textContent = state.selectedVariation.size_label;
        }

        if (optionsEl && options) {
            const order = (customizeFlow && customizeFlow.length)
                ? customizeFlow.map(f => f.key).filter(Boolean)
                : ['style', 'situation', 'background_color'];
            const keys = order.length ? order : Object.keys(state.customizationOptions || {});
            const parts = [];
            keys.forEach(key => {
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
            if (sc) {
                const prefix = (i18n && i18n.summaryCustomDirection) ? i18n.summaryCustomDirection : 'Custom direction';
                const short = sc.length > 80 ? sc.slice(0, 80) + '…' : sc;
                parts.push(prefix + ': ' + short);
            }
            optionsEl.textContent = parts.length ? parts.join(', ') : '-';
        }

        if (priceEl && state.selectedVariation) {
            priceEl.innerHTML = state.selectedVariation.price_html;
        }

        if (cartPreviewImg && state.mockupUrl) {
            cartPreviewImg.src = state.mockupUrl;
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

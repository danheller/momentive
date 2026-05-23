(function() {
    'use strict';
    
    function applyIconOverlays() {
        // Find all Media & Text blocks with icon overlay classes
        const mediaTextBlocks = document.querySelectorAll('.wp-block-media-text[class*="has-"][class*="-icon"]');
        
        mediaTextBlocks.forEach(block => {
            // Skip if already processed
            if (block.dataset.iconProcessed === 'true') {
                return;
            }
            
            const classes = block.className.split(' ');
            let iconId = null;
            let bgColor = 'pink';
            let fillColor = 'white';
            let position = 'center';
            let shape = 'tilted-square';
            const colorOptions = ['pink', 'light-purple', 'sky-blue', 'mint', 'white'];
    
            // Parse classes to extract icon, color, and position
            classes.forEach(cls => {
                if (cls.startsWith('has-') && cls.endsWith('-icon')) {
                    iconId = cls.replace('has-', '').replace('-icon', '');
                }
                // Check for background color (e.g., "pink-background")
                if (cls.endsWith('-background')) {
                    const color = cls.replace('-background', '');
                    if (colorOptions.includes(color)) {
                        bgColor = color;
                    }
                }
                
                // Check for fill color (e.g., "mint-fill")
                if (cls.endsWith('-fill')) {
                    const color = cls.replace('-fill', '');
                    if (colorOptions.includes(color)) {
                        fillColor = color;
                    }
                }
                if (['bottom-left', 'bottom-right', 'center'].includes(cls)) {
                    position = cls;
                }
                if (['tilted-square', 'circle', 'square'].includes(cls)) {
                    shape = cls;
                }
            });
            
            if (iconId) {
                const figure = block.querySelector('figure');
                if (figure) {
                    // Remove any existing overlay icons first
                    const existingOverlay = figure.querySelector('.overlay-icon');
                    if (existingOverlay) {
                        existingOverlay.remove();
                    }
                    
                    const iconHtml = `<span class="svg-icon shape-${shape} ${iconId}-icon bg-${bgColor} overlay-icon ${position}" style="--icon-fill: var(--${fillColor});"><svg aria-hidden="true"><use href="#icon-${iconId}"></use></svg></span>`;
                    figure.style.position = 'relative';
                    figure.insertAdjacentHTML('beforeend', iconHtml);
                    
                    // Mark as processed
                    block.dataset.iconProcessed = 'true';
                }
            }
        });
    }
    
    // Run on frontend
    if (typeof wp === 'undefined' || typeof wp.data === 'undefined') {
        // Frontend only
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyIconOverlays);
        } else {
            applyIconOverlays();
        }
    } else {
        // Editor environment
        wp.domReady(function() {
            // Initial run
            applyIconOverlays();
            
            // Subscribe to block editor changes
            let previousBlocks = [];
            
            wp.data.subscribe(function() {
                const blocks = wp.data.select('core/block-editor').getBlocks();
                
                // Check if blocks have changed
                if (JSON.stringify(blocks) !== JSON.stringify(previousBlocks)) {
                    previousBlocks = blocks;
                    
                    // Reset processed flags when blocks change
                    document.querySelectorAll('.wp-block-media-text').forEach(block => {
                        delete block.dataset.iconProcessed;
                    });
                    
                    // Small delay to ensure DOM is updated
                    setTimeout(applyIconOverlays, 100);
                }
            });
            
            // Also run on window load (for iframe editor)
            window.addEventListener('load', function() {
                setTimeout(applyIconOverlays, 200);
            });
        });
    }
    
    // Also expose function globally for manual calls if needed
    window.amsApplyIconOverlays = applyIconOverlays;
    
})();
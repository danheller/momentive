(function () {
	'use strict';

	function initIntegrationList(container) {
		var grid        = container.querySelector('.integration-list__grid');
		if (!grid) return;

		var cards       = grid.querySelectorAll('.integration-card');
		var noResults   = container.querySelector('.integration-list__no-results');
		var resetBtn    = container.querySelector('.integration-list__reset');

		var typeSelect      = container.querySelector('.integration-list__select--type');
		var productSelect   = container.querySelector('.integration-list__select--product');
		var capCheckboxes   = container.querySelectorAll('.integration-list__capability-checkbox');

		// ── Capabilities dropdown toggle ──────────────────────────────────────

		var capToggle = container.querySelector('.il-cap-dropdown__toggle');
		var capPanel  = container.querySelector('.il-cap-dropdown__panel');

		if (capToggle && capPanel) {
			capToggle.addEventListener('click', function () {
				var isOpen = capToggle.getAttribute('aria-expanded') === 'true';
				capToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
				if (isOpen) {
					capPanel.setAttribute('hidden', '');
				} else {
					capPanel.removeAttribute('hidden');
				}
			});

			// Close when clicking outside.
			document.addEventListener('click', function (e) {
				if (!capToggle.contains(e.target) && !capPanel.contains(e.target)) {
					capToggle.setAttribute('aria-expanded', 'false');
					capPanel.setAttribute('hidden', '');
				}
			});
		}

		// ── Filtering ─────────────────────────────────────────────────────────

		function getCheckedCapabilities() {
			var checked = [];
			for (var i = 0; i < capCheckboxes.length; i++) {
				if (capCheckboxes[i].checked) {
					checked.push(capCheckboxes[i].value);
				}
			}
			return checked;
		}

		function applyFilters() {
			var selectedType    = typeSelect    ? typeSelect.value    : '';
			var selectedProduct = productSelect ? productSelect.value : '';
			var selectedCaps    = getCheckedCapabilities();
			var visibleCount    = 0;

			for (var i = 0; i < cards.length; i++) {
				var card            = cards[i];
				var cardType        = card.getAttribute('data-type')         || '';
				var cardCaps        = card.getAttribute('data-capabilities')  || '';
				var cardProducts    = card.getAttribute('data-products')      || '';
				var cardCapList     = cardCaps     ? cardCaps.split(' ')     : [];
				var cardProductList = cardProducts ? cardProducts.split(' ') : [];

				var typeMatch    = !selectedType    || cardType === selectedType;
				var productMatch = !selectedProduct || cardProductList.indexOf(selectedProduct) !== -1;

				var capMatch = true;
				if (selectedCaps.length > 0) {
					for (var j = 0; j < selectedCaps.length; j++) {
						if (cardCapList.indexOf(selectedCaps[j]) === -1) {
							capMatch = false;
							break;
						}
					}
				}

				if (typeMatch && productMatch && capMatch) {
					card.style.display = '';
					visibleCount++;
				} else {
					card.style.display = 'none';
				}
			}

			if (noResults) {
				noResults.style.display = visibleCount === 0 ? '' : 'none';
			}
		}

		function resetFilters() {
			if (typeSelect)    typeSelect.value    = '';
			if (productSelect) productSelect.value = '';
			for (var i = 0; i < capCheckboxes.length; i++) {
				capCheckboxes[i].checked = false;
			}
			// Close capabilities panel on reset.
			if (capToggle && capPanel) {
				capToggle.setAttribute('aria-expanded', 'false');
				capPanel.setAttribute('hidden', '');
			}
			applyFilters();
		}

		// ── Event listeners ───────────────────────────────────────────────────

		if (typeSelect)    typeSelect.addEventListener('change', applyFilters);
		if (productSelect) productSelect.addEventListener('change', applyFilters);
		for (var i = 0; i < capCheckboxes.length; i++) {
			capCheckboxes[i].addEventListener('change', applyFilters);
		}
		if (resetBtn) resetBtn.addEventListener('click', resetFilters);
	}

	function init() {
		var lists = document.querySelectorAll('.integration-list');
		for (var i = 0; i < lists.length; i++) {
			initIntegrationList(lists[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

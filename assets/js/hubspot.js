document.addEventListener('DOMContentLoaded', function () {
	const fields = [
		'utm_source', 'utm_medium', 'utm_campaign',
		'utm_term', 'utm_content', 'landing_page'
	];

	// Function to populate fields
	function populateFields() {
		fields.forEach(function (key) {
			const field = document.querySelector(`input[name="${key}"]`);
			const value = sessionStorage.getItem(key);
			if (field && value) {
				field.value = value;
				// Trigger change event for frameworks that require it
				field.dispatchEvent(new Event('change', { bubbles: true }));
			}
		});
	}

	// Execute immediately
	populateFields();

	// Also execute when new forms appear (for SPAs)
	const observer = new MutationObserver(function(mutations) {
		mutations.forEach(function(mutation) {
			if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
				// Check if new forms were added
				for (let node of mutation.addedNodes) {
					if (node.nodeType === 1) { // Element node
						if (node.tagName === 'FORM' || node.querySelector && node.querySelector('form')) {
							populateFields();
							break;
						}
					}
				}
			}
		});
	});

	observer.observe(document.body, { childList: true, subtree: true });
});

window.addEventListener('message', event => {
	// Get UTM Content Field
	var utmStaticContent = "";
	var postTitle = document.querySelector('h1').textContent;
	// Get Hubspot Form
	var formContainer = document.querySelector('.hbspt-form');
	// If the form exists on the page, set the hidden field values
	if(event.data.type === 'hsFormCallback' && event.data.eventName === 'onFormReady') {
		var utmContentField = document.getElementsByName("utm_content")[0];
		if(utmStaticContent != '') {
			utmContentField.value = utmStaticContent;
		} else {
			utmContentField.value = postTitle;
		}
	}
});
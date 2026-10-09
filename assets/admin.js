(function () {
	var excludeClass = 'mt-exclude-creditcard-stripe_creditcard';

	/**
	 * Keep card fields out of the merge tag menu on custom value inputs.
	 * The class is read when the menu opens and when autocomplete runs.
	 *
	 * @param {EventTarget|null} node
	 */
	function mark(node) {
		if (!node || !node.classList || !node.classList.contains('merge-tag-support')) {
			return;
		}

		if (!node.closest || !node.closest('.gform-settings-generic-map__custom')) {
			return;
		}

		node.classList.add(excludeClass);
	}

	document.addEventListener('focusin', function (event) {
		mark(event.target);
	}, true);

	document.addEventListener('click', function (event) {
		var button = event.target && event.target.closest ? event.target.closest('.open-list') : null;
		if (!button) {
			return;
		}

		var wrap = button.closest('.all-merge-tags');
		if (!wrap) {
			return;
		}

		mark(wrap.previousElementSibling);
	}, true);
})();

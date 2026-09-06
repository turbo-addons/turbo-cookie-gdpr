/**
 * Turbo Cookie — Frontend Consent JavaScript
 *
 * Handles: cookie read/write, consent state management,
 * banner/modal UI, script unblocking, GCM updates, REST logging.
 *
 * @package TurboCookie
 * @since   1.0.0
 */
(function() {
	'use strict';

	var TC = {
		config: null,
		sessionId: null,
		banner: null,
		modal: null,
		overlay: null,

		/**
		 * Initialize.
		 */
		init: function() {
			if (typeof turboCookieConfig === 'undefined') return;

			this.config = turboCookieConfig;
			this.banner = document.getElementById('turbo-cookie-banner');
			this.modal = document.getElementById('turbo-cookie-modal');
			this.overlay = document.getElementById('turbo-cookie-modal-overlay');

			if (!this.banner) return;

			this.sessionId = this.getOrCreateSessionId();
			this.bindEvents();
			this.checkConsent();
		},

		/**
		 * Bind all event listeners.
		 */
		bindEvents: function() {
			var self = this;

			// Delegate all button clicks.
			document.addEventListener('click', function(e) {
				var btn = e.target.closest('[data-turbo-cookie-action]');
				if (!btn) return;

				var action = btn.getAttribute('data-turbo-cookie-action');

				switch (action) {
					case 'accept-all':
						self.acceptAll();
						break;
					case 'accept-essential':
						self.acceptEssential();
						break;
					case 'decline':
						self.declineAll();
						break;
					case 'open-preferences':
						self.openModal();
						break;
					case 'close-modal':
						self.closeModal();
						break;
					case 'save-preferences':
						self.savePreferences();
						break;
					case 'accept-category':
						// Accept a single category from a placeholder button.
						var cat = btn.getAttribute('data-turbo-cookie-category');
						if (cat) self.acceptCategory(cat);
						break;
				}
			});

			// Close modal on overlay click.
			if (this.overlay) {
				this.overlay.addEventListener('click', function() {
					self.closeModal();
				});
			}

			// Escape key closes modal.
			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && self.modal && self.modal.style.display !== 'none') {
					self.closeModal();
				}
			});
		},

		/**
		 * Check existing consent state.
		 */
		checkConsent: function() {
			var consent = this.getConsent();

			if (!consent) {
				// No consent yet — show banner.
				this.showBanner();
				return;
			}

			// Check if consent version matches.
			if (consent.version !== this.config.reconsentVersion) {
				// Re-request consent.
				this.showBanner();
				return;
			}

			// Consent exists and is current — activate allowed scripts.
			this.activateScripts(consent.categories);
			this.updateGCM(consent.categories);
		},

		/**
		 * Accept all categories.
		 */
		acceptAll: function() {
			var categories = {};
			var allCats = this.config.categories;
			for (var slug in allCats) {
				if (allCats.hasOwnProperty(slug)) {
					categories[slug] = true;
				}
			}
			this.setConsent('accepted', categories);
			this.hideBanner();
			this.closeModal();
			this.activateScripts(categories);
			this.updateGCM(categories);
			this.logConsent('accepted', categories);
		},

		/**
		 * Accept essential cookies only — decline all non-essential.
		 */
		acceptEssential: function() {
			var categories = {};
			var allCats = this.config.categories;
			for (var slug in allCats) {
				if (allCats.hasOwnProperty(slug)) {
					categories[slug] = !!allCats[slug].required;
				}
			}
			this.setConsent('declined', categories);
			this.hideBanner();
			this.closeModal();
			this.updateGCM(categories);
			this.logConsent('declined', categories);
		},

		/**
		 * Decline all non-essential categories.
		 */
		declineAll: function() {
			var categories = {};
			var allCats = this.config.categories;

			for (var slug in allCats) {
				if (allCats.hasOwnProperty(slug)) {
					categories[slug] = !!allCats[slug].required;
				}
			}

			this.setConsent('declined', categories);
			this.hideBanner();
			this.closeModal();
			this.updateGCM(categories);
			this.logConsent('declined', categories);
		},

		/**
		 * Save preferences from modal toggles.
		 */
		savePreferences: function() {
			var categories = {};
			var allCats = this.config.categories;
			var allAccepted = true;
			var allDeclined = true;

			for (var slug in allCats) {
				if (!allCats.hasOwnProperty(slug)) continue;

				if (allCats[slug].required) {
					categories[slug] = true;
					continue;
				}

				var toggle = document.querySelector('#turbo-cat-' + slug);
				categories[slug] = toggle ? toggle.checked : false;

				if (categories[slug]) {
					allDeclined = false;
				} else {
					allAccepted = false;
				}
			}

			var action = allAccepted ? 'accepted' : (allDeclined ? 'declined' : 'partial');

			this.setConsent(action, categories);
			this.hideBanner();
			this.closeModal();
			this.activateScripts(categories);
			this.updateGCM(categories);
			this.logConsent(action, categories);
		},

		/**
		 * Show the consent banner.
		 */
		showBanner: function() {
			if (!this.banner) return;
			this.banner.style.display = '';
			// Trigger reflow then add visible class for animation.
			void this.banner.offsetHeight;
			this.banner.classList.add('turbo-cookie-visible');
			this.banner.setAttribute('aria-hidden', 'false');
		},

		/**
		 * Hide the consent banner.
		 */
		hideBanner: function() {
			if (!this.banner) return;
			this.banner.classList.remove('turbo-cookie-visible');
			this.banner.setAttribute('aria-hidden', 'true');

			setTimeout(function() {
				this.banner.style.display = 'none';
			}.bind(this), 400);
		},

		/**
		 * Open preferences modal.
		 */
		openModal: function() {
			if (!this.modal || !this.overlay) return;

			// Restore toggles from current consent.
			var consent = this.getConsent();
			if (consent && consent.categories) {
				for (var slug in consent.categories) {
					var toggle = document.querySelector('#turbo-cat-' + slug);
					if (toggle && !toggle.disabled) {
						toggle.checked = consent.categories[slug];
					}
				}
			}

			this.overlay.style.display = '';
			this.modal.style.display = '';
			void this.modal.offsetHeight;
			this.overlay.classList.add('turbo-cookie-visible');
			this.modal.classList.add('turbo-cookie-visible');

			// Trap focus.
			this.modal.setAttribute('aria-hidden', 'false');
			var firstBtn = this.modal.querySelector('button, input, [tabindex]');
			if (firstBtn) firstBtn.focus();
		},

		/**
		 * Close preferences modal.
		 */
		closeModal: function() {
			if (!this.modal || !this.overlay) return;

			this.overlay.classList.remove('turbo-cookie-visible');
			this.modal.classList.remove('turbo-cookie-visible');
			this.modal.setAttribute('aria-hidden', 'true');

			setTimeout(function() {
				this.overlay.style.display = 'none';
				this.modal.style.display = 'none';
			}.bind(this), 300);
		},

		/**
		 * Activate blocked scripts for allowed categories.
		 */
		activateScripts: function(categories) {
			// Activate blocked <script> tags.
			var blockedScripts = document.querySelectorAll('script[data-turbo-cookie-blocked="true"]');
			for (var i = 0; i < blockedScripts.length; i++) {
				var script = blockedScripts[i];
				var category = script.getAttribute('data-turbo-cookie-category');

				if (categories[category]) {
					this.unblockScript(script);
				}
			}

			// Activate blocked iframes, embeds, objects inside placeholders.
			var blockedEmbeds = document.querySelectorAll(
				'.turbo-cookie-placeholder [data-turbo-cookie-blocked="true"]'
			);
			for (var j = 0; j < blockedEmbeds.length; j++) {
				var el       = blockedEmbeds[j];
				var elCat    = el.getAttribute('data-turbo-cookie-category');
				var tagName  = el.tagName.toLowerCase();

				if (!categories[elCat]) continue;

				// iframe / embed — restore src.
				var realSrc = el.getAttribute('data-turbo-cookie-src');
				if (realSrc) {
					el.src = realSrc;
					el.removeAttribute('data-turbo-cookie-src');
				}

				// object — restore data attribute.
				var realData = el.getAttribute('data-turbo-cookie-data');
				if (realData) {
					el.setAttribute('data', realData);
					el.removeAttribute('data-turbo-cookie-data');
				}

				el.style.display = '';
				el.removeAttribute('data-turbo-cookie-blocked');
				el.removeAttribute('data-turbo-cookie-category');

				// Hide the placeholder overlay, show the content.
				var placeholder = el.closest('.turbo-cookie-placeholder');
				if (placeholder) {
					var overlay = placeholder.querySelector('.turbo-cookie-placeholder-inner');
					if (overlay) overlay.style.display = 'none';
				}
			}
		},

		/**
		 * Unblock a single script element.
		 */
		unblockScript: function(oldScript) {
			var newScript = document.createElement('script');

			// Copy attributes.
			for (var i = 0; i < oldScript.attributes.length; i++) {
				var attr = oldScript.attributes[i];
				if (attr.name === 'type') {
					newScript.type = 'text/javascript';
				} else if (attr.name.indexOf('data-turbo-cookie') === -1) {
					newScript.setAttribute(attr.name, attr.value);
				}
			}

			// If no type was set, ensure it's javascript.
			if (!newScript.hasAttribute('type')) {
				newScript.type = 'text/javascript';
			}

			// Copy inline content.
			if (oldScript.textContent) {
				newScript.textContent = oldScript.textContent;
			}

			// Replace in DOM.
			oldScript.parentNode.replaceChild(newScript, oldScript);
		},

		/**
		 * Update Google Consent Mode.
		 */
		updateGCM: function(categories) {
			if (!this.config.gcmEnabled) return;
			if (typeof gtag === 'undefined') return;
			if (typeof window.turboCookieGCMMapping === 'undefined') return;

			var mapping = window.turboCookieGCMMapping;
			var payload = {
				ad_storage: 'denied',
				ad_user_data: 'denied',
				ad_personalization: 'denied',
				analytics_storage: 'denied',
				functionality_storage: 'denied',
				personalization_storage: 'denied',
				security_storage: 'granted'
			};

			for (var cat in mapping) {
				if (mapping.hasOwnProperty(cat) && categories[cat]) {
					var signals = mapping[cat];
					for (var s = 0; s < signals.length; s++) {
						payload[signals[s]] = 'granted';
					}
				}
			}

			gtag('consent', 'update', payload);
		},

		/**
		 * Log consent to the REST API.
		 */
		logConsent: function(action, categories) {
			var self = this;
			var data = {
				action: action,
				categories: categories,
				session_id: this.sessionId,
				page_url: window.location.href
			};

			fetch(this.config.restUrl + 'consent', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.config.nonce
				},
				body: JSON.stringify(data)
			}).catch(function(err) {
				// Silent fail — consent still works client-side.
			});
		},

		/**
		 * Accept a single category (from placeholder button).
		 * Merges with existing consent, keeps other categories unchanged.
		 */
		acceptCategory: function(category) {
			var current    = this.getConsent();
			var categories = {};
			var allCats    = this.config.categories;

			// Start from existing consent if available.
			if (current && current.categories) {
				categories = current.categories;
			} else {
				for (var slug in allCats) {
					if (allCats.hasOwnProperty(slug)) {
						categories[slug] = !!allCats[slug].required;
					}
				}
			}

			// Grant the requested category.
			categories[category] = true;

			// Determine overall action.
			var allAccepted = true;
			for (var s in allCats) {
				if (allCats.hasOwnProperty(s) && !allCats[s].required && !categories[s]) {
					allAccepted = false;
					break;
				}
			}
			var action = allAccepted ? 'accepted' : 'partial';

			this.setConsent(action, categories);
			this.hideBanner();
			this.closeModal();
			this.activateScripts(categories);
			this.updateGCM(categories);
			this.logConsent(action, categories);
		},

		/* ===========================================
		   Cookie Helpers
		   =========================================== */

		/**
		 * Set the consent cookie.
		 */
		setConsent: function(action, categories) {
			var data = {
				action: action,
				categories: categories,
				version: this.config.reconsentVersion,
				timestamp: new Date().toISOString()
			};

			var encoded = btoa(JSON.stringify(data));
			var expires = new Date();
			expires.setDate(expires.getDate() + parseInt(this.config.consentExpiry, 10));

			document.cookie = this.config.cookieName + '=' + encoded +
				'; expires=' + expires.toUTCString() +
				'; path=/; SameSite=Lax; Secure';
		},

		/**
		 * Get the consent cookie value.
		 */
		getConsent: function() {
			var name = this.config.cookieName + '=';
			var cookies = document.cookie.split(';');

			for (var i = 0; i < cookies.length; i++) {
				var c = cookies[i].trim();
				if (c.indexOf(name) === 0) {
					try {
						var value = c.substring(name.length);
						return JSON.parse(atob(value));
					} catch (e) {
						return null;
					}
				}
			}
			return null;
		},

		/**
		 * Get or create a persistent session ID for this browser.
		 */
		getOrCreateSessionId: function() {
			var key = 'turbo_cookie_session';
			var stored = null;

			try {
				stored = localStorage.getItem(key);
			} catch (e) {}

			if (stored) return stored;

			var id = this.generateUUID();
			try {
				localStorage.setItem(key, id);
			} catch (e) {}

			return id;
		},

		/**
		 * Generate a UUID v4.
		 */
		generateUUID: function() {
			return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
				var r = Math.random() * 16 | 0;
				var v = c === 'x' ? r : (r & 0x3 | 0x8);
				return v.toString(16);
			});
		}
	};

	// Initialize when DOM is ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() { TC.init(); });
	} else {
		TC.init();
	}

	// Expose for external use (e.g., re-open preferences programmatically).
	window.TurboCookie = {
		openPreferences: function() { TC.openModal(); },
		getConsent: function() { return TC.getConsent(); },
		hasConsent: function() { return !!TC.getConsent(); }
	};

})();

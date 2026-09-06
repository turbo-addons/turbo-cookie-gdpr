/**
 * Turbo Cookie — Admin JavaScript
 *
 * @package TurboCookie
 * @since   1.0.0
 */
(function($) {
	'use strict';

	var TurboCookieAdmin = {

		init: function() {
			this.initColorPickers();
			this.bindForms();
			this.bindWizard();
			this.bindLogs();
		},

		/**
		 * Initialize WordPress color pickers.
		 */
		initColorPickers: function() {
			$('.turbo-color-picker').wpColorPicker();
		},

		/**
		 * Bind form submissions via AJAX.
		 */
		bindForms: function() {
			// Settings form.
			$('#turbo-cookie-settings-form').on('submit', function(e) {
				e.preventDefault();
				TurboCookieAdmin.saveForm($(this), 'turbo_cookie_save_settings');
			});

			// Banner form.
			$('#turbo-cookie-banner-form').on('submit', function(e) {
				e.preventDefault();
				TurboCookieAdmin.saveForm($(this), 'turbo_cookie_save_banner');
			});

			// Categories form.
			$('#turbo-cookie-categories-form').on('submit', function(e) {
				e.preventDefault();
				TurboCookieAdmin.saveForm($(this), 'turbo_cookie_save_categories');
			});
		},

		/**
		 * Save form via AJAX.
		 */
		saveForm: function($form, action) {
			var $btn = $form.find('button[type="submit"]');
			var $indicator = $form.find('.turbo-cookie-save-indicator');
			var data = $form.serialize();

			$btn.prop('disabled', true).text('Saving...');

			$.ajax({
				url: turboCookieAdmin.ajaxUrl,
				type: 'POST',
				data: data + '&action=' + action + '&nonce=' + turboCookieAdmin.nonce,
				success: function(response) {
					if (response.success) {
						$indicator.text(turboCookieAdmin.strings.saved).addClass('show');
						setTimeout(function() {
							$indicator.removeClass('show');
						}, 3000);
					} else {
						alert(response.data || turboCookieAdmin.strings.error);
					}
				},
				error: function() {
					alert(turboCookieAdmin.strings.error);
				},
				complete: function() {
					$btn.prop('disabled', false).text('Save');
				}
			});
		},

		/**
		 * Wizard navigation.
		 */
		bindWizard: function() {
			var $wizard = $('.turbo-cookie-wizard');
			if (!$wizard.length) return;

			var currentStep = 1;
			var totalSteps = 3;

			$wizard.on('click', '.wizard-next', function() {
				if (currentStep < totalSteps) {
					currentStep++;
					TurboCookieAdmin.updateWizardStep($wizard, currentStep, totalSteps);
				}
			});

			$wizard.on('click', '.wizard-prev', function() {
				if (currentStep > 1) {
					currentStep--;
					TurboCookieAdmin.updateWizardStep($wizard, currentStep, totalSteps);
				}
			});

			$wizard.on('click', '.wizard-finish', function() {
				TurboCookieAdmin.completeWizard($wizard);
			});
		},

		/**
		 * Update wizard step display.
		 */
		updateWizardStep: function($wizard, step, total) {
			// Update step visibility.
			$wizard.find('.wizard-step').removeClass('active');
			$wizard.find('.wizard-step[data-step="' + step + '"]').addClass('active');

			// Update dots.
			$wizard.find('.step-dot').removeClass('active completed');
			$wizard.find('.step-dot').each(function() {
				var dotStep = parseInt($(this).data('step'));
				if (dotStep < step) {
					$(this).addClass('completed');
				} else if (dotStep === step) {
					$(this).addClass('active');
				}
			});

			// Update buttons.
			$wizard.find('.wizard-prev').toggle(step > 1);
			$wizard.find('.wizard-next').toggle(step < total);
			$wizard.find('.wizard-finish').toggle(step === total);
		},

		/**
		 * Complete the onboarding wizard.
		 */
		completeWizard: function($wizard) {
			var $btn = $wizard.find('.wizard-finish');
			$btn.prop('disabled', true).text('Setting up...');

			var data = {
				action: 'turbo_cookie_complete_onboarding',
				nonce: turboCookieAdmin.nonce,
				position: $wizard.find('[name="position"]').val(),
				message: $wizard.find('[name="message"]').val(),
				privacy_policy_url: $wizard.find('[name="privacy_policy_url"]').val(),
				script_blocking: $wizard.find('[name="script_blocking"]').is(':checked') ? 1 : 0,
				gcm_enabled: $wizard.find('[name="gcm_enabled"]').is(':checked') ? 1 : 0,
				consent_log_enabled: $wizard.find('[name="consent_log_enabled"]').is(':checked') ? 1 : 0
			};

			$.ajax({
				url: turboCookieAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success && response.data.redirect) {
						window.location.href = response.data.redirect;
					}
				},
				error: function() {
					alert(turboCookieAdmin.strings.error);
					$btn.prop('disabled', false).text('Finish Setup');
				}
			});
		},

		/**
		 * Bind consent log actions.
		 */
		bindLogs: function() {
			// Clear all logs.
			$('#turbo-cookie-clear-logs').on('click', function() {
				if (!confirm(turboCookieAdmin.strings.confirm_clear)) {
					return;
				}

				$.ajax({
					url: turboCookieAdmin.restUrl + 'logs',
					type: 'DELETE',
					data: JSON.stringify({ all: true }),
					contentType: 'application/json',
					headers: { 'X-WP-Nonce': turboCookieAdmin.restNonce },
					success: function(response) {
						if (response.success) {
							location.reload();
						}
					}
				});
			});

			// Export logs.
			$('#turbo-cookie-export-logs').on('click', function() {
				$.ajax({
					url: turboCookieAdmin.ajaxUrl,
					type: 'POST',
					data: {
						action: 'turbo_cookie_export_logs',
						nonce: turboCookieAdmin.nonce
					},
					success: function(response) {
						if (response.success && response.data.csv) {
							TurboCookieAdmin.downloadCSV(response.data.csv, 'turbo-cookie-consent-logs.csv');
						}
					}
				});
			});
		},

		/**
		 * Trigger CSV download.
		 */
		downloadCSV: function(csv, filename) {
			var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
			var link = document.createElement('a');
			link.href = URL.createObjectURL(blob);
			link.download = filename;
			link.style.display = 'none';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
		}
	};

	$(document).ready(function() {
		TurboCookieAdmin.init();
	});

})(jQuery);

/* ── Cookie Policy Page Generator ─────────────────────────── */
jQuery(document).ready(function ($) {
	$('#turbo-cookie-create-policy-page').on('click', function () {
		var $btn = $(this);
		var $ind = $('#turbo-policy-indicator');

		$btn.prop('disabled', true).text('Creating…');
		$ind.text('').css('color', '');

		$.post(
			turboCookieAdmin.ajaxUrl,
			{
				action: 'turbo_cookie_create_policy_page',
				nonce:  turboCookieAdmin.nonce
			}
		)
		.done(function (res) {
			if (res.success) {
				$ind.text('✓ ' + res.data.message).css('color', '#22c55e');
				setTimeout(function () {
					location.reload();
				}, 1200);
			} else {
				$ind.text('✗ ' + (res.data || 'Error creating page.')).css('color', '#ef4444');
				$btn.prop('disabled', false).text('Create Cookie Policy Page');
			}
		})
		.fail(function () {
			$ind.text('Request failed. Please try again.').css('color', '#ef4444');
			$btn.prop('disabled', false).text('Create Cookie Policy Page');
		});
	});
});


/* ── Manage Cookies Page ───────────────────────────────────── */
jQuery(document).ready(function ($) {

	if (!$('#tc-add-cookie-form').length) return;

	var nonce = turboCookieAdmin.nonce;
	var ajax  = turboCookieAdmin.ajaxUrl;
	var rowIdx = parseInt($('#tc-declared-tbody tr').length, 10);

	/* Add from Known Service chip */
	$('.turbo-cookie-service-chip').on('click', function () {
		var svc = $(this).data('service');
		if (!svc || !svc.cookies || !svc.cookies.length) return;

		var $msg = $('#tc-service-add-msg');
		$msg.text('Adding…').css('color', '');

		$.post(ajax, {
			action:      'turbo_cookie_save_cookie',
			nonce:       nonce,
			cookies_json: JSON.stringify(svc.cookies)
		})
		.done(function (res) {
			if (res.success) {
				$msg.text('✓ ' + res.data.message).css('color', '#22c55e');
				appendRows(svc.cookies);
				updateCount(res.data.total);
			} else {
				$msg.text('✗ ' + (res.data || 'Error')).css('color', '#ef4444');
			}
			setTimeout(function () { $msg.text(''); }, 3000);
		});
	});

	/* Add single cookie form */
	$('#tc-add-cookie-form').on('submit', function (e) {
		e.preventDefault();
		var name = $('#tc-new-name').val().trim();
		if (!name) { alert('Cookie name is required.'); return; }

		var $msg = $('#tc-add-cookie-msg');
		$msg.text('Saving…').css('color', '');

		var cookie = {
			name:        name,
			provider:    $('#tc-new-provider').val().trim(),
			category:    $('#tc-new-category').val(),
			duration:    $('#tc-new-duration').val().trim(),
			description: $('#tc-new-description').val().trim()
		};

		$.post(ajax, {
			action:      'turbo_cookie_save_cookie',
			nonce:       nonce,
			name:        cookie.name,
			provider:    cookie.provider,
			category:    cookie.category,
			duration:    cookie.duration,
			description: cookie.description
		})
		.done(function (res) {
			if (res.success) {
				$msg.text('✓ ' + res.data.message).css('color', '#22c55e');
				appendRows([cookie]);
				updateCount(res.data.total);
				$('#tc-new-name, #tc-new-provider, #tc-new-duration, #tc-new-description').val('');
				$('#tc-new-category').val('functional');
			} else {
				$msg.text('✗ ' + (res.data || 'Error')).css('color', '#ef4444');
			}
			setTimeout(function () { $msg.text(''); }, 3000);
		});
	});

	/* Delete cookie */
	$(document).on('click', '.tc-delete-cookie', function () {
		if (!confirm(turboCookieAdmin.strings.confirm_delete_cookie || 'Delete this cookie?')) return;

		var idx  = $(this).data('index');
		var $row = $('#tc-row-' + idx);
		var self = this;

		$.post(ajax, {
			action: 'turbo_cookie_delete_cookie',
			nonce:  nonce,
			index:  idx
		})
		.done(function (res) {
			if (res.success) {
				$row.fadeOut(200, function () { $(this).remove(); reindexRows(); });
			}
		});
	});

	/* Append rows to table */
	function appendRows(cookies) {
		var $tbody = $('#tc-declared-tbody');
		var $table = $('#tc-declared-table');
		var $empty = $('#tc-empty-msg');

		$empty.hide();
		$table.show();

		cookies.forEach(function (c) {
			var row = '<tr id="tc-row-' + rowIdx + '">'
				+ '<td><code>' + esc(c.name) + '</code></td>'
				+ '<td>' + esc(c.provider || '') + '</td>'
				+ '<td><span class="chip-cat chip-cat-' + esc(c.category) + '"></span>' + esc(capitalise(c.category)) + '</td>'
				+ '<td>' + esc(c.duration || '') + '</td>'
				+ '<td>' + esc(c.description || '') + '</td>'
				+ '<td><button type="button" class="button button-small button-link-delete tc-delete-cookie" data-index="' + rowIdx + '">Delete</button></td>'
				+ '</tr>';
			$tbody.append(row);
			rowIdx++;
		});
	}

	function reindexRows() {
		$('#tc-declared-tbody tr').each(function (i) {
			$(this).attr('id', 'tc-row-' + i);
			$(this).find('.tc-delete-cookie').data('index', i).attr('data-index', i);
		});
		rowIdx = $('#tc-declared-tbody tr').length;
		updateCount(rowIdx);
		if (rowIdx === 0) {
			$('#tc-declared-table').hide();
			$('#tc-empty-msg').show();
		}
	}

	function updateCount(n) {
		$('#tc-cookie-count').text('(' + n + ')');
	}

	function esc(str) {
		return $('<span>').text(str).html();
	}

	function capitalise(str) {
		return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
	}
});

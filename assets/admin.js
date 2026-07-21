/* futuri Cookies — administrace */
jQuery(function ($) {
	'use strict';

	// Záložky
	$('.fc-tabs .nav-tab').on('click', function (e) {
		e.preventDefault();
		var target = $(this).attr('href');
		$('.fc-tabs .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.fc-tab-panel').hide();
		$(target).show();
	});

	// Color picker
	$('.fc-color').wpColorPicker();

	// Repeater skriptů — čítač se nastaví jednou a jen roste,
	// takže odebrání řádku nemůže způsobit kolizi indexů.
	var $wrap = $('#fc-scripts');
	var counter = $wrap.find('.fc-script-row').length;

	$('#fc-script-add').on('click', function () {
		var idx = counter++;
		var html =
			'<div class="fc-script-row">' +
				'<div class="fc-script-top">' +
					'<input type="text" name="scripts[' + idx + '][name]" placeholder="Název (např. Facebook Pixel)" class="regular-text">' +
					'<select name="scripts[' + idx + '][category]">' +
						'<option value="functional">Preferenční</option>' +
						'<option value="analytics" selected>Analytické</option>' +
						'<option value="marketing">Marketingové</option>' +
					'</select>' +
					'<select name="scripts[' + idx + '][position]">' +
						'<option value="head">&lt;head&gt;</option>' +
						'<option value="body">&lt;body&gt; (patička)</option>' +
					'</select>' +
					'<button type="button" class="button fc-script-remove">Odebrat</button>' +
				'</div>' +
				'<textarea name="scripts[' + idx + '][code]" rows="4" class="large-text code" placeholder="&lt;script&gt;...&lt;/script&gt; nebo čistý JS"></textarea>' +
			'</div>';
		$wrap.append(html);
	});

	$wrap.on('click', '.fc-script-remove', function () {
		$(this).closest('.fc-script-row').remove();
	});
});

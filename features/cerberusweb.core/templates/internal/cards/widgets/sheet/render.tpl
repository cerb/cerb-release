<div id="cardWidget{$widget->getUniqueId($card_context_id)}">
    {if 'fieldsets' == $layout.style}
        {include file="devblocks:cerberusweb.core::ui/sheets/render_fieldsets.tpl"}
    {elseif in_array($layout.style, ['columns','grid'])}
        {include file="devblocks:cerberusweb.core::ui/sheets/render_grid.tpl"}
    {else}
        {include file="devblocks:cerberusweb.core::ui/sheets/render.tpl"}
    {/if}

	{if $widget->extension_params.toolbar_kata}
		<div data-cerb-toolbar style="margin-top:0.5em;">
			{$widget_ext->renderToolbar($widget, $card_context_id)}
		</div>
	{/if}
</div>

<script type="text/javascript">
$(function() {
	var $widget = $('#cardWidget{$widget->getUniqueId($card_context_id)}');
	var $sheet = $widget.find('.cerb-sheet, .cerb-data-sheet, .cerb-sheet-grid, .cerb-sheet-columns');
	var $sheet_toolbar = $widget.find('[data-cerb-toolbar]');
	var $popup = genericAjaxPopupFind($widget);

	$sheet.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
		.on('cerb-peek-saved cerb-peek-deleted', function(e) {
			e.stopPropagation();
			$popup.triggerHandler($.Event('cerb-widget-refresh', { widget_id: {$widget->id} }));
		})
	;

	$sheet.on('cerb-sheet--selections-changed', function(e) {
		e.stopPropagation();

		// Update the toolbar
		var formData = new FormData();
		formData.set('c', 'profiles');
		formData.set('a', 'invoke');
		formData.set('module', 'card_widget');
		formData.set('action', 'invokeWidget');
		formData.set('widget_id', '{$widget->id}');
		formData.set('invoke_action', 'renderToolbar');
		formData.set('card_context_id', '{$card_context_id}');

		if(e.hasOwnProperty('row_selections')) {
			for (var i in e.row_selections) {
				formData.append('row_selections[]', e.row_selections[i]);
			}
		}

		$sheet_toolbar.html(Devblocks.getSpinner().css('max-width', '16px'));

		genericAjaxPost(formData, null, null, function(html) {
			$sheet_toolbar
				.html(html)
				.triggerHandler('cerb-toolbar--refreshed')
			;
		});
	});

	$sheet.on('cerb-sheet--page-changed', function(e) {
		e.stopPropagation();

		var evt = $.Event('cerb-widget-refresh');
		evt.widget_id = {$widget->id};
		evt.refresh_options = {
			'page': e.page
		};

		$popup.triggerHandler(evt);
	});

	var doneFunc = function(e) {
		e.stopPropagation();

		var $target = e.trigger;

		var done_params = [];

		if($target.is('.cerb-bot-trigger')) {
			done_params = new URLSearchParams($target.attr('data-interaction-done'));
		} else {
			return;
		}

		if(!done_params.has('refresh_widgets[]'))
			return;

		var refresh = done_params.getAll('refresh_widgets[]');

		var widget_ids = [];

		if(-1 !== $.inArray('all', refresh)) {
			// Everything
		} else {
			$popup.find('.cerb-card-widget')
				.filter(function() {
					var $this = $(this);
					var name = $this.attr('data-widget-name');

					if(undefined === name)
						return false;

					return -1 !== $.inArray(name, refresh);
				})
				.each(function() {
					var $this = $(this);
					var widget_id = parseInt($this.attr('data-widget-id'));

					if(widget_id)
						widget_ids.push(widget_id);
				})
			;

			// If nothing to do, abort
			if(0 === widget_ids.length)
				widget_ids = [-1];
		}

		var evt = $.Event('cerb-widgets-refresh', {
			widget_ids: widget_ids,
			refresh_options: { }
		});

		$popup.triggerHandler(evt);
	}

	$sheet_toolbar.cerbToolbar({
		caller: {
			name: 'cerb.toolbar.cardWidget.sheet',
			params: {
				record_type: '{$card_context}',
				record_id: '{$card_context_id}',
				widget_id: '{$widget->id}'
			}
		},
		start: function(formData) {
		},
		done: doneFunc
	});
});
</script>
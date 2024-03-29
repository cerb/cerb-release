{$is_writeable = Context_WorkspacePage::isWriteableByActor($page, $active_worker)}

<div style="margin-bottom:5px;">
	{include file="devblocks:cerberusweb.core::internal/dashboards/prompts/render.tpl" prompts=$prompts}
	
	{if $is_writeable}
	<div style="display:inline-block;vertical-align:middle;" class="cerb-no-print">
		{if $active_worker->hasPriv("contexts.{CerberusContexts::CONTEXT_WORKSPACE_WIDGET}.create")}<button id="btnWorkspaceTabAddWidget{$model->id}" type="button" class="cerb-peek-trigger" data-context="{CerberusContexts::CONTEXT_WORKSPACE_WIDGET}" data-context-id="0" data-edit="tab:{$model->id}" data-width="75%"><span class="glyphicons glyphicons-circle-plus"></span> {'common.widget.add'|devblocks_translate|capitalize}</button>{/if}
		<button id="btnWorkspaceTabEditDashboard{$model->id}" type="button"><span class="glyphicons glyphicons-edit"></span> {'common.dashboard.edit'|devblocks_translate|capitalize}</button>
	</div>
	{/if}
</div>

{if 'sidebar_left' == $layout}
	<div id="workspaceTab{$model->id}" class="cerb-workspace-layout cerb-workspace-layout--sidebar-left" style="vertical-align:top;display:flex;flex-flow:row wrap;">
		<div data-layout-zone="sidebar" class="cerb-workspace-layout-zone" style="flex:1 1 33%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.sidebar item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
		
		<div data-layout-zone="content" class="cerb-workspace-layout-zone" style="flex:2 2 66%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.content item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
	</div>
{elseif 'sidebar_right' == $layout}
	<div id="workspaceTab{$model->id}" class="cerb-workspace-layout cerb-workspace-layout--sidebar-right" style="vertical-align:top;display:flex;flex-flow:row wrap;">
		<div data-layout-zone="content" class="cerb-workspace-layout-zone cerb-workspace-layout-zone--content" style="flex:2 2 66%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.content item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
		
		<div data-layout-zone="sidebar" class="cerb-workspace-layout-zone cerb-workspace-layout-zone--sidebar" style="flex:1 1 33%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.sidebar item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
	</div>
{elseif 'thirds' == $layout}
	<div id="workspaceTab{$model->id}" class="cerb-workspace-layout cerb-workspace-layout--thirds" style="vertical-align:top;display:flex;flex-flow:row wrap;">
		<div data-layout-zone="left" class="cerb-workspace-layout-zone cerb-workspace-layout-zone--left" style="flex:1 1 33%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.left item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
		
		<div data-layout-zone="center" class="cerb-workspace-layout-zone cerb-workspace-layout-zone--center" style="flex:1 1 33%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.center item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
		
		<div data-layout-zone="right" class="cerb-workspace-layout-zone cerb-workspace-layout-zone--right" style="flex:1 1 33%;min-width:345px;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.right item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
	</div>
{else}
	<div id="workspaceTab{$model->id}" class="cerb-workspace-layout cerb-workspace-layout--content" style="vertical-align:top;display:flex;flex-flow:row wrap;">
		<div data-layout-zone="content" class="cerb-workspace-layout-zone" style="flex:1 1 100%;overflow-x:hidden;">
			<div class="cerb-workspace-layout-zone--widgets" style="padding:2px;vertical-align:top;display:flex;flex-flow:row wrap;min-height:100px;">
			{foreach from=$zones.content item=widget name=widgets}
				{include file="devblocks:cerberusweb.core::internal/workspaces/widgets/render.tpl" widget=$widget}
			{/foreach}
			</div>
		</div>
	</div>
{/if}

<script type="text/javascript">
$(function() {
	var $container = $('#workspaceTab{$model->id}');
	var $add_button = $('#btnWorkspaceTabAddWidget{$model->id}');
	var $edit_button = $('#btnWorkspaceTabEditDashboard{$model->id}');
	
	// Drag
	{if $is_writeable}
	$container.find('.cerb-workspace-layout-zone--widgets')
		.sortable({
			tolerance: 'pointer',
			cursorAt: { top: 5, left: 5 },
			items: '.cerb-workspace-widget',
			helper: function(event, element) {
				return element.clone()
					.css('outline','2px dashed gray')
					.css('outline-offset','-2px')
					.css('background-color', 'var(--cerb-color-background)')
					;
			},
			placeholder: 'cerb-widget-drag-placeholder',
			forceHelperSize: true,
			forcePlaceholderSize: true,
			handle: '.cerb-workspace-widget--header .glyphicons-menu-hamburger',
			connectWith: '.cerb-workspace-layout-zone--widgets',
			opacity: 0.7,
			start: function(event, ui) {
				ui.placeholder.css('flex', ui.item.css('flex'));
				$container.find('.cerb-workspace-layout-zone--widgets')
					.css('outline', '2px dashed orange')
					.css('outline-offset', '-3px')
					.css('background-color', 'var(--cerb-color-background-contrast-250)')
					.css('min-height', '100px')
					;
			},
			stop: function(event, ui) {
				$container.find('.cerb-workspace-layout-zone--widgets')
					.css('outline', '')
					.css('outline-offset', '')
					.css('background-color', '')
					.css('min-height', 'initial')
					;
			},
			//receive: function(e, ui) {},
			update: function(event, ui) {
				$container.trigger('cerb-reorder');
			}
		})
		;
	{/if}
	
	$container.on('cerb-reorder', function(e) {
		var formData = new FormData();
		formData.set('c', 'profiles');
		formData.set('a', 'invoke');
		formData.set('module', 'workspace_widget');
		formData.set('action', 'reorderWidgets');
		formData.set('tab_id', '{$model->id}');

		// Zones
		$container.find('> .cerb-workspace-layout-zone')
			.each(function(d) {
				var $cell = $(this);
				var zone = $cell.attr('data-layout-zone');
				var ids = $cell.find('.cerb-workspace-widget').map(function(d) { return $(this).attr('data-widget-id'); });

				formData.append('zones[' + zone + ']', $.makeArray(ids));
			})
			;

		genericAjaxPost(formData);
	});
	
	$container.on('cerb-widget-refresh', function(e) {
		var widget_id = e.widget_id;
		var refresh_options = (e.refresh_options && typeof e.refresh_options == 'object') ? e.refresh_options : {};

		async.series([ async.apply(loadWidgetFunc, widget_id, false, refresh_options) ], function(err, json) {
			// Done
		});
	});

	$container.on('cerb-widgets-refresh', function(e) {
		var widget_ids = (e.widget_ids && $.isArray(e.widget_ids)) ? e.widget_ids : [];
		var refresh_options = (e.refresh_options && typeof e.refresh_options == 'object') ? e.refresh_options : { };

		var jobs = [];

		$container.find('.cerb-workspace-widget').each(function() {
			var $widget = $(this);
			var widget_id = parseInt($widget.attr('data-widget-id'));

			// If we're refreshing this widget or all widgets
			if(widget_id && (0 === widget_ids.length || -1 !== $.inArray(widget_id, widget_ids))) {
				jobs.push(
					async.apply(loadWidgetFunc, widget_id, true, refresh_options)
				);
			}
		});

		async.parallelLimit(jobs, 2, function(err, json) {
			// Done
		});
	});

	var addEvents = function($target) {
		var $menu = $target.find('.cerb-workspace-widget--menu');
		var $menu_link = $target.find('.cerb-workspace-widget--link');
		var $handle = $target.find('.cerb-workspace-widget--header .glyphicons-menu-hamburger');

		{if $is_writeable}
		$target.hoverIntent({
			interval: 50,
			timeout: 250,
			over: function (e) {
				$handle.show();
			},
			out: function (e) {
				$handle.hide();
			}
		});
		{/if}

		$menu
			.menu({
				select: function(event, ui) {
					var $li = $(ui.item);
					$li.closest('ul').hide();
					
					var $widget = $li.closest('.cerb-workspace-widget');
					var widget_id = $widget.attr('data-widget-id');
					
					if($li.is('.cerb-workspace-widget-menu--edit')) {
						$li.clone()
							.cerbPeekTrigger()
							.on('cerb-peek-saved', function(e) {
								// [TODO] Check the event type
								async.series([ async.apply(loadWidgetFunc, e.id, true, {}) ], function(err, json) {
									// Done
								});
							})
							.on('cerb-peek-deleted', function(e) {
								$('#workspaceWidget' + e.id).closest('.cerb-workspace-widget').remove();
								$container.trigger('cerb-reorder');
							})
							.click()
							;
						
					} else if($li.is('.cerb-workspace-widget-menu--refresh')) {
						async.series([ async.apply(loadWidgetFunc, widget_id, false, {}) ], function(err, json) {
							// Done
						});
						
					} else if($li.is('.cerb-workspace-widget-menu--export-data')) {
						genericAjaxPopup('export_data', 'c=profiles&a=invoke&module=workspace_widget&action=exportWidgetData&id=' + widget_id, null, false);
						
					} else if($li.is('.cerb-workspace-widget-menu--export-widget')) {
						genericAjaxPopup('export_widget', 'c=profiles&a=invoke&module=workspace_widget&action=exportWidget&id=' + widget_id, null, false);
						
					}
				}
			})
			;
		
		$menu_link.on('click', function(e) {
			e.stopPropagation();
			$(this).closest('.cerb-workspace-widget').find('.cerb-workspace-widget--menu').toggle();
		});
		
		return $target;
	}

	{if $is_writeable}
	$add_button
		.cerbPeekTrigger()
		.on('cerb-peek-saved', function(e) {
			var $zone = $container.find('> .cerb-workspace-layout-zone:first > .cerb-workspace-layout-zone--widgets:first');
			var $placeholder = $('<div class="cerb-workspace-widget"/>').hide().prependTo($zone);
			var $widget = $('<div/>').attr('id', 'workspaceWidget' + e.id).appendTo($placeholder);
			
			async.series([ async.apply(loadWidgetFunc, e.id, true, {}) ], function(err, json) {
				$container.trigger('cerb-reorder');
			});
		})
		;
	
	$edit_button
		.on('click', function() {
			var $workspace = $('#frmWorkspacePage{$model->workspace_page_id}');
			$workspace.find('a.edit-tab').click();
		})
		;
	{/if}
	
	var loadWidgetFunc = function(widget_id, is_full, refresh_options, callback) {
		var $widget = $('#workspaceWidget' + widget_id).fadeTo('fast', 0.3);

		if(is_full) {
			Devblocks.getSpinner().prependTo($widget);
		} else {
			Devblocks.getSpinner(true).prependTo($widget);
		}

		var formData;

		if(refresh_options instanceof FormData) {
			formData = refresh_options;
		} else {
			formData = new FormData();
		}

		formData.set('c', 'profiles');
		formData.set('a', 'invoke');
		formData.set('module', 'workspace_widget');
		formData.set('action', 'renderWidget');
		formData.set('id', widget_id);
		formData.set('full', is_full ? '1' : '0');

		if(refresh_options instanceof Object) {
			Devblocks.objectToFormData(refresh_options, formData);
		}

		genericAjaxPost(formData, '', '', function(html) {
			if('string' !== typeof html || 0 === html.length) {
				$widget.empty();

				$('<div/>')
					.text('Error: Widget failed to load.')
					.css('margin-bottom', '25px')
					.appendTo($widget)
				;
				
				if(is_full) {
					var $parent = $widget.closest('.cerb-workspace-widget');
					var $clone = $parent.clone();
					
					addEvents($clone).insertBefore(
						$widget.closest('.cerb-workspace-widget').hide()
					);

					$widget.closest('.cerb-workspace-widget').remove();
				}
				
			} else {
				try {
					if(is_full) {
						addEvents($(html)).insertBefore(
							$widget.attr('id',null).closest('.cerb-workspace-widget').hide()
						);
						
						$widget.closest('.cerb-workspace-widget').remove();
					} else {
						$widget.html(html);
					}
				} catch(e) {
					if(console)
						console.error(e);
				}
			}

			$widget.fadeTo('fast', 1.0);
			callback();
		});
	};
	
	clearInterval(window.dashboardTimer{$model->id});
	
	var tick = function() {
		var $dashboard = $('#workspaceTab{$model->id}');
		
		if($dashboard.length === 0 || !$dashboard.is(':visible')) {
			clearInterval(window.dashboardTimer{$model->id});
			delete window.dashboardTimer{$model->id};
			return;
		}
		
		$dashboard.find('.cerb-workspace-widget').each(function() {
			$(this).triggerHandler('cerb-dashboard-heartbeat');
		});
	};
	
	window.dashboardTimer{$model->id} = setInterval(tick, 1000);
	
	$container.triggerHandler('cerb-widgets-refresh');
});
</script>
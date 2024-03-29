{if $prompts}
<form id="tabFilters{$tab->id}" action="{devblocks_url}{/devblocks_url}" method="POST" onsubmit="return false;" style="padding:5px 10px;display:inline-block;">
	<input type="hidden" name="c" value="profiles">
	<input type="hidden" name="a" value="invoke">
	<input type="hidden" name="module" value="workspace_tab">
	<input type="hidden" name="action" value="saveDashboardTabPrefs">
	<input type="hidden" name="tab_id" value="{$tab->id}">
	
	<div style="margin:5px 10px 0 0;">
		{include file="devblocks:cerberusweb.core::internal/dashboards/prompts/prompts.tpl" prompts=$prompts}

		<div style="display:inline-block;vertical-align:middle;">
			<button type="button" class="cerb-filter-editor--save"><span class="glyphicons glyphicons-refresh"></span> {'common.update'|devblocks_translate|capitalize}</button>
			<button type="button" class="cerb-filter-editor--reset">{'common.reset'|devblocks_translate|capitalize}</button>
		</div>
	</div>
</form>
{/if}

<script type="text/javascript">
$(function() {
	var $frm = $('#tabFilters{$tab->id}');
	
	$frm.find('.cerb-filter-editor--save')
		.on('click', function(e) {
			e.stopPropagation();
			genericAjaxPost($frm, '', '', function() {
				// Reload widgets
				var $container = $('#workspaceTab{$tab->id}');
				$container.triggerHandler('cerb-widgets-refresh');
			});
		})
	;
	
	$frm.find('.cerb-filter-editor--reset')
		.on('click', function(e) {
			e.stopPropagation();

			var formData = new FormData($frm[0]);
			formData.set('reset', '1');
			
			// Reset the dashboard prefs
			genericAjaxPost(formData, '', '', function() {
				// Reload
				var $tabs = $('#workspaceTab{$tab->id}').closest('.ui-tabs');
				$tabs.tabs('load', $tabs.tabs('option','active'));
			});
		})
	;
})
</script>
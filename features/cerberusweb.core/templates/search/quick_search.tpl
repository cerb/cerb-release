{$uniqid = uniqid()}
{if $view instanceof IAbstractView_QuickSearch}

<form action="javascript:;" method="post" id="{$uniqid}" class="quick-search">
	<input type="hidden" name="c" value="search">
	<input type="hidden" name="a" value="ajaxQuickSearch">
	<input type="hidden" name="view_id" value="{$view->id}">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

	<div style="border:1px solid rgb(200,200,200);">
		<table cellpadding="0" cellspacing="0" width="100%">
			<tr>
				<td width="100%" valign="top">
					<textarea name="query" class="cerb-code-editor cerb-input-quicksearch" data-editor-mode="ace/mode/cerb_query" style="width:100%;height:30px;border:0;visibility:hidden;">{$view->getParamsQuery()}</textarea>
				</td>
				<td width="0%" nowrap="nowrap" valign="top">
					<a href="javascript:;" class="cerb-quick-search-menu-trigger" style="position:relative;top:5px;padding:0px 10px;"><span class="glyphicons glyphicons-circle-question-mark" style="margin:0;color:gray;"></span></a>
				</td>
			</tr>
		</table>
	</div>
</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#{$uniqid}');
	var $popup = $frm.closest('.ui-dialog');
	var isInPopup = ($popup.length > 0);
	
	var $editor = $frm.find('textarea.cerb-code-editor')
		.cerbCodeEditor()
		.cerbCodeEditorAutocompleteSearchQueries({
			context: '{$view->getContext()}'
		})
		.nextAll('pre.ace_editor')
		;
	
	var editor = ace.edit($editor.attr('id'));
	editor.setOption('highlightActiveLine', false);
	editor.renderer.setOption('showGutter', false);
	editor.renderer.setOption('showLineNumbers', false);
	editor.commands.addCommand({
		name: 'Submit',
		bindKey: { win: "Enter", mac: "Enter" },
		exec: function(editor) {
			$frm.submit();
		}
	});
	
	{if $focus}
	editor.focus();
	{/if}
	
	var $menu_trigger = $frm.find('a.cerb-quick-search-menu-trigger').click(function() {
		editor.focus();
		editor.commands.byName.startAutocomplete.exec(editor);
	});
	
	$frm.submit(function() {
		genericAjaxPost('{$uniqid}','',null,function(json) {
			if(json.status == true) {
				{if !empty($return_url)}
					window.location.href = '{$return_url}';
				{else}
					var $view_filters = $('#viewCustomFilters{$view->id}');
					
					if(0 != $view_filters.length) {
						$view_filters.html(json.html);
						$view_filters.trigger('view_refresh')
					}
				{/if}
			}
			
			editor.focus();
		});
	});
});
</script>
{/if}
<form action="{devblocks_url}{/devblocks_url}" method="POST" id="frmStorageSchemaPeek" name="frmStorageSchemaPeek" onsubmit="return false;">
<input type="hidden" name="c" value="config">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="storage_content">
<input type="hidden" name="action" value="saveStorageSchemaPeek">
<input type="hidden" name="ext_id" value="{$schema->manifest->id}">

{$schema->renderConfig()}

<button type="button" onclick="genericAjaxPost('frmStorageSchemaPeek','schema_{$schema->manifest->id|md5}');genericAjaxPopupClose('peek');"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate}</button>

</form>

<script type="text/javascript">
$(function() {
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		$(this).dialog('option','title',"{$schema->manifest->name|escape:'javascript' nofilter}");
	});
});
</script>
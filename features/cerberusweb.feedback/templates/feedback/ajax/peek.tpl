{$peek_context = CerberusContexts::CONTEXT_FEEDBACK}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmFeedbackEntry" onsubmit="return false;">
<input type="hidden" name="c" value="feedback">
<input type="hidden" name="a" value="saveEntry">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<div>
	<b>{'feedback_entry.quote_address'|devblocks_translate|capitalize}:</b> ({'feedback.peek.quote.tooltip'|devblocks_translate})<br>
	
	<button type="button" class="chooser-abstract" data-field-name="quote_address_id" data-context="{CerberusContexts::CONTEXT_ADDRESS}" data-single="true" data-autocomplete="" data-autocomplete-if-empty="true"><span class="glyphicons glyphicons-search"></span></button>
	
	<ul class="bubbles chooser-container">
		{if $address}
			<li><input type="hidden" name="quote_address_id" value="{$address->id}"><a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_ADDRESS}" data-context-id="{$address->id}">{$address->email}</a></li>
		{/if}
	</ul>
	<br>
	<br>
	
	<b>{'feedback_entry.quote_text'|devblocks_translate|capitalize}:</b><br>
	<textarea name="quote" cols="45" rows="4" style="width:98%;">{$model->quote_text}</textarea><br>
	<br>
	
	<b>{'feedback_entry.quote_mood'|devblocks_translate|capitalize}:</b> 
	<label><input type="radio" name="mood" value="1" {if 1==$model->quote_mood}checked{/if}> <span class="tag tag-green" style="vertical-align:middle;">{'feedback.mood.praise'|devblocks_translate|capitalize}</span></label>
	<label><input type="radio" name="mood" value="0" {if empty($model->quote_mood)}checked{/if}> <span class="tag tag-gray" style="vertical-align:middle;">{'feedback.mood.neutral'|devblocks_translate|capitalize}</span></label>
	<label><input type="radio" name="mood" value="2" {if 2==$model->quote_mood}checked{/if}> <span class="tag tag-red" style="vertical-align:middle;">{'feedback.mood.criticism'|devblocks_translate|capitalize}</span></label>
	<br>
	<br>
	
	<b>{'feedback_entry.source_url'|devblocks_translate|capitalize}:</b> ({'common.optional'|devblocks_translate|lower})<br>
	<input type="text" name="url" size="45" maxlength="255" style="width:98%;" value="{$model->source_url}"><br>
</div>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=$peek_context context_id=$model->id}

<input type="hidden" name="source_extension_id" value="{$source_extension_id}">
<input type="hidden" name="source_id" value="{$source_id}">
<br>

{if (!$model->id && $active_worker->hasPriv("contexts.{$peek_context}.create")) || ($model->id && $active_worker->hasPriv("contexts.{$peek_context}.update"))}<button type="button" onclick="genericAjaxPopupPostCloseReloadView(null,'frmFeedbackEntry', '{$view_id}');"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>{/if}
{if $model->id && $active_worker->hasPriv("contexts.{$peek_context}.delete")}<button type="button" onclick="if(confirm('Permanently delete this feedback?')) { this.form.do_delete.value='1';genericAjaxPopupPostCloseReloadView(null,'frmFeedbackEntry', '{$view_id}'); } "><span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}

</form>

<script type="text/javascript">
$(function() {
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		$popup.dialog('option','title',"{'feedback.button.capture'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		
		// Abstract choosers
		
		$popup.find('button.chooser-abstract')
			.cerbChooserTrigger()
			;
		
		// Peek triggers
		$popup.find('a.cerb-peek-trigger').cerbPeekTrigger();
		
		// Searches
		$popup.find('button.cerb-search-trigger')
			.cerbSearchTrigger()
			;
	});
});
</script>
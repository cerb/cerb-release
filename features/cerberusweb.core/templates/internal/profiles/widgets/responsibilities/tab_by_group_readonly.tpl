{$tab_context = CerberusContexts::CONTEXT_GROUP}
{$tab_context_id = $group->id}

{$tab_is_editable = $active_worker->is_superuser || $active_worker->isGroupManager($group->id)}
{$tab_uniqid = uniqid()}

{if $tab_is_editable}
<form action="javascript:;" method="post" style="margin:5px;" onsubmit="return false;" id="frm{$tab_uniqid}">
	<button type="button"><span class="glyphicons glyphicons-cogwheel"></span> {'common.edit'|devblocks_translate|capitalize}</button>
</form>
{/if}

<div id="fieldsets{$tab_uniqid}" style="column-width:275px;">

{foreach from=$buckets item=bucket key=bucket_id}
<fieldset class="peek" style="margin-bottom:0;display:inline-block;vertical-align:top;break-inside:avoid-column;">
	<legend>
		<a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_BUCKET}" data-context-id="{$bucket->id}">{$bucket->name}</a>
	</legend>
	
	<div style="padding-left:10px;">
		{foreach from=$members item=member}
		{$worker_id = $member->id}
		{$worker = $workers.$worker_id}
		{$responsibility_level = $responsibilities.$bucket_id.$worker_id}
		
		{if $worker}
		<div style="width:250px;display:block;margin:0 10px 10px 5px;">
			<label>
				<a href="javascript:;" class="cerb-peek-trigger no-underline" data-context="{CerberusContexts::CONTEXT_WORKER}" data-context-id="{$worker->id}"><b>{$worker->getName()}</b></a> {if $worker->title}({$worker->title}){/if}
			</label>
			
			<div style="margin-top:5px;position:relative;margin-left:5px;width:250px;height:10px;background-color:var(--cerb-color-background-contrast-230);border-radius:10px;">
				<span style="display:inline-block;background-color:rgb(200,200,200);height:18px;width:1px;position:absolute;top:-4px;margin-left:1px;left:50%;"></span>
				<div style="position:relative;margin-left:-6px;top:-3px;left:{$responsibility_level}%;width:15px;height:15px;border-radius:15px;background-color:{if $responsibility_level < 50}rgb(230,70,70);{elseif $responsibility_level > 50}rgb(0,200,0);{else}rgb(175,175,175);{/if}"></div>
			</div>
			
		</div>
		{/if}
		
		{/foreach}
		
	</div>
</fieldset>
{/foreach}

</div>

{if $tab_is_editable}
<script type="text/javascript">
$(function() {
	var $frm = $('#frm{$tab_uniqid}');
	var $fieldsets = $('#fieldsets{$tab_uniqid}');
	
	$fieldsets.find('.cerb-peek-trigger').cerbPeekTrigger();
	
	$frm.find('button').click(function() {
		// Open popup
		var $popup = genericAjaxPopup('peek', 'c=profiles&a=invokeWidget&widget_id={$widget->id}&action=renderPopup&context={$tab_context}&context_id={$tab_context_id}', null, false, '90%');
		
		// When the popup saves, reload the tab
		$popup.one('responsibilities_save', function() {
			var $tabs = $frm.closest('div.ui-tabs');
			var tabId = $tabs.tabs("option", "active");
			$tabs.tabs("load", tabId);
		});
		
	});
});
</script>
{/if}
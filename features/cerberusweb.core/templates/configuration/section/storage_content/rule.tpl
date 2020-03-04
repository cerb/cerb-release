<fieldset>
	<legend>
		{$schema->manifest->name}
		{if !$smarty.const.DEVBLOCKS_STORAGE_ENGINE_PREVENT_CHANGE}
		(<a href="javascript:;" onclick="genericAjaxPopup('peek','c=config&a=invoke&module=storage_content&action=showStorageSchemaPeek&ext_id={$schema->manifest->id}', null, false);">{'common.edit'|devblocks_translate|lower}</a>)
		{/if}
	</legend>

	{$schema->render()}
	
	{$schema_stats = $schema->getStats()}
	{if !empty($schema_stats)}
		<br>
		{foreach from=$schema_stats item=stats key=stat_key}
			{$stat_keys = explode(':',$stat_key)}
			{$extension_id = $stat_keys.0}
			{$profile_id = $stat_keys.1}
			
			{if isset($storage_engines.$extension_id) && isset($stats.count) && isset($stats.bytes)}
				{if empty($profile_id)}
				<b>{$storage_engines.{$extension_id}->name}:</b>
				{else}
				<b>{$storage_profiles.{$profile_id}->name}:</b>
				{/if}
				 
				{$stats.count} objects ({$stats.bytes|devblocks_prettybytes})<br>
			{/if}
		{/foreach}
	{/if}
</fieldset>
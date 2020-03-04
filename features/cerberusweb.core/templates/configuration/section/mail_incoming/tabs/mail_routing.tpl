<form action="{devblocks_url}{/devblocks_url}" style="margin-bottom:5px;">
	<button type="button" onclick="genericAjaxPopup('peek','c=config&a=invoke&module=mail_incoming&action=showMailRoutingRulePanel&id=0',null,false,'50%');"><span class="glyphicons glyphicons-circle-plus" style="color:rgb(0,180,0);"></span> {'common.add'|devblocks_translate|capitalize}</button>
</form>

<fieldset>
	<legend>Rules</legend>
	
	<form action="{devblocks_url}{/devblocks_url}" method="post">
	<input type="hidden" name="c" value="config">
<input type="hidden" name="a" value="invoke">
<input type="hidden" name="module" value="mail_incoming">
<input type="hidden" name="action" value="saveRouting">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	
	{if !empty($rules)}
	<table cellspacing="2" cellpadding="2">
		<tr>
			<td align="center" style="padding-right:10px;"><b>{'common.order'|devblocks_translate|capitalize}</b></td>
			<td><b>Routing Rule</b></td>
			<td align="center"><b>{'common.remove'|devblocks_translate|capitalize}</b></td>
		</tr>
		{counter start=0 print=false name=order}
		{foreach from=$rules item=rule key=rule_id name=rules}
			<tr>
				<td valign="top" align="center">
					{if $rule->is_sticky}
						<input type="hidden" name="sticky_ids[]" value="{$rule_id}">
						<input type="text" name="sticky_order[]" value="{counter name=order}" size="2" maxlength="2">
					{else}
						<i><span style="color:rgb(180,180,180);font-size:80%;">(auto)</span></i>
					{/if}
				</td>
				<td style="{if $rule->is_sticky}background-color:rgb(255,255,221);border:2px solid rgb(255,215,0);{else}{/if}padding:5px;">
					<a href="javascript:;" onclick="genericAjaxPopup('peek','c=config&a=invoke&module=mail_incoming&action=showMailRoutingRulePanel&id={$rule_id}',null,false,'50%');" style="color:rgb(0,120,0);font-weight:bold;">{$rule->name}</a>
					{if $rule->is_stackable}<span style="font-size:90%;padding-left:5px;color:rgb(0,120,0);">(Stackable)</span>{/if}
					<br>
					
					{foreach from=$rule->criteria item=crit key=crit_key}
						{if $crit_key=='tocc'}
							To/Cc = <b>{$crit.value}</b><br>
						{elseif $crit_key=='from'}
							From = <b>{$crit.value}</b><br>
						{elseif $crit_key=='subject'}
							Subject = <b>{$crit.value}</b><br>
						{elseif 'header'==substr($crit_key,0,6)}
							Header <i>{$crit.header}</i> = <b>{$crit.value}</b><br>
						{elseif $crit_key=='body'}
							Body = <b>{$crit.value}</b><br>
						{elseif $crit_key=='dayofweek'}
							Day of Week is 
								{foreach from=$crit item=day name=timeofday}
								<b>{$day}</b>{if !$smarty.foreach.timeofday.last} or {/if}
								{/foreach}
								<br>
						{elseif $crit_key=='timeofday'}
							{$from_time = explode(':',$crit.from)}
							{$to_time = explode(':',$crit.to)}
							Time of Day 
								<i>between</i> 
								<b>{$from_time[0]|string_format:"%d"}:{$from_time[1]|string_format:"%02d"}</b> 
								<i>and</i> 
								<b>{$to_time[0]|string_format:"%d"}:{$to_time[1]|string_format:"%02d"}</b> 
								<br>
						{elseif 0==strcasecmp('cf_',substr($crit_key,0,3))}
							{include file="devblocks:cerberusweb.core::internal/custom_fields/filters/render_criteria_list.tpl"}
						{/if}
					{/foreach}
					
					<blockquote style="margin:2px;margin-left:20px;font-size:95%;color:rgb(100,100,100);">
						{foreach from=$rule->actions item=action key=action_key}
							{if $action_key=="move"}
								{assign var=g_id value=$action.group_id}
								{if isset($groups.$g_id)}
									Move to 
									<b>{$groups.$g_id->name}</b>
								{/if}
								<br>
							{elseif 0==strcasecmp('cf_',substr($action_key,0,3))}
								{include file="devblocks:cerberusweb.core::internal/custom_fields/filters/render_action_list.tpl"}
							{/if}
						{/foreach}
					<span>(Matched {$rule->pos} new messages)</span><br>
					</blockquote>
				</td>
				<td valign="top" align="center">
					<label><input type="checkbox" name="deletes[]" value="{$rule_id}">
					<input type="hidden" name="ids[]" value="{$rule_id}">
				</td>
			</tr>
		{/foreach}
	</table>
	<br>	
	{/if}

	<button type="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	</form>
</fieldset>

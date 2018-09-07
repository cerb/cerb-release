{$view_filters = $view->getFields()}
{if $readonly}<ul class="bubbles">{/if}
{if !empty($params)}
{foreach from=$params item=param key=param_key name=params}
	{if !$nested && !$readonly}<div><span class="glyphicons glyphicons-circle-remove" style="cursor:pointer;margin-right:5px;"></span> <input type="checkbox" name="field_deletes[]" value="{$param_key}" style="display:none;"> {/if}
	{if !$nested && $readonly}<li class="bubble-blue" style="position:relative;{if is_array($param)}white-space:normal;{/if}">{/if}
		
	{if '*_' == substr($param->field,0,2)}
		{$view->renderVirtualCriteria($param)}
	{elseif is_array($param)}
		{foreach from=$param item=p name=p}
			{if $smarty.foreach.p.first}
				{if $p == DevblocksSearchCriteria::GROUP_AND}
					{$group_oper = 'AND'}
					{$group_not = false}
				{elseif $p == DevblocksSearchCriteria::GROUP_AND_NOT}
					{$group_oper = 'AND'}
					{$group_not = true}
				{elseif $p == DevblocksSearchCriteria::GROUP_OR}
					{$group_oper = 'OR'}
					{$group_not = false}
				{elseif $p == DevblocksSearchCriteria::GROUP_OR_NOT}
					{$group_oper = 'OR'}
					{$group_not = true}
				{/if}
				
				{if $group_not}
					<tt style="color:black;font-weight:bold;padding:0px 5px;">NOT (</tt>
				{/if}
			{else}
				{if is_array($p)}
					{include file="devblocks:cerberusweb.core::internal/views/criteria_list_params.tpl" params=$p nested=true}
				{else}
					{if '*_' == substr($p->field,0,2)}
						{$view->renderVirtualCriteria($p)}
					{else}
						{$view_filters.{$p->field}->db_label|capitalize} 
						{if $p->operator=='='}
							is 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='equals or null'}
							is 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='!='}
							is not 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='like'}
							is 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='not like'}
							is not 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='in'}
							is 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='in or null'}
							is blank{if !empty($p->value)} or{/if} 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='not in'}
							is not 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='not in or null'}
							is blank{if !empty($p->value)} or not{/if} 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='is null'}
							is <b>null</b>
						{elseif $p->operator=='is not null'}
							is <b>not null</b>
						{elseif $param->operator=='between'}
							is between 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $param->operator=='not between'}
							is not between 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='fulltext'}
							search 
							<b>{$view->renderCriteriaParam($p)}</b>
						{elseif $p->operator=='custom'}
							matches  
							<b>{$view->renderCriteriaParam($p)}</b>
						{else} 
							{$p->operator} 
							<b>{$view->renderCriteriaParam($p)}</b>
						{/if}
					{/if}
				{/if}
				
				{if !$smarty.foreach.p.last} 
					<tt style="color:black;font-weight:bold;padding:0px 5px;">{$group_oper}</tt>
				{else}
					{if $group_not}
					<tt style="color:black;font-weight:bold;padding:0px 5px;">)</tt>
					{/if}
				{/if}
			{/if}
		{/foreach}
	{else}
		{$field = $param->field} 
		{$view_filters.$field->db_label|capitalize}
		{* [TODO] Add operator labels to platform *}
		{if $param->operator=='='}
			is 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='equals or null'}
			is 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='!='}
			is not 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='like'}
			is 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='not like'}
			is not 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='in'}
			is 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='in or null'}
			is null{if !empty($param->value)} or{/if} 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='not in'}
			is not 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='not in or null'}
			is null{if !empty($param->value)} or not{/if} 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='is null'}
			is <b>null</b>
		{elseif $param->operator=='is not null'}
			is <b>not null</b>
		{elseif $param->operator=='between'}
			is between 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='not between'}
			is not between 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='fulltext'}
			search 
			<b>{$view->renderCriteriaParam($param)}</b>
		{elseif $param->operator=='custom'}
			matches 
			<b>{$view->renderCriteriaParam($param)}</b>
		{else} 
			{$param->operator} 
			<b>{$view->renderCriteriaParam($param)}</b>
		{/if}
		
		{if $nested}{if $smarty.foreach.params.first}<tt style="color:black;font-weight:bold;padding:0px 2px 0px 0px;">(</tt>{/if}
			{if !$smarty.foreach.params.first && !$smarty.foreach.params.last}<tt style="color:black;font-weight:bold;padding:0px 5px;">{$params.0}</tt>{/if}
			{if $smarty.foreach.params.last}<tt style="color:black;font-weight:bold;padding:0px 0px 0px 2px;">)</tt>{/if} 
		{/if}
	{/if}
		
	{if !$nested && !$readonly}</div>{/if}
	{if !$nested && $readonly}<a href="javascript:;" class="delete" onclick="ajax.viewRemoveFilter('{$view->id}', ['{$param_key}']);" style="position:absolute;top:-7px;right:-6px;display:none;"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span></a></li>{/if}
{/foreach}
{if $readonly}</ul>{/if}
{/if}

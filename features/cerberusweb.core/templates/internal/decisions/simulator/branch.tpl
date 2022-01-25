{$node = $nodes[$node_id]}

{if in_array($node_id, $path)}

{* Label *}
<div class="node {$node->node_type}">
	{if $node->node_type == 'subroutine'}
		<div class="badge badge-lightgray" style="margin:2px;">
			<a href="javascript:;" style="text-decoration:none;font-weight:bold;color:var(--cerb-color-background-contrast-50);">
				{$node->title}
			</a>
		</div>

	{elseif $node->node_type == 'switch'}
		<div class="badge badge-lightgray" style="margin:2px;">
			<a href="javascript:;" style="text-decoration:none;font-weight:bold;color:rgb(68,154,220);">
				{$node->title}
			</a>
		</div>
		
	{elseif $node->node_type == 'loop'}
		<div class="badge badge-lightgray" style="margin:2px;">
			<a href="javascript:;" style="text-decoration:none;font-weight:bold;color:var(--cerb-color-background-contrast-100);">
				<span style="font-weight:normal;">&#x27f3;</span> {$node->title}
			</a>
		</div>
	
	{elseif $node->node_type == 'outcome'}
		<div class="badge badge-lightgray">
			<a href="javascript:;" style="text-decoration:none;font-weight:bold;{if preg_match('#^yes($|,| )#i',$node->title)}color:rgb(0,150,0);{elseif preg_match('#^no($|,| )#i',$node->title)}color:rgb(150,0,0);{/if}">
				{$node->title}
			</a>
		</div>
	
	{elseif $node->node_type == 'action'}
		<div class="badge badge-lightgray" style="margin:2px;">
			<a href="javascript:;" style="text-decoration:none;font-weight:normal;font-style:italic;">
				{$node->title}
			</a>
		</div>
		
	{/if}
	
	{* Recurse Children *}
	<div class="branch {$node->node_type}" style="padding-bottom:2px;margin-left:10px;padding-left:10px;{if $node->node_type == 'outcome'}border-left:1px solid rgb(200,200,200);{/if}">
	{if is_array($tree[$node_id]) && !empty($tree[$node_id])}
		{foreach from=$tree[$node_id] item=child_id}
			{include file="devblocks:cerberusweb.core::internal/decisions/simulator/branch.tpl" node_id=$child_id trigger_id=$trigger_id path=$path nodes=$nodes tree=$tree depths=$depths}
		{/foreach}
	{/if}
	</div>
</div>

{/if}

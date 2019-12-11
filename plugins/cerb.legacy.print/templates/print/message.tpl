<html>
<head>
	<title>Ticket #{$ticket->mask}: {$ticket->subject} - {$settings->get('cerberusweb.core','helpdesk_title','')}</title>
	<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=utf-8">
</head>

<body>
<div style="display: inline-block;max-width: 100vw;background: url({devblocks_url}c=resource&p=cerberusweb.core&f=css/logo{/devblocks_url}?v={$settings->get('cerberusweb.core','ui_user_logo_updated_at',0)}) no-repeat;background-size:contain;width:281px;height:80px;"></div>
<br>

<h2 style="margin-bottom:0px;">{$ticket->subject}</h2>

{assign var=ticket_team_id value=$ticket->team_id}
{assign var=ticket_team value=$teams.$ticket_team_id}
{assign var=ticket_category_id value=$ticket->category_id}
{assign var=ticket_team_category_set value=$team_categories.$ticket_team_id}
{assign var=ticket_category value=$ticket_team_category_set.$ticket_category_id}

<b>Status:</b> {if $ticket->is_deleted}{'status.deleted'|devblocks_translate}{elseif $ticket->is_closed}{'status.closed'|devblocks_translate}{elseif $ticket->is_waiting}{'status.waiting'|devblocks_translate}{else}{'status.open'|devblocks_translate}{/if} &nbsp; 
<b>Team:</b> {$teams.$ticket_team_id->name} &nbsp; 
<b>Bucket:</b> {if !empty($ticket_category_id)}{$buckets.$ticket_category_id->name}{else}Inbox{/if} &nbsp; 
<b>Mask:</b> {$ticket->mask} &nbsp; 
<b>Internal ID:</b> {$ticket->id} &nbsp; 
<br>
{if !empty($context_watchers)}
	<b>{'common.watchers'|devblocks_translate|capitalize}:</b> 
	{foreach from=$context_watchers item=context_worker name=context_watchers}
	{$context_worker->getName()}{if !$smarty.foreach.context_watchers.last}, {/if}
	{/foreach}
{/if}
<br>

{assign var=headers value=$message->getHeaders()}
<hr>
<div style="margin-left:20px;">
	{if isset($headers.to)}<b>To:</b> {$headers.to}<br>{/if}
	{if isset($headers.cc)}<b>Cc:</b> {$headers.cc}<br>{/if}
	{if isset($headers.from)}<b>From:</b> {$headers.from}<br>{/if}
	{if isset($headers.date)}<b>Date:</b> {$headers.date}<br>{/if}
	{if isset($headers.subject)}<b>Subject:</b> {$headers.subject}<br>{/if}
	<br>
	{if $message->html_attachment_id}
		{$message->getContentAsHtml() nofilter}<br>
	{else}
		{$message->getContent()|trim|escape|nl2br nofilter}<br>
	{/if}
	<br>
	{assign var=message_id value=$message->id}
	{if isset($message_notes.$message_id) && is_array($message_notes.$message_id)}
		{foreach from=$message_notes.$message_id item=note name=notes key=note_id}
				<div style="margin:10px;margin-left:20px;">
					<b>[{'display.ui.sticky_note'|devblocks_translate|capitalize}] </b>
					{if 1 == $note->type}
						<b>[warning]:</b>&nbsp;
					{elseif 2 == $note->type}
						<b>[error]:</b>&nbsp;
					{else}
						<br><b>From: </b>
						{assign var=note_worker_id value=$note->worker_id}
						{if $workers.$note_worker_id}
							{if empty($workers.$note_worker_id->first_name) && empty($workers.$note_worker_id->last_name)}&lt;{$workers.$note_worker_id->email}&gt;{else}{$workers.$note_worker_id->getName()}{/if}&nbsp;
						{else}
							(Deleted Worker)&nbsp;
						{/if}
					{/if}
					<br>
					<b>{'message.header.date'|devblocks_translate|capitalize}:</b> {$note->created|devblocks_date}<br>
					{if !empty($note->content)}{$note->content}{/if}
				</div>
		{/foreach}
	{/if}
</div>

</body>
<script type="text/javascript">
if(document.addEventListener) {
	document.addEventListener('DOMContentLoaded', function(e) {
		window.print();
	}, false);
}
</script>
</html>

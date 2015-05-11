{include file="devblocks:cerberusweb.core::header.tpl"}

{if !empty($prebody_renderers)}
	{foreach from=$prebody_renderers item=renderer}
		{if !empty($renderer)}{$renderer->render()}{/if}
	{/foreach}
{/if}

{if !empty($tour_enabled)}{include file="devblocks:cerberusweb.core::internal/tour/banner.tpl"}{/if}
<table cellspacing="0" cellpadding="2" border="0" width="100%">
	<tr>
		<td align="left" valign="bottom">
			{assign var=logo_url value=$settings->get('cerberusweb.core','helpdesk_logo_url','')}
			{if empty($logo_url)}
			<a href="{devblocks_url}{/devblocks_url}"><img src="{devblocks_url}c=resource&p=cerberusweb.core&f=images/wgm/cerb_logo.png{/devblocks_url}?v={$smarty.const.APP_BUILD}" border="0" id="logo"></a>
			{else}
			<a href="{devblocks_url}{/devblocks_url}"><img src="{$logo_url}" border="0" id="logo"></a>
			{/if}
		</td>
		<td align="right" valign="bottom" style="line-height:150%;">
		{if empty($visit)}
			{'header.not_signed_in'|devblocks_translate} <a href="{devblocks_url}c=login{/devblocks_url}">{'header.signon'|devblocks_translate|lower}</a>
		{elseif !empty($active_worker)}
			<span id="badgeNotifications"><a href="{devblocks_url}c=profiles&w=worker&me=me&tab=notifications{/devblocks_url}"></a></span>
			
			{$worker_name =''|cat:'<b><a href="javascript:;" id="lnkSignedIn">'|cat:$active_worker->getName()|cat:'</a></b><span class="glyphicons glyphicons-chevron-down" style="margin:2px 0px 0px 2px;"></span>'}
			{'header.signed_in'|devblocks_translate:$worker_name nofilter}
			{if $visit->isImposter()}
				[ <a href="javascript:;" id="aImposter">{$visit->getImposter()->getName()}</a> ]
			{/if}
			<ul id="menuSignedIn" class="cerb-popupmenu cerb-float">
				<li><a href="{devblocks_url}c=profiles&w=worker&me=me{/devblocks_url}">{'header.my_profile'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=preferences{/devblocks_url}">{'common.settings'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=profiles&w=worker&me=me&tab=notifications{/devblocks_url}">{'home.tab.my_notifications'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=profiles&w=worker&me=me&tab=availability{/devblocks_url}">{'common.calendar'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=profiles&w=worker&me=me&tab=activity{/devblocks_url}">{'common.activity_log'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=profiles&w=worker&me=me&tab=attendants{/devblocks_url}">{'common.virtual_attendants'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=profiles&w=worker&me=me&tab=links{/devblocks_url}">{'watchlist'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=profiles&w=worker&me=me&tab=snippets{/devblocks_url}">{'common.snippets'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=login&a=signout{/devblocks_url}">{'header.signoff'|devblocks_translate|lower}</a></li>
				<li><a href="{devblocks_url}c=login&a=signout&w=all{/devblocks_url}">{'header.signoff.all.my'|devblocks_translate|lower}</a></li>
			</ul>
		{/if}
		</td>
	</tr>
</table>

<script type="text/javascript">
$().ready(function(e) {
	{if !empty($visit) && $visit->isImposter()}
	$('#aImposter').click(function(e) {
		genericAjaxGet('','c=internal&a=suRevert',function(o) {
			window.location.reload();
		});
	});
	{/if}
	
	$menu = $('#menuSignedIn');
	$menu.appendTo('body');
	$menu.find('> li')
		.click(function(e) {
			e.stopPropagation();
			if(!$(e.target).is('li'))
				return;

			$link = $(this).find('a:first');
			
			if($link.length > 0)
				window.location.href = $link.attr('href');
		})
		;
	
	$('#lnkSignedIn')
		.click(function(e) {
			$menu = $('#menuSignedIn');

			if($menu.is(':visible'))
				return;
			
			$menu
				.show()
				.css('position','absolute')
				.css('top',$(this).offset().top+($(this).height())+'px')
				.css('left',$(this).offset().left-(10+$menu.width()-$(this).width())+'px')
				.show()
			;
		});

	$menu
		.hover(
			function(e) {},
			function(e) {
				$('#menuSignedIn')
					.hide()
				;
			}
		)
		;
});
</script>

{include file="devblocks:cerberusweb.core::menu.tpl"}

{if !empty($page) && $page->isVisible()}
	{$page->render()}
{else}
	{'header.no_page'|devblocks_translate}
{/if}

{if !empty($postbody_renderers)}
	{foreach from=$postbody_renderers item=renderer}
		{if !empty($renderer)}{$renderer->render()}{/if}
	{/foreach}
{/if}

{include file="devblocks:cerberusweb.core::footer.tpl"}

<html>
<head>
	<meta content="text/html; charset=utf-8" http-equiv="content-type">
	<meta name="_csrf_token" content="{$session->csrf_token}">
	<title>{$page_title}</title>
	
	{if empty($favicon_url)}
	<link type="image/x-icon" rel="shortcut icon" href="{devblocks_url}c=resource&p=cerberusweb.support_center&f=favicon.ico{/devblocks_url}">
	{else}
	<link type="image/x-icon" rel="shortcut icon" href="{$favicon_url}">
	{/if}
	
	<script type="text/javascript" src="{devblocks_url}c=resource&p=cerberusweb.support_center&f=js/devblocks.common.js{/devblocks_url}"></script>
	
	<style type='text/css'>
		{include file="devblocks:cerberusweb.support_center:portal_{$portal_code}:support_center/style.css.tpl"}
		{include file="devblocks:cerberusweb.support_center:portal_{$portal_code}:support_center/user_styles.css.tpl"}
	</style>
</head>

<body>
{include file="devblocks:cerberusweb.support_center:portal_{$portal_code}:support_center/header.tpl"}

<div style="clear:both;margin-bottom:5px;"></div>

<ul class="menu">
{foreach from=$menu item=item name=menu}
{if $item->manifest->id == 'sc.controller.login'}
	{if !empty($active_contact)}
		<li style="float:right;"><a href="{devblocks_url}c={$item->manifest->params.uri}&a=signout{/devblocks_url}">{'portal.sc.public.themes.sign_out'|devblocks_translate|lower}</a></li>
	{else}
		<li style="float:right;background-color:rgb(46,183,39);"><a style="color:rgb(255,255,255);" href="{devblocks_url}c={$item->manifest->params.uri}{/devblocks_url}">{'portal.sc.public.themes.sign_in'|devblocks_translate|lower}</a></li>
	{/if}
{else}
	<li {if !empty($module) && 0==strcasecmp($module->manifest->params.uri,$item->manifest->params.uri)}class="selected"{/if}>
		<a href="{devblocks_url}c={$item->manifest->params.uri}{/devblocks_url}">{$item->manifest->params.menu_title|devblocks_translate|lower}</a>
	</li>
{/if}
{/foreach}
</ul>
<div style="clear:both;border-top:2px solid rgb(69,85,96);">
</div>

<table cellpadding="5" cellspacing="0" border="0" width="100%" align="center">
	<tr>
		<td width="1%" nowrap="nowrap" valign="top">
			<!-- Sidebar -->
			{if !empty($module) && method_exists($module,'renderSidebar')}
			{$module->renderSidebar($module_response)}
			{/if}
		</td>
		
		<td width="99%" valign="top">
			<div id="content">
			{if !empty($module)}
			{$module->writeResponse($module_response)}
			{/if}
			</div>
		</td>
	</tr>
</table>

{include file="devblocks:cerberusweb.support_center:portal_{$portal_code}:support_center/footer.tpl"}

</body>

</html>

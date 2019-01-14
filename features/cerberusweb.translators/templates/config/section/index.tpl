<div>
	<h2>{'translators.common'|devblocks_translate|capitalize}</h2>
</div>

<div>
	{include file="devblocks:cerberusweb.core::search/quick_search.tpl" view=$view return_url=null reset=false}
</div>

<form action="{devblocks_url}{/devblocks_url}" style="margin-bottom:5px;">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	<button type="button" onclick="genericAjaxPopup('peek','c=config&a=handleSectionAction&section=translations&action=showAddLanguagePanel',null,false,'50%' );"><span class="glyphicons glyphicons-globe"></span> {'translators.languages'|devblocks_translate|capitalize}</button>
	<button type="button" onclick="genericAjaxPopup('peek','c=config&a=handleSectionAction&section=translations&action=showFindStringsPanel',null,false,'50%' );"><span class="glyphicons glyphicons-refresh"></span> {'common.synchronize'|devblocks_translate|capitalize}</button>
	<button type="button" onclick="genericAjaxPopup('peek','c=config&a=handleSectionAction&section=translations&action=showImportStringsPanel',null,false,'50%' );"><span class="glyphicons glyphicons-file-import"></span> {'common.import'|devblocks_translate|capitalize}</button>
</form>

{include file="devblocks:cerberusweb.core::internal/views/search_and_view.tpl" view=$view}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmFindStringsEntry">
<input type="hidden" name="c" value="config">
<input type="hidden" name="a" value="invoke">
<input type="hidden" name="module" value="translations">
<input type="hidden" name="action" value="saveFindStringsPanel">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

This will find text defined in U.S. English and not yet translated to other languages.  
Leaving new text blank allows you to easily find translation work with a search.
<br>
<br>

{if count($codes) > 1}
<table cellspacing="0" cellpadding="2" border="0">
<tr>
	<td><b>Language</td>
	<td style="padding-left:10px;"><b>With new text to translate...</td>
</tr>
{foreach from=$codes key=code item=lang_name}
{if $code != 'en_US'}
	<tr>
	<td>
		{$lang_name}
		<input type="hidden" name="lang_codes[]" value="{$code}">
	</td>
	
	<td style="padding-left:10px;">
		<select name="lang_actions[]">
			<option value="">- leave blank -</option>
			<option value="en_US">Copy U.S. English</option>
		</select>
	</td>
	</tr>
{/if}
{/foreach}
</table>
<br>
{else}
<br>
<b>You have no non-English languages defined.</b><br>
<br>
{/if}

{if count($codes) > 1}<button type="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>{/if}

</form>

<script type="text/javascript">
	$popup = genericAjaxPopupFetch('peek');
	$popup.one('popup_open', function(event,ui) {
		$(this).dialog('option','title',"{'common.synchronize'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
	} );
</script>

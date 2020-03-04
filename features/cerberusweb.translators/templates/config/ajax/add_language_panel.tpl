<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmAddLanguage">
<input type="hidden" name="c" value="config">
<input type="hidden" name="a" value="invoke">
<input type="hidden" name="module" value="translations">
<input type="hidden" name="action" value="saveAddLanguagePanel">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

{if is_array($codes) && count($codes) > 1}
<table cellspacing="0" cellpadding="2" border="0">
<tr>
	<td><b>{'translators.language'|devblocks_translate|capitalize}</b></td>
	<td style="padding-left:5px;"><b>Remove</b></td>
</tr>
{if is_array($codes)}
{foreach from=$codes key=code item=lang_name}
{if 'en_US' != $code}
<tr>
	<td>{$lang_name}</td>
	<td style="padding-left:5px;"><input type="checkbox" name="del_lang_ids[]" value="{$code}"></td>
</tr>
{/if}
{/foreach}
{/if}
</table>
<br>
{/if}

<b>Add New Translation:</b><br>
<select name="add_lang_code">
<option value=""></option>
{if is_array($locales)}
{foreach from=$locales key=code item=label}
	{if !isset($codes.$code)}
	<option value="{$code}">{$label}</option>
	{/if}
{/foreach}
{/if}
</select>
<br>
<br>

<b>Copy New Text From:</b> <i>(e.g.: U.S. English to British English)</i><br>
<select name="copy_lang_code">
<option value"">-- leave blank --</option>
{if is_array($codes)}
{foreach from=$codes key=code item=lang_name}
	<option value="{$code}">{$lang_name}</option>
{/foreach}
{/if}
</select>
<br>
<br>

<button type="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>

</form>

<script type="text/javascript">
	$popup = genericAjaxPopupFetch('peek');
	$popup.one('popup_open', function(event,ui) {
		$(this).dialog('option','title',"Add Language");
	} );
</script>



<form action="{devblocks_url}c=login&a=recover&step=code{/devblocks_url}" method="post" id="recoverForm">

<div style="vertical-align:middle;max-width:500px;margin:20px auto 20px auto;padding:5px 20px 20px 20px;border-radius:5px;box-shadow:darkgray 0px 0px 5px;">
	{if !empty($error)}
	<div class="error-box" style="border:0;">
		<h1>{'common.error'|devblocks_translate|capitalize}</h1>
		<p>{Page_Login::getErrorMessage($error)}</p>
	</div>
	{/if}
	
	<div>
		<h3>Enter the recovery code that was sent to {$email}</h3>
		
		<div>
			<input type="text" name="code" size="45" value="" placeholder="e.g. 12345678" style="width:100%;line-height:1.5em;height:24px;padding:0px 5px;border-radius:5px;box-sizing:border-box;">
		</div>
		
		<div style="margin-top:10px;">
			<button type="submit" style="width:100%;">
				{'common.continue'|devblocks_translate|capitalize}
			</button>
		</div>
	</div>
</div>
</form>

<script type="text/javascript">
$(function() {
	$('#recoverForm').find('input[name=code]').focus().select();
});
</script>

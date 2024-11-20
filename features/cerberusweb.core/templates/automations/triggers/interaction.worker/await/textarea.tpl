{$element_id = uniqid()}
<div class="cerb-form-builder-prompt cerb-form-builder-prompt-text" id="{$element_id}">
	<h6>{$label}</h6>

	<div style="margin-left:10px;">
		<div class="cerb-form-textarea-grow">
			<textarea name="prompts[{$var}]" placeholder="{$placeholder}">{$value|default:$default}</textarea>
		</div>
		{if $max_length && is_numeric($max_length)}
			<div data-cerb-character-count style="text-align:right;"></div>
		{/if}
	</div>
</div>

<script nonce="{DevblocksPlatform::getRequestNonce()}" type="text/javascript">
$(function() {
	var $prompt = $('#{$element_id}');

	var $input = $prompt.find('textarea');
	var input = $input.get(0);

	var $counter = $prompt.find('[data-cerb-character-count]');
	var counter_max = {$max_length|json_encode};
	
	$input.on('input', function(e) {
		e.stopPropagation();

		this.style.height = 'auto';
		this.style.height = this.scrollHeight + 'px';

		if($counter) {
			var counter_cur = input.value.length;
			$counter.text(counter_cur + ' / ' + counter_max);

			if(counter_cur > counter_max) {
				$counter.css('color', 'red');
			} else {
				$counter.css('color', '');
			}
		}
	});

	$input.triggerHandler('input');
});
</script>
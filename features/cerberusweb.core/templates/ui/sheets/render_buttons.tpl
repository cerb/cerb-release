{$is_selection_enabled = false}

<div class="cerb-sheet-buttons">
	{if $rows}
	{foreach from=$rows item=row name=rows}
	<div class="cerb-sheet-buttons--button cerb-sheet--row">
		{if $layout.title_column}
			{$column = $columns[$layout.title_column]}
			<div class="cerb-sheet-buttons--title">{$row[$column.key] nofilter}</div>
		{/if}

		{foreach from=$columns item=column name=columns}
			{if $layout.title_column == $column.key}
			{elseif $column._type == 'selection'}
				{$is_selection_enabled = true}
				{if $column.params.mode|default:null == 'single'}
					<input type="radio" name="{$sheet_selection_key|default:'_selection'}" value="{$row[$column.key]}" {if $row[$column.key] == $default}checked="checked"{/if}>
				{else}
					<input type="checkbox" name="{$sheet_selection_key|default:'_selection'}[]" value="{$row[$column.key]}" {if is_array($default) && in_array($row[$column.key], $default)}checked="checked"{/if}>
				{/if}
			{else}
				{$row[$column.key] nofilter}
			{/if}
		{/foreach}
	</div>
	{/foreach}
	{/if}
</div>

{if $paging && $paging.page.of > 1}
	<span style="float:right;margin-top:5px;">
		{if array_key_exists('first', $paging.page)}<a href="javascript:;" class="cerb-paging" data-page="{$paging.page.first}">&lt;&lt;</a>{/if}
		{if array_key_exists('prev', $paging.page)}<a href="javascript:;" class="cerb-paging" data-page="{$paging.page.prev}">&lt;{'common.previous_short'|devblocks_translate|capitalize}</a>{/if}
		(Showing {if $paging.page.rows.from==$paging.page.rows.to}{$paging.page.rows.from}{else}{$paging.page.rows.from}-{$paging.page.rows.to}{/if}
		 of {$paging.page.rows.of})
		{if array_key_exists('next', $paging.page)}<a href="javascript:;" class="cerb-paging" data-page="{$paging.page.next}">{'common.next'|devblocks_translate|capitalize}&gt;</a>{/if}
		{if array_key_exists('last', $paging.page)}<a href="javascript:;" class="cerb-paging" data-page="{$paging.page.last}">&gt;&gt;</a>{/if}
	</span>
{/if}

{$script_uid = uniqid('script')}
<script type="text/javascript" id="{$script_uid}">
$(function() {
	var $script = $('#{$script_uid}');
	var $sheet = $script.siblings('div.cerb-sheet-buttons');

	$sheet.find('.cerb-peek-trigger')
		.cerbPeekTrigger()
		.on('cerb-peek-saved cerb-peek-deleted', function(e) {
			e.stopPropagation();
			$sheet.trigger($.Event('cerb-sheet--refresh'));
		})
	;

	$sheet.find('.cerb-search-trigger')
		.cerbSearchTrigger()
	;

	$sheet.parent().find('.cerb-paging')
		.click(function() {
			var $this = $(this);
			var page = $this.attr('data-page');

			if(null == page)
				return;

			var evt = $.Event('cerb-sheet--page-changed', { page: page });
			$sheet.trigger(evt);
		})
	;

	{if $is_selection_enabled}
	$sheet.find('> div')
		.disableSelection()
		.on('click', function(e) {
			e.stopPropagation();

			var $target = $(e.target);
			var evt;

			if($target.is('a'))
				return;

			if($target.hasClass('cerb-peek-trigger'))
				return;

			if($target.hasClass('cerb-search-trigger'))
				return;

			var $tbody = $(this);

			// If removing selected, add back hover

			var $checkbox = $tbody.find('input[type=radio], input[type=checkbox]');

			// If our target was something other than the input toggle
			if(!$checkbox.is($target)) {
				if ($checkbox.is(':checked')) {
					$checkbox.prop('checked', false);
				} else {
					$checkbox.prop('checked', true);
				}
			}

			var is_multiple = $checkbox.is('[type=checkbox]');

			evt = $.Event('cerb-sheet--selection', { ui: { item: $checkbox }, is_multiple: is_multiple });
			$sheet.trigger(evt);

			var row_selections = [];
            var rows_visible = [];

			$tbody.closest('table.cerb-sheet')
				.find('input[type=radio], input[type=checkbox]')
				.each(function() {
                    let $this = $(this);

                    rows_visible.push($this.val());
                    
                    if($this.is(':checked')) {
                        row_selections.push($this.val());
                    }
				})
			;

			$sheet.trigger($.Event('cerb-sheet--selections-changed', {
                row_selections: row_selections,
                rows_visible: rows_visible,
                is_multiple: is_multiple
            }));
		})
		.hover(
			function(e) {
				e.stopPropagation();
				$(this).addClass('hover');
			},
			function(e) {
				e.stopPropagation();
				$(this).removeClass('hover');
			}
		)
	;
	{/if}
});
</script>
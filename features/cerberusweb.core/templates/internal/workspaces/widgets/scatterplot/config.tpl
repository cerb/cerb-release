<div id="widget{$widget->id}Config">

<b>Series Axes:</b>
<label><input type="radio" name="params[axes_independent]" value="1" {if $widget->params.axes_independent}checked="checked"{/if}> Independent</label>
<label><input type="radio" name="params[axes_independent]" value="0" {if empty($widget->params.axes_independent)}checked="checked"{/if}> Shared</label>

<div id="widget{$widget->id}ConfigTabs">
	<ul style="display:none;">
		<li><a href="#widget{$widget->id}ConfigTabDatasource">Data Sources</a></li>
	</ul>
	
	<div id="widget{$widget->id}ConfigTabDatasource">
		{section start=0 loop=5 name=series}
		{$series_idx = $smarty.section.series.index}
		{$series_prefix = "[series][{$series_idx}]"}
		
		<fieldset id="widget{$widget->id}Datasource{$series_idx}" class="peek">
			<legend>Source #{$smarty.section.series.iteration}</legend>
		
			<b>Data</b> from
			{$source = $widget->params.series[{$series_idx}].datasource}
			
			<select name="params[series][{$series_idx}][datasource]" class="datasource-selector" params_prefix="{$series_prefix}">
				<option value=""></option>
				{foreach from=$datasource_mfts item=datasource_mft}
					<option value="{$datasource_mft->id}" {if $source==$datasource_mft->id}selected="selected"{/if}>{$datasource_mft->name}</option>
				{/foreach}
			</select>

			<div style="margin-left: 10px;">

				<b>Label</b> it 
				<input type="text" name="params[series][{$series_idx}][label]" value="{$widget->params.series[{$series_idx}].label}" size="45">
				<br>
				
				<div class="datasource-params">
					{$datasource = Extension_WorkspaceWidgetDatasource::get($source)}
					{if !empty($datasource) && method_exists($datasource, 'renderConfig')}
						{$datasource->renderConfig($widget, $widget->params.series[{$series_idx}], $series_prefix)}
					{/if}
				</div>
				
				<b>Color</b> it 
				<input type="text" name="params[series][{$series_idx}][line_color]" value="{$widget->params.series[{$series_idx}].line_color|default:'#058DC7'}" size="7" class="color-picker">
				<br>
				
			</div>
				
		</fieldset>
		{/section}
	</div>
	
</div>

</div>

<script type="text/javascript">
$(function() {
	var $config = $('#widget{$widget->id}Config');
	var $tabs = $('#widget{$widget->id}ConfigTabs').tabs();
	
	$tabs.find('input:text.color-picker').minicolors({
		swatches: ['#CF2C1D','#FEAF03','#57970A','#007CBD','#7047BA','#D5D5D5','#ADADAD','#34434E']
	});
	
	$tabs.find('select.datasource-selector').change(function() {
		datasource=$(this).val();
		$div_params=$(this).closest('fieldset').find('DIV.datasource-params');
		
		if(datasource.length==0) { 
			$div_params.html('');
			
		} else {
			series_prefix = $(this).attr('params_prefix');
			genericAjaxGet($div_params, 'c=profiles&a=invoke&module=workspace_widget&action=getWidgetDatasourceConfig&params_prefix=' + encodeURIComponent(series_prefix) + '&widget_id={$widget->id}&ext_id=' + datasource);
		}
	});
});
</script>
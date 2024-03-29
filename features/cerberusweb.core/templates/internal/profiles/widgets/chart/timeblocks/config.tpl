<div id="widget{$widget->id}Config" style="margin-top:10px;">
    <fieldset id="widget{$widget->id}QueryEditor" class="peek">
        <legend>
            Run this data query:
            {include file="devblocks:cerberusweb.core::help/docs_button.tpl" url="https://cerb.ai/docs/data-queries/"}
        </legend>

        <textarea name="params[data_query]" data-editor-mode="ace/mode/cerb_query" class="placeholders" style="width:95%;height:50px;">{$widget->extension_params.data_query}</textarea>

        <div>
            <b>Cache</b> query results for
            <input type="text" size="5" maxlength="6" name="params[cache_secs]" placeholder="e.g. 300" value="{$widget->extension_params.cache_secs}"> seconds
        </div>
    </fieldset>
</div>

<script type="text/javascript">
    $(function() {
        var $config = $('#widget{$widget->id}Config');
        $config.find('textarea.placeholders')
            .cerbCodeEditor()
            .cerbCodeEditorAutocompleteDataQueries()
        ;
    });
</script>
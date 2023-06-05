{$peek_context = CerberusContexts::CONTEXT_AUTOMATION_EVENT}
{$peek_context_id = $model->id}
{$form_id = uniqid('form')}
<form action="{devblocks_url}{/devblocks_url}" method="post" id="{$form_id}" onsubmit="return false;">
    <input type="hidden" name="c" value="profiles">
    <input type="hidden" name="a" value="invoke">
    <input type="hidden" name="module" value="automation_event">
    <input type="hidden" name="action" value="savePeekJson">
    <input type="hidden" name="view_id" value="{$view_id}">
    {if $model && $model->id}
        <input type="hidden" name="id" value="{$model->id}">
        <input type="hidden" name="name" value="{$model->name}">
    {/if}
    <input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

    <h1>{$model->name}</h1>
    
    <div style="margin-bottom:10px;">
        {$model->description}
    </div>
    
    {if !empty($custom_fields)}
    <table cellspacing="0" cellpadding="2" border="0" width="98%">
        {include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false tbody=true}
    </table>
    {/if}

    {include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=$peek_context context_id=$model->id}

    <fieldset class="peek">
        <legend>Automations: (KATA)</legend>
        <div class="cerb-code-editor-toolbar">
            {DevblocksPlatform::services()->ui()->toolbar()->render($toolbar)}

            <div class="cerb-code-editor-toolbar-divider"></div>

            {if $model->id}
                <button type="button" class="cerb-code-editor-toolbar-button" data-cerb-editor-button-changesets title="{'common.change_history'|devblocks_translate|capitalize}"><span class="glyphicons glyphicons-history"></span></button>
            {/if}

            {include file="devblocks:cerberusweb.core::automations/triggers/editor_event_handler_buttons.tpl"}

            <button type="button" style="float:right;" class="cerb-code-editor-toolbar-button cerb-editor-button-help"><a href="https://cerb.ai/docs/automations/#events" target="_blank"><span class="glyphicons glyphicons-circle-question-mark"></span></a></button>
        </div>

        <textarea name="automations_kata" data-editor-mode="ace/mode/cerb_kata">{$model->automations_kata}</textarea>

        {if is_a($trigger_ext, 'Extension_AutomationTrigger')}
        {include file="devblocks:cerberusweb.core::automations/triggers/editor_event_handler.tpl" trigger_inputs=$trigger_ext->getEventPlaceholders()}
        {/if}
    </fieldset>
    
    <div class="buttons" style="margin-top:10px;">
        {if $model->id}
            <button type="button" class="save"><span class="glyphicons glyphicons-circle-ok"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
            <button type="button" class="save-continue"><span class="glyphicons glyphicons-circle-arrow-right"></span> {'common.save_and_continue'|devblocks_translate|capitalize}</button>
        {else}
            <button type="button" class="save"><span class="glyphicons glyphicons-circle-plus"></span> {'common.create'|devblocks_translate|capitalize}</button>
        {/if}
    </div>
</form>

<script type="text/javascript">
    $(function() {
        var $frm = $('#{$form_id}');
        var $popup = genericAjaxPopupFind($frm);

        Devblocks.formDisableSubmit($frm);

        $popup.one('popup_open', function() {
            $popup.dialog('option','title',"{'Automation Event'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
            $popup.css('overflow', 'inherit');

            // Buttons

            $popup.find('button.save').click(Devblocks.callbackPeekEditSave);
            $popup.find('button.save-continue').click({ mode: 'continue' }, Devblocks.callbackPeekEditSave);

            // Editor
            
            let autocomplete_suggestions = cerbAutocompleteSuggestions.kataAutomationEvent;
            
            {if $model->extension_id}
            autocomplete_suggestions['automation:uri:']['params']['automation'] = {
                'triggers': ['{$model->extension_id}']
            };
            {/if}

            var $editor = $popup.find('[name=automations_kata]')
                .cerbCodeEditor()
                .cerbCodeEditorAutocompleteKata({
                    autocomplete_suggestions: autocomplete_suggestions 
                })
                .next('pre.ace_editor')
            ;

            var editor = ace.edit($editor.attr('id'));

            {if $model->id}
            $popup.find('[data-cerb-editor-button-changesets]').on('click', function(e) {
                e.stopPropagation();

                var formData = new FormData();
                formData.set('c', 'internal');
                formData.set('a', 'invoke');
                formData.set('module', 'records');
                formData.set('action', 'showChangesetsPopup');
                formData.set('record_type', 'automation_event');
                formData.set('record_id', '{$model->id}');
                formData.set('record_key', 'automations_kata');

                var $editor_policy_differ_popup = genericAjaxPopup('editorDiff{$form_id}', formData, null, null, '80%');

                $editor_policy_differ_popup.one('cerb-diff-editor-ready', function(e) {
                    e.stopPropagation();

                    if(!e.hasOwnProperty('differ'))
                        return;

                    e.differ.editors.right.ace.setValue(editor.getValue());
                    e.differ.editors.right.ace.clearSelection();

                    e.differ.editors.right.ace.on('change', function() {
                        editor.setValue(e.differ.editors.right.ace.getValue());
                        editor.clearSelection();
                    });
                });
            });
            {/if}            

            // Toolbar

            var $toolbar = $popup.find('.cerb-code-editor-toolbar');
            
            $toolbar.cerbToolbar({
                caller: {
                    name: 'cerb.toolbar.editor',
                    params: {
                        //toolbar: 'cerb.toolbar.cardWidget.interactions',
                        selected_text: ''
                    }
                },
                start: function(formData) {
                    var pos = editor.getCursorPosition();
                    var token_path = Devblocks.cerbCodeEditor.getKataTokenPath(pos, editor).join('');

                    formData.set('caller[params][selected_text]', editor.getSelectedText());
                    formData.set('caller[params][token_path]', token_path);
                    formData.set('caller[params][cursor_row]', pos.row);
                    formData.set('caller[params][cursor_column]', pos.column);
                    formData.set('caller[params][trigger]', '{$model->extension_id}');
                    formData.set('caller[params][value]', editor.getValue());
                },
                done: function(e) {
                    e.stopPropagation();

                    var $target = e.trigger;

                    if(!$target.is('.cerb-bot-trigger'))
                        return;

                    if (e.eventData.exit === 'error') {

                    } else if(e.eventData.exit === 'return') {
                        Devblocks.interactionWorkerPostActions(e.eventData, editor);
                    }
                },
                reset: function(e) {
                    e.stopPropagation();
                }
            });
            
            $toolbar.cerbCodeEditorToolbarEventHandler({
                editor: editor
            });
        });
    });
</script>

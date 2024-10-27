{$peek_context = CerberusContexts::CONTEXT_WORKFLOW}
{$peek_context_id = $model->id}
{$form_id = uniqid('form')}
{$tabset_id = uniqid('tabs')}

<form action="{devblocks_url}{/devblocks_url}" method="post" id="{$form_id}">
    <input type="hidden" name="c" value="profiles">
    <input type="hidden" name="a" value="invoke">
    <input type="hidden" name="module" value="workflow">
    <input type="hidden" name="action" value="savePeekJson">
    <input type="hidden" name="view_id" value="{$view_id}">
    <input type="hidden" name="id" value="{if $model}{$model->id}{/if}">
    <input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

    <div id="{$tabset_id}" class="cerb-tabs">
        {if !$model->id && $packages}
            <ul>
                <li><a href="#workflow-library">{'common.library'|devblocks_translate|capitalize}</a></li>
                <li><a href="#workflow-builder">{'common.build'|devblocks_translate|capitalize}</a></li>
            </ul>
        {/if}

        {if !$model->id && $packages}
            <div id="workflow-library" class="package-library">
                {include file="devblocks:cerberusweb.core::internal/package_library/editor_chooser.tpl"}
            </div>
        {/if}

        <div id="workflow-builder">
        <table cellspacing="0" cellpadding="2" border="0" width="98%">
            {if $model->name}
                <h1>{$model->name}</h1>
                <div>{$model->description}</div>
            {/if}

            {if !empty($custom_fields)}
                {include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false tbody=true}
            {/if}
        </table>

        {include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=$peek_context context_id=$model->id}

        {if $model->id}
        <div class="cerb-code-editor-toolbar" style="margin:0.5em 0;">
            {if $model->config_kata}
            <button type="button" data-cerb-button-config-update data-cerb-template-section="config"><span class="glyphicons glyphicons-adjust-alt"></span> Edit Configuration</button>
            {/if}
            <button type="button" data-cerb-button-template-update><span class="glyphicons glyphicons-file-import"></span> Update Template</button>
        </div>
        {else}
            {if templates_layout && $templates_rows}
                <div>
                    {if 'fieldsets' == $templates_layout.style}
                        {include file="devblocks:cerberusweb.core::ui/sheets/render_fieldsets.tpl" layout=$templates_layout columns=$templates_columns rows=$templates_rows}
                    {elseif in_array($templates_layout.style, ['columns','grid'])}
                        {include file="devblocks:cerberusweb.core::ui/sheets/render_grid.tpl" layout=$templates_layout columns=$templates_columns rows=$templates_rows}
                    {else}
                        {include file="devblocks:cerberusweb.core::ui/sheets/render.tpl" layout=$templates_layout columns=$templates_columns rows=$templates_rows}
                    {/if}
                </div>
            {/if}
        {/if}

        <div data-cerb-summary>
        {include file="devblocks:cerberusweb.core::records/types/workflow/peek_edit/summary.tpl"}
        </div>

        <div class="buttons" style="margin-top:10px;">
            {if $model->id}
                <button type="button" class="save"><span class="glyphicons glyphicons-circle-ok"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
                <button type="button" class="save-continue"><span class="glyphicons glyphicons-circle-arrow-right"></span> {'common.save_and_continue'|devblocks_translate|capitalize}</button>
                {if $active_worker->hasPriv("contexts.{$peek_context}.delete")}<button type="button" class="delete-prompt"><span class="glyphicons glyphicons-circle-remove"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
            {else}
                <button type="button" class="create-library" style="display:none;"><span class="glyphicons glyphicons-circle-plus"></span> {'common.create'|devblocks_translate|capitalize}</button>
                <button type="button" class="create"><span class="glyphicons glyphicons-circle-arrow-right"></span> {'common.create_and_continue'|devblocks_translate|capitalize}</button>
            {/if}
        </div>
    </div>
</form>

<script nonce="{DevblocksPlatform::getRequestNonce()}" type="text/javascript">
    $(function() {
        let $frm = $('#{$form_id}');
        let $popup = genericAjaxPopupFind($frm);

        Devblocks.formDisableSubmit($frm);

        $popup.one('popup_open', function() {
            $popup.dialog('option','title',"{'common.workflow'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
            $popup.find('[autofocus]:first').focus();
            $popup.css('overflow', 'inherit');

            let $tab_builder = $popup.find('#workflow-builder');

            let funcAfter = function(e) {
                let popup_url = 'c=internal&a=invoke&module=records&action=showPeekPopup' +
                    '&context=' + encodeURIComponent(e.context) +
                    '&context_id=' + encodeURIComponent(e.id) +
                    '&view_id=' + encodeURIComponent(e.view_id) +
                    '&edit=true'
                ;

                let $new_popup = genericAjaxPopup('editor' + Devblocks.uniqueId(), popup_url, null, null, '50%');

                $new_popup.one('popup_open', function(evt) {
                    evt.stopPropagation();
                    setTimeout(function() {
                        $new_popup.find('button[data-cerb-button-template-update]').click();
                    }, 50);
                });
            };

            // Buttons
            $tab_builder.find('button.save').click(Devblocks.callbackPeekEditSave);
            $tab_builder.find('button.save-continue').click({ mode: 'continue' }, Devblocks.callbackPeekEditSave);
            $tab_builder.find('button.create').click({ mode: 'create', after: funcAfter }, Devblocks.callbackPeekEditSave);

            let onButtonTemplateUpdate = function(e) {
                e.preventDefault();
                e.stopPropagation();

                let $button = $(this);
                let section = $button.attr('data-cerb-template-section');

                let model_id = $frm.find('input[name=id]').val();
                if(!model_id) return;

                let formData = new FormData();
                formData.set('c', 'profiles');
                formData.set('a', 'invoke');
                formData.set('module', 'workflow');
                formData.set('action', 'showTemplateUpdatePopup');
                formData.set('id', model_id);

                if(section)
                    formData.set('section', section);

                let $update_popup = genericAjaxPopup('workflowTemplate', formData, '', '', '80%');

                $update_popup.on('template_updated', function(evt) {
                    evt.stopPropagation();

                    var layer = $popup.attr('data-layer');
                    var popup_url = 'c=internal&a=invoke&module=records&action=showPeekPopup' +
                        '&context=' + encodeURIComponent('workflow') +
                        '&context_id=' + encodeURIComponent(model_id) +
                        '&view_id=' + encodeURIComponent("{$view_id}") +
                        '&edit=true'
                    ;

                    // Body snatch
                    var $new_popup = genericAjaxPopup(layer, popup_url, 'reuse', false);
                    $new_popup.focus();
                });
            };

            $tab_builder.find('button[data-cerb-button-config-update').on('click', onButtonTemplateUpdate);
            $tab_builder.find('button[data-cerb-button-template-update').on('click', onButtonTemplateUpdate);

            {if $model->id}
            $tab_builder.find('button.delete-prompt').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                let formData = new FormData();
                formData.set('c', 'profiles');
                formData.set('a', 'invoke');
                formData.set('module', 'workflow');
                formData.set('action', 'showWorkflowDeletePopup');
                formData.set('id', '{$model->id}');

                let $delete_popup = genericAjaxPopup('workflowDelete', formData, '', '', '80%');

                $delete_popup.on('workflow_delete', function(evt) {
                    evt.stopPropagation();

                    {if $view_id}
                    genericAjaxGet('view{$view_id}', 'c=internal&a=invoke&module=worklists&action=refresh&id={$view_id}');
                    {/if}

                    genericAjaxPopupClose($popup, 'peek_deleted');
                });
            });
            {/if}

            // Package Library

            {if !$model->id && $packages}
            let tabOptions = Devblocks.getDefaultjQueryUiTabOptions();
            tabOptions.active = Devblocks.getjQueryUiTabSelected('{$tabset_id}');

            let $tabs = $popup.find('.cerb-tabs').tabs(tabOptions);

            let $library_container = $tabs;
            {include file="devblocks:cerberusweb.core::internal/package_library/editor_chooser.js.tpl"}

            $library_container.on('cerb-package-library-form-submit', function(e) {
                e.stopPropagation();

                $tab_builder.find('button.create-library')
                    .off('click')
                    .click(
                        {
                            mode: 'continue',
                            after: function(evt) {
                                $library_container.triggerHandler('cerb-package-library-form-submit--done');

                                if(evt.hasOwnProperty('error')) {
                                    return;
                                }

                                let layer = $popup.attr('data-layer');
                                let popup_url = 'c=internal&a=invoke&module=records&action=showPeekPopup' +
                                    '&context=' + encodeURIComponent(evt.context) +
                                    '&context_id=' + encodeURIComponent(evt.id) +
                                    '&view_id=' + encodeURIComponent(evt.view_id) +
                                    '&edit=true'
                                ;
                                let $new_popup = genericAjaxPopup(layer, popup_url, 'reuse', false);

                                setTimeout(function() {
                                    $new_popup.find('button[data-cerb-button-template-update]').click();
                                }, 50);
                            }
                        },
                        Devblocks.callbackPeekEditSave
                    )
                    .click()
                ;
            });
            {/if}
        });
    });
</script>

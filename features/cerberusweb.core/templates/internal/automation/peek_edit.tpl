{$peek_context = CerberusContexts::CONTEXT_AUTOMATION}
{$peek_context_id = $model->id}
{$form_id = uniqid()}
{if $model}{$extension = $model->getTriggerExtension()}{else}{$extension = null}{/if}

<form action="{devblocks_url}{/devblocks_url}" method="post" id="editor{$form_id}" onsubmit="return false;">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="invoke">
<input type="hidden" name="module" value="automation">
<input type="hidden" name="action" value="savePeekJson">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
<input type="hidden" name="is_simulator" value="1">
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<table cellspacing="0" cellpadding="2" border="0" width="98%">
	<tr>
		<td width="1%" nowrap="nowrap">
			<b>{'common.name'|devblocks_translate|capitalize}:</b>
		</td>
		<td width="99%">
			<input type="text" name="name" value="{$model->name}" autofocus="autofocus" style="width:100%;" spellcheck="false">
		</td>
	</tr>

	<tr>
		<td width="1%" nowrap="nowrap">
			<b>{'common.description'|devblocks_translate|capitalize}:</b>
		</td>
		<td width="99%">
			<input type="text" name="description" value="{$model->description}" style="width:100%;">
		</td>
	</tr>

	<tr>
		<td width="1%" valign="top" nowrap="nowrap">
			<b>{'common.trigger'|devblocks_translate|capitalize}:</b>
		</td>
		<td width="99%">
			<select name="extension_id">
				<option value=""></option>
				{foreach from=$extensions item=extension}
					<option value="{$extension->id}" {if $model->extension_id==$extension->id}selected="selected"{/if}>{$extension->name}</option>
				{/foreach}
			</select>

			<div data-cerb-extension-params>
				{if $extension}
				{$extension->renderConfig($model)}
				{/if}
			</div>
		</td>
	</tr>

	{if !empty($custom_fields)}
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false tbody=true}
	{/if}
</table>

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=$peek_context context_id=$model->id}

<div data-cerb-automation-editor-script>
	<div class="cerb-code-editor-toolbar">
		<div data-cerb-toolbar style="display:inline-block;">
			{$toolbar_dict = DevblocksDictionaryDelegate::instance([
			'worker__context' => CerberusContexts::CONTEXT_WORKER,
			'worker_id' => $active_worker->id
			])}

			{$toolbar_kata =
"menu/insert:
  icon: circle-plus
  items:
    menu/inputs:
      label: Inputs
      items:
        interaction/record:
          label: Record
          name: ai.cerb.automationBuilder.input.record
        interaction/records:
          label: Records
          name: ai.cerb.automationBuilder.input.records
        interaction/text:
          label: Text
          name: ai.cerb.automationBuilder.input.text
    menu/control:
      label: Control
      items:
        interaction/decision:
          label: Decision
          name: ai.cerb.automationBuilder.command.decision
        interaction/outcome:
          label: Outcome
          name: ai.cerb.automationBuilder.command.outcome
        interaction/repeat:
          label: Repeat
          name: ai.cerb.automationBuilder.command.repeat
    menu/actions:
      label: Actions
      items:
        interaction/data_query:
          label: Data query
          name: ai.cerb.automationBuilder.action.dataQuery
        menu/actions_email:
          label: Email
          items:
        interaction/email_parser:
          label: Parser
          name: ai.cerb.automationBuilder.action.emailParser
        interaction/function:
          label: Function
          name: ai.cerb.automationBuilder.action.function
        interaction/http_request:
          label: HTTP request
          name: ai.cerb.automationBuilder.action.httpRequest
        menu/actions_record:
          label: Record
          items:
            interaction/record_create:
              label: Create
              name: ai.cerb.automationBuilder.action.recordCreate
            interaction/record_delete:
              label: Delete
              name: ai.cerb.automationBuilder.action.recordDelete
            interaction/record_get:
              label: Get
              name: ai.cerb.automationBuilder.action.recordGet
            interaction/record_update:
              label: Update
              name: ai.cerb.automationBuilder.action.recordUpdate
    menu/yields:
      label: Yields
      items:
        menu/yield_respond:
          label: Form Response
          items:
            interaction/respond_say:
              label: Say
              name: ai.cerb.automationBuilder.ui.interaction.yield.say
            interaction/respond_map:
              label: Map
              name: ai.cerb.automationBuilder.ui.interaction.yield.map
        menu/yield_form:
          label: Form Prompt
          items:
            interaction/prompt_choice:
              label: Choice
              name: ai.cerb.automationBuilder.ui.interaction.yield.promptChoice
            interaction/prompt_editor:
              label: Editor
              name: ai.cerb.automationBuilder.ui.interaction.yield.promptEditor
            interaction/prompt_sheet:
              label: Sheet
              name: ai.cerb.automationBuilder.ui.interaction.yield.promptSheet
            interaction/prompt_text:
              label: Text
              name: ai.cerb.automationBuilder.ui.interaction.yield.promptText
"
			}

			{$toolbar = DevblocksPlatform::services()->ui()->toolbar()->parse($toolbar_kata, $toolbar_dict)}

			{DevblocksPlatform::services()->ui()->toolbar()->render($toolbar)}
		</div>

		<button type="button" class="cerb-code-editor-toolbar-button cerb-code-editor-toolbar-button--help" title="{'common.help'|devblocks_translate|capitalize}"><span class="glyphicons glyphicons-circle-question-mark"></span></button>
	</div>
	<textarea name="automation_script" data-editor-mode="ace/mode/cerb_kata" data-editor-lines="25">{$model->script}</textarea>
</div>

{$tabs_uid = uniqid('automationTabs')}
<div id="{$tabs_uid}" style="margin-top:10px;" data-cerb-automation-editor-tabs>
	<ul>
		<li data-cerb-tab="run"><a href="#{$tabs_uid}Run">{'common.run'|devblocks_translate|capitalize}</a></li>
		<li data-cerb-tab="policy"><a href="#{$tabs_uid}Policy">{'common.policy'|devblocks_translate|capitalize}</a></li>
		{*<li data-cerb-tab="versions"><a href="#{$tabs_uid}Versions">{'common.versions'|devblocks_translate|capitalize}</a></li>*}
		<li data-cerb-tab="visualization"><a href="#{$tabs_uid}Visualization">Visualization</a></li>
	</ul>

	<div id="{$tabs_uid}Run">
		<div style="display:flex;">
			<fieldset class="peek black no-legend" style="flex:1 1 50%;padding:5px;" data-cerb-automation-editor-state-start>
				<legend>
					{'common.input'|devblocks_translate|capitalize}: <small>(YAML)</small>
				</legend>

				<div class="cerb-code-editor-toolbar">
					<button type="button" title="Simulate" class="cerb-code-editor-toolbar-button cerb-editor-toolbar-button--mode" data-mode="simulator">Simulate</button>
					<div class="cerb-code-editor-toolbar-divider"></div>
					<button type="button" class="cerb-code-editor-toolbar-button cerb-code-editor-toolbar-button--run" title="{'common.run'|devblocks_translate|capitalize}"><span class="glyphicons glyphicons-play"></span></button>
					<button type="button" class="cerb-code-editor-toolbar-button" title="{'common.help'|devblocks_translate|capitalize}"><span class="glyphicons glyphicons-circle-question-mark"></span></button>
				</div>

				<textarea name="start_state_yaml" data-editor-mode="ace/mode/yaml" rows="5" cols="45"></textarea>
			</fieldset>

			<fieldset class="peek black no-legend" style="flex:1 1 50%;padding:5px;" data-cerb-automation-editor-state-end>
				<legend>
					{'common.output'|devblocks_translate|capitalize}: <small>(YAML)</small>
				</legend>

				<div class="cerb-code-editor-toolbar">
					<button type="button" class="cerb-code-editor-toolbar-button cerb-code-editor-toolbar-button--step" title="Copy to input" style="display:none;"><span class="glyphicons glyphicons-left-arrow"></span></button>
					<button type="button" class="cerb-code-editor-toolbar-button" title="{'common.help'|devblocks_translate|capitalize}"><span class="glyphicons glyphicons-circle-question-mark"></span></button>
				</div>

				<textarea name="end_state_yaml" data-editor-mode="ace/mode/yaml" rows="5" cols="45"></textarea>
			</fieldset>
		</div>
	</div>

	<div id="{$tabs_uid}Policy">
		<div class="cerb-code-editor-toolbar">
			<button type="button" class="cerb-code-editor-toolbar-button" title="{'common.help'|devblocks_translate|capitalize}"><span class="glyphicons glyphicons-circle-question-mark"></span></button>
		</div>

		<textarea name="automation_policy_kata" data-editor-mode="ace/mode/yaml" data-editor-lines="25">{$model->policy_kata}</textarea>
	</div>

	{*
	<div id="{$tabs_uid}Versions">
	</div>
	*}

	<div id="{$tabs_uid}Visualization">

	</div>
</div>

{if !empty($model->id)}
<fieldset style="display:none;" class="delete">
	<legend>{'common.delete'|devblocks_translate|capitalize}</legend>

	<div>
		Are you sure you want to permanently delete this automation?
	</div>

	<button type="button" class="delete red">{'common.yes'|devblocks_translate|capitalize}</button>
	<button type="button" onclick="$(this).closest('form').find('div.buttons').fadeIn();$(this).closest('fieldset.delete').fadeOut();">{'common.no'|devblocks_translate|capitalize}</button>
</fieldset>
{/if}

<div class="buttons" style="margin-top:10px;">
	<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
	{if $model->id}<button type="button" class="save-continue"><span class="glyphicons glyphicons-circle-arrow-right" style="color:rgb(0,180,0);"></span> {'common.save_and_continue'|devblocks_translate|capitalize}</button>{/if}
	{if !empty($model->id) && $active_worker->hasPriv("contexts.{$peek_context}.delete")}<button type="button" onclick="$(this).parent().siblings('fieldset.delete').fadeIn();$(this).closest('div').fadeOut();"><span class="glyphicons glyphicons-circle-remove" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}
</div>
</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#editor{$form_id}');
	var $popup = genericAjaxPopupFind($frm);

	$popup.one('popup_open', function() {
		$frm.find('[data-cerb-automation-editor-tabs]').tabs({
			beforeActivate: function(event, ui) {
				if(ui.newTab.attr('data-cerb-tab') !== 'visualization')
					return;

				Devblocks.getSpinner().appendTo(ui.newPanel.html(''));

				var formData = new FormData();
				formData.set('c', 'profiles');
				formData.set('a', 'invoke');
				formData.set('module', 'automation');
				formData.set('action', 'editorVisualize');
				formData.set('script', editor_automation.getValue());

				genericAjaxPost(formData, null, null, function(html) {
					ui.newPanel.html(html);
				});
			}
		});

		var $script_toolbar = $frm.find('.cerb-code-editor-toolbar');

		var $automation_yaml = $frm.find('textarea[name=automation_script]');

		var $state_start = $frm.find('[data-cerb-automation-editor-state-start]');
		var $state_yaml = $state_start.find('textarea[name=start_state_yaml]');
		var $state_start_toolbar = $state_start.find('.cerb-code-editor-toolbar');
		var $button_run = $state_start_toolbar.find('.cerb-code-editor-toolbar-button--run');
		var $toggle_mode = $state_start_toolbar.find('.cerb-editor-toolbar-button--mode');

		var $state_end = $frm.find('[data-cerb-automation-editor-state-end]');
		var $end_state_yaml = $state_end.find('textarea[name=end_state_yaml]');
		var $state_end_toolbar = $state_end.find('.cerb-code-editor-toolbar');
		var $button_step = $state_end_toolbar.find('.cerb-code-editor-toolbar-button--step');

		var $spinner = Devblocks.getSpinner().css('max-width', '16px');
		var highlight_marker = null;

		var $extension_params = $frm.find('[data-cerb-extension-params]');

		$popup.dialog('option', 'title', "{'common.automation'|devblocks_translate|capitalize|escape:js}");
		$popup.css('overflow', 'inherit');

		// Buttons
		$popup.find('button.submit').click(Devblocks.callbackPeekEditSave);
		$popup.find('button.save-continue').click({ mode: 'continue' }, Devblocks.callbackPeekEditSave);
		$popup.find('button.delete').click({ mode: 'delete' }, Devblocks.callbackPeekEditSave);

		// Close confirmation

		$popup.on('dialogbeforeclose', function(e, ui) {
			var keycode = e.keyCode || e.which;
			if(keycode === 27)
				return confirm('{'warning.core.editor.close'|devblocks_translate}');
		});

		// Extension select

		$popup.find('select[name=extension_id]').on('change', function() {
			var $select = $(this);
			var extension_id = $select.val();

			$extension_params.empty().append(Devblocks.getSpinner());

			if(extension_id.length === 0) {
				$extension_params.empty();

				$editor_automation
					.cerbCodeEditorAutocompleteKata({
						autocomplete_suggestions: []
					})
				;
				return;
			}

			var formData;

			// Update config for trigger

			formData = new FormData();
			formData.set('c', 'profiles');
			formData.set('a', 'invoke');
			formData.set('module', 'automation');
			formData.set('action', 'getExtensionConfig');
			formData.set('extension_id', extension_id);

			genericAjaxPost(formData, $extension_params);

			// Update autocompletion for editor

			formData = new FormData();
			formData.set('c', 'profiles');
			formData.set('a', 'invoke');
			formData.set('module', 'automation');
			formData.set('action', 'getAutocompleteJson');
			formData.set('extension_id', extension_id);

			genericAjaxPost(formData, null, null, function(json) {
				$editor_automation
					.cerbCodeEditorAutocompleteKata({
						autocomplete_suggestions: json
					})
					;
			});
		});

		$popup.find('.chooser-abstract').cerbChooserTrigger();

		$popup.find('.cerb-peek-trigger').cerbPeekTrigger();

		var $editor_automation = $popup.find('textarea[name=automation_script]')
			.cerbCodeEditor()
			{if is_a($extension, 'Extension_AutomationTrigger')}
			{$autocomplete_json = $extension->getAutocompleteSuggestionsJson()}
			{if is_string($autocomplete_json)}
			.cerbCodeEditorAutocompleteKata({
				autocomplete_suggestions: {$autocomplete_json nofilter}
			})
			{/if}
			{/if}
			;

		var $editor_policy = $popup.find('textarea[name=automation_policy_kata]')
			.cerbCodeEditor()
			;

		$popup.find('textarea[name=start_state_yaml], textarea[name=end_state_yaml]')
			.cerbCodeEditor()
			;

		var editor_automation = ace.edit($automation_yaml.nextAll('pre.ace_editor').attr('id'));
		var editor_state_start = ace.edit($state_yaml.nextAll('pre.ace_editor').attr('id'));
		var editor_state_end = ace.edit($end_state_yaml.nextAll('pre.ace_editor').attr('id'));
		var editor_policy = ace.edit($editor_policy.nextAll('pre.ace_editor').attr('id'));

		{if $cursor}
		editor_automation.gotoLine({$cursor.row}, {$cursor.column});
		{/if}

		editor_automation.focus();

		editor_state_start.setOption('minLines', 15);
		editor_state_start.setOption('maxLines', 15);

		editor_state_end.setOption('minLines', 15);
		editor_state_end.setOption('maxLines', 15);

		$button_step
			.click(function() {
				Devblocks.clearAlerts();

				editor_state_start.setValue(editor_state_end.getValue());
				editor_state_start.clearSelection();

				editor_state_end.setValue('');

				$button_step.hide();
			})
		;

		$button_run
			.click(function() {
				Devblocks.clearAlerts();

				$spinner.insertAfter($button_run);
				$button_run.hide();
				$button_step.hide();

				if(null != highlight_marker) {
					editor_automation.session.removeMarker(highlight_marker.id);
					highlight_marker = null;
				}

				var formData = new FormData($frm[0]);
				formData.set('c', 'profiles');
				formData.set('a', 'invoke');
				formData.set('module', 'automation');
				formData.set('action', 'runAutomationEditor');

				genericAjaxPost(formData, null, null, function(json) {
					$spinner.detach();
					$button_run.fadeIn();

					if('object' != typeof json) {
						Devblocks.createAlertError("An unexpected error occurred.");
						return;
					}

					if(json.error) {
						Devblocks.createAlertError(json.error);
						return;
					}

					if(json.exit === 'yield') {
						$button_step.fadeIn();
					}

					if(json.hasOwnProperty('exit_state')) {
						var state_path = json.exit_state;

						var row = Devblocks.cerbCodeEditor.getYamlRowByPath(editor_automation, state_path);

						if(row) {
							highlight_marker = editor_automation.session.highlightLines(row,row);
							editor_automation.scrollToLine(row);
						}
					}

					editor_state_end.setValue(json.dict);
					editor_state_end.clearSelection();
				});
			})
		;

		$popup.on('cerb-automation-editor--goto', function(e) {
			if(null != highlight_marker) {
				editor_automation.session.removeMarker(highlight_marker.id);
				highlight_marker = null;
			}

			if(!e.hasOwnProperty('editor_line'))
				return;

			var row = e.editor_line;

			if(false !== row) {
				highlight_marker = editor_automation.session.highlightLines(row,row);
				editor_automation.scrollToLine(row);
			}
		});

		// Formatting

		$toggle_mode.on('click', function() {
			if('simulator' === $toggle_mode.attr('data-mode')) {
				$script_toolbar.triggerHandler($.Event('cerb-editor-toolbar-mode-set', { simulator: false }));
			} else {
				$script_toolbar.triggerHandler($.Event('cerb-editor-toolbar-mode-set', { simulator: true }));
			}
		});

		$script_toolbar.on('cerb-editor-toolbar-mode-set', function(e) {
			if(e.hasOwnProperty('simulator')) {
				if(e.simulator) {
					$frm.find('input:hidden[name=is_simulator]').val('1');
					$toggle_mode.attr('data-mode', 'simulator');
					$toggle_mode.text('Simulate');
				} else {
					$frm.find('input:hidden[name=is_simulator]').val('0');
					$toggle_mode.attr('data-mode', 'live');
					$toggle_mode.text('Execute');
				}
			}
		});

		var $script_custom_toolbar = $script_toolbar.find('[data-cerb-toolbar]');

		var doneFunc = function(e) {
			e.stopPropagation();

			var $target = e.trigger;

			if(!$target.is('.cerb-bot-trigger'))
				return;

			//var done_params = new URLSearchParams($target.attr('data-interaction-done'));

			if(e.eventData.snippet) {
				editor_automation.insertSnippet(e.eventData.snippet);
			}
		};

		// Buttons
		$script_custom_toolbar
			.find('> button[data-cerb-toolbar-button]')
			.cerbBotTrigger({
				done: doneFunc
			})
		;

		// Menus
		$script_custom_toolbar
			.find('> button[data-cerb-toolbar-menu]')
			.on('click', function() {
				var $this = $(this);
				var $ul = $(this).next('ul').toggle();

				$ul.position({
					my: 'left top',
					at: 'left bottom',
					of: $this,
					collision: 'fit'
				});
			})
			.next('ul.cerb-float')
			.menu()
			.find('li.cerb-bot-trigger')
			.cerbBotTrigger({
				done: doneFunc
			})
			.on('click', function(e) {
				e.stopPropagation();
				$(this).closest('ul.cerb-float').hide();
			})
		;

	});
});
</script>
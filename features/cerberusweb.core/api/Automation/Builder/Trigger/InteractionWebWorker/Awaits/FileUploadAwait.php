<?php
namespace Cerb\Automation\Builder\Trigger\InteractionWebWorker\Awaits;

use _DevblocksValidationService;
use DevblocksPlatform;
use Model_AutomationContinuation;

class FileUploadAwait extends AbstractAwait {
	function invoke(string $prompt_key, string $action, Model_AutomationContinuation $continuation) {
		return false;
	}

	function validate(_DevblocksValidationService $validation) {
		@$prompt_label = $this->_data['label'];
		
		$is_required = array_key_exists('required', $this->_data) && $this->_data['required'];
		
		$input_field = $validation->addField($this->_key, $prompt_label);
		
		$input_field_type = $input_field->id()
			->addValidator($validation->validators()->contextId(\CerberusContexts::CONTEXT_ATTACHMENT, !$is_required))
			;
		
		// [TODO] Validate file types
		// [TODO] Validate file sizes
		
		if($is_required)
			$input_field_type->setRequired(true);
	}
	
	function formatValue() {
		return $this->_value;
	}
	
	function render(Model_AutomationContinuation $continuation) {
		$tpl = DevblocksPlatform::services()->template();
		
		@$label = $this->_data['label'];
		@$placeholder = $this->_data['placeholder'];
		@$default = $this->_data['default'];
		$is_required = array_key_exists('required', $this->_data) && $this->_data['required'];
	
		$tpl->assign('label', $label);
		$tpl->assign('placeholder', $placeholder);
		$tpl->assign('default', $default);
		$tpl->assign('var', $this->_key);
		$tpl->assign('value', $this->_value);
		$tpl->assign('is_required', $is_required);
		$tpl->display('devblocks:cerberusweb.core::automations/triggers/interaction.web.worker/await/file_upload.tpl');
	}
}
<?php

/**
 * @file plugins/generic/authorRequirements/AuthorRequirementsPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorRequirementsPlugin
 * @ingroup plugins_generic_authorRequirements
 *
 * @brief Author Requirements plugin class
 */

namespace APP\plugins\generic\authorRequirements;

use APP\controllers\grid\users\author\form\AuthorForm;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\components\forms\Field;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\publication\ContributorForm;
use PKP\core\JSONMessage;
use PKP\form\Form;
use PKP\form\validation\FormValidatorEmail;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;

class AuthorRequirementsPlugin extends GenericPlugin
{
    /**
     * Get the display name of this plugin
     * @return string
     */
    public function getDisplayName()
    {
        return __('plugins.generic.authorRequirements.displayName');
    }

    /**
     * Get the description of this plugin
     * @return string
     */
    public function getDescription()
    {
        return __('plugins.generic.authorRequirements.description');
    }

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {

        // Register the plugin even when it is not enabled
        $success = parent::register($category, $path);

        if ($success && $this->getEnabled()) {

            $contextId = $this->getCurrentContextId();

            // Deals with making email optional
            if ($this->getSetting($contextId, 'emailOptional')) {
                // Component-based form
                Hook::add('Form::config::before', [$this, 'modifyContributorForm']);
                Hook::add('Schema::get::author', [$this, 'modifyAuthorSchema']);

                // Legacy forms (for Quick Submit plugin)
                Hook::add('TemplateResource::getFilename', [$this, '_overridePluginTemplates']);
                Hook::add('TemplateManager::fetch', [$this, 'overrideFormDisplay']);
                Hook::add('TemplateManager::fetch', [$this, 'overrideFormCreation']);
                Hook::add('authorform::readuservars', [$this, 'overrideFormValidation']);
            }

            // New features
            if ($this->getSetting($contextId, 'familyNameRequired')) {
                Hook::add('Form::config::before', [$this, 'makeLastNameRequired']);
                Hook::add('Schema::get::author', [$this, 'modifyAuthorSchemaForLastName']);
            }

            if ($this->getSetting($contextId, 'defaultCountry')) {
                Hook::add('Form::config::before', [$this, 'setDefaultCountry']);
            }

            if ($this->getSetting($contextId, 'authorUserGroupOnly')) {
                Hook::add('Form::config::before', [$this, 'restrictToAuthorUserGroup']);
            }

            if ($this->getSetting($contextId, 'disableBio')) {
                Hook::add('Form::config::before', [$this, 'disableBioField']);
            }

            if ($this->getSetting($contextId, 'disableUrl')) {
                Hook::add('Form::config::before', [$this, 'disableUrlField']);
            }

            if ($this->getSetting($contextId, 'disablePreferredPublicName')) {
                Hook::add('Form::config::before', [$this, 'disablePreferredPublicNameField']);
            }

            // Hook for PKPAuthorForm (grid form)
            Hook::add('pkpauthorform::initdata', [$this, 'initAuthorFormData']);
        }
        return $success;
    }

    /**
     * Make contributor email optional in ContributorForm
     */
    public function modifyContributorForm($hookName, $form): bool
    {
        if (!$form instanceof ContributorForm) {
            return Hook::CONTINUE;
        }

        $form->fields = array_map(function(Field $field) {
            if ($field->name === 'email') {
                $field->isRequired = false;
            }

            return $field;
        }, $form->fields);

        return Hook::CONTINUE;
    }

    /**
     * Overrides visual presentation for required author form elements.
     */
    public function overrideFormDisplay($hookname, $args): bool
    {
        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[0];
        /** @var string $template */
        $template = $args[1];

        if ($template !== 'controllers/grid/users/author/form/authorForm.tpl') {
            return Hook::CONTINUE;
        }

        $templateMgr->assign('emailNotRequired', true);

        return Hook::CONTINUE;
    }

    /**
     * Overrides form creation regarding required and optional author form elements.
     */
    public function overrideFormCreation($hookname, $args): bool
    {

        /** @var TemplateManager $templateMgr */
        $templateMgr = $args[0];
        $form = $templateMgr->getFBV()->getForm();

        if ($form) {
            $this->emailOverride($form);
        }

        return Hook::CONTINUE;
    }

    /**
     * Overrides form validation for optional elements.
     */
    public function overrideFormValidation($hookname, $args): bool
    {
        /** @var Form $form */
        $form = $args[0];
        if ($form) {
            $this->emailOverride($form);
        }

        return Hook::CONTINUE;
    }

    /**
     * Overrides the email requirement for authors during form creation and validation.
     */
    public function emailOverride(Form $form): void
    {
        if (!($form instanceof AuthorForm)) {
            return;
        }

        // Remove email check from check list
        $checks =& $form->_checks;
        foreach ($checks as $k => $check) {
            if ($check instanceof FormValidatorEmail) {
                unset($checks[$k]);
                $checks = array_values($checks);
                break;
            }
        }

        // Remove css validator element
        $cssValidation =& $form->cssValidation;
        unset($cssValidation['email']);

        // Add optional email form validation back in
        $form->addCheck(new FormValidatorEmail($form, 'email', 'optional'));
    }

    /**
     * Make family name required in ContributorForm
     */
    public function makeLastNameRequired($hookName, $form): bool
    {
        if (!$form instanceof ContributorForm) {
            return Hook::CONTINUE;
        }

        foreach ($form->fields as $field) {
            if ($field->name === 'familyName') {
                $field->isRequired = true;
            }
        }

        return Hook::CONTINUE;
    }

    /**
     * Set default country for new contributors
     */
    public function setDefaultCountry($hookName, $form): bool
    {
        if (!$form instanceof ContributorForm) {
            return Hook::CONTINUE;
        }

        // Get current context's country setting
        $request = \PKP\core\PKPApplication::get()->getRequest();
        $context = $request->getContext();
        
        if ($context && $context->getData('country')) {
            foreach ($form->fields as $field) {
                if ($field->name === 'country' && $field instanceof FieldSelect) {
                    $field->isRequired = true;
                    if (!$field->value) {
                        $field->value = $context->getData('country');
                    }
                }
            }
        }

        return Hook::CONTINUE;
    }

    /**
     * Restrict user group selection to Author group only
     */
    public function restrictToAuthorUserGroup($hookName, $form): bool
    {
        if (!$form instanceof ContributorForm) {
            return Hook::CONTINUE;
        }

        $newFields = [];
        $hiddenValue = null;
        
        foreach ($form->fields as $field) {
            if ($field->name === 'userGroupId') {
                if ($field instanceof FieldOptions) {
                    // Filter options to only include Author user groups
                    $authorOptions = collect($field->options)->filter(function($option) {
                        return stripos($option['label'], 'author') !== false || stripos($option['label'], 'tác giả') !== false;
                    });
                    
                    if ($authorOptions->count() >= 1) {
                        $hiddenValue = $authorOptions->first()['value'];
                    } else {
                        // If no author group found, use the first available option
                        $firstOption = collect($field->options)->first();
                        $hiddenValue = $firstOption ? $firstOption['value'] : $field->value;
                    }
                } else {
                    // If already a different field type, still remove it
                    $hiddenValue = $field->value ?? '';
                }
            } else {
                $newFields[] = $field;
            }
        }
        
        // Add to hidden fields if we found a value
        if ($hiddenValue !== null) {
            $form->hiddenFields['userGroupId'] = $hiddenValue;
        }
        
        $form->fields = $newFields;
        return Hook::CONTINUE;
    }

    /**
     * Disable biography field - convert to hidden
     */
    public function disableBioField($hookName, $form): bool
    {
        if (!$form instanceof ContributorForm) {
            return Hook::CONTINUE;
        }

        $newFields = [];
        foreach ($form->fields as $field) {
            if ($field->name === 'biography') {
                // Add to hidden fields instead of creating FieldHidden
                $form->hiddenFields['biography'] = is_array($field->value) ? $field->value : ($field->value ?? []);
                // Don't add to newFields - effectively removes it from visible fields
            } else {
                $newFields[] = $field;
            }
        }
        
        $form->fields = $newFields;
        return Hook::CONTINUE;
    }

    /**
     * Disable URL field - convert to hidden
     */
    public function disableUrlField($hookName, $form): bool
    {
        if (!$form instanceof ContributorForm) {
            return Hook::CONTINUE;
        }

        $newFields = [];
        foreach ($form->fields as $field) {
            if ($field->name === 'url') {
                // Add to hidden fields instead of creating FieldHidden
                $form->hiddenFields['url'] = $field->value ?? '';
                // Don't add to newFields - effectively removes it from visible fields
            } else {
                $newFields[] = $field;
            }
        }
        
        $form->fields = $newFields;
        return Hook::CONTINUE;
    }

    /**
     * Disable Preferred Public Name field - convert to hidden
     */
    public function disablePreferredPublicNameField($hookName, $form): bool
    {
        if (!$form instanceof ContributorForm) {
            return Hook::CONTINUE;
        }

        $newFields = [];
        foreach ($form->fields as $field) {
            if ($field->name === 'preferredPublicName') {
                // Add to hidden fields instead of creating FieldHidden
                $form->hiddenFields['preferredPublicName'] = is_array($field->value) ? $field->value : ($field->value ?? []);
                // Don't add to newFields - effectively removes it from visible fields
            } else {
                $newFields[] = $field;
            }
        }
        
        $form->fields = $newFields;
        return Hook::CONTINUE;
    }

    /**
     * Initialize data for PKPAuthorForm (grid forms)
     */
    public function initAuthorFormData($hookName, $args): bool
    {
        $form = $args[0];
        $contextId = $this->getCurrentContextId();
        
        // Set default country for new authors
        if ($this->getSetting($contextId, 'defaultCountry') && !$form->getAuthor()) {
            $request = \PKP\core\PKPApplication::get()->getRequest();
            $context = $request->getContext();
            
            if ($context && $context->getData('country')) {
                $form->setData('country', $context->getData('country'));
            }
        }

        return Hook::CONTINUE;
    }

    /**
     * Make email nullable in author schema
     */
    public function modifyAuthorSchema($hookName, $args): bool
    {
        $schema = &$args[0];
        $schema->required = array_filter($schema->required, fn ($item) => $item !== 'email');
        $schema->properties->email->validation[] = 'nullable';

        return Hook::CONTINUE;
    }

    /**
     * Make family name required in author schema
     */
    public function modifyAuthorSchemaForLastName($hookName, $args): bool
    {
        $schema = &$args[0];
        
        if (!in_array('familyName', $schema->required)) {
            $schema->required[] = 'familyName';
        }

        return Hook::CONTINUE;
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        $url = $router->url($request, null, null, 'manage', null, ['verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic']);
        array_unshift($actions, new LinkAction('settings', new AjaxModal($url, $this->getDisplayName()), __('manager.plugins.settings')));
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'settings') {
            return parent::manage($args, $request);
        }

        $form = new AuthorRequirementsSettingsForm($this, $request->getContext()->getId());
        if (!$request->getUserVar('save')) {
            $form->initData();
            return new JSONMessage(true, $form->fetch($request));
        }

        $form->readInputData();
        if (!$form->validate()) {
            return new JSONMessage(true, $form->fetch($request));
        }

        try {
            $form->execute();
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification($request->getUser()->getId());
        } catch (\Exception $exception) {
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification(
                $request->getUser()->getId(),
                \PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('common.error.databaseError', ['error' => $exception->getMessage()])],
            );
        }
        return new JSONMessage(true);
    }
}

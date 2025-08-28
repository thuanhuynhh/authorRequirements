<?php

/**
 * @file plugins/generic/authorRequirements/AuthorRequirementsSettingsForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorRequirementsSettingsForm
 * @ingroup plugins_generic_authorRequirements
 *
 * @brief Form for managers to modify Author Requirements plugin settings
 */

namespace APP\plugins\generic\authorRequirements;

use APP\template\TemplateManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class AuthorRequirementsSettingsForm extends Form
{

    private int $_contextId;
    private AuthorRequirementsPlugin $_plugin;

    function __construct(AuthorRequirementsPlugin $plugin, int $contextId)
    {
        $this->_contextId = $contextId;
        $this->_plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * Initialize form data
     */
    public function initData()
    {
        $contextId = $this->_contextId;
        $plugin = $this->_plugin;

        $this->setData('emailOptional', $plugin->getSetting($contextId, 'emailOptional'));
        $this->setData('familyNameRequired', $plugin->getSetting($contextId, 'familyNameRequired'));
        $this->setData('defaultCountry', $plugin->getSetting($contextId, 'defaultCountry'));
        $this->setData('authorUserGroupOnly', $plugin->getSetting($contextId, 'authorUserGroupOnly'));
        $this->setData('disableBio', $plugin->getSetting($contextId, 'disableBio'));
        $this->setData('disableUrl', $plugin->getSetting($contextId, 'disableUrl'));
        $this->setData('disablePreferredPublicName', $plugin->getSetting($contextId, 'disablePreferredPublicName'));
        parent::initData();
    }

    /**
     * Assign form data to user-submitted data
     */
    public function readInputData()
    {
        $this->readUserVars(array(
            'emailOptional',
            'familyNameRequired',
            'defaultCountry',
            'authorUserGroupOnly',
            'disableBio',
            'disableUrl',
            'disablePreferredPublicName'
        ));
        parent::readInputData();
    }

    /**
     * Fetch the form.
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->_plugin->getName());

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save settings.
     */
    public function execute(...$functionArgs)
    {
        $plugin = $this->_plugin;
        $contextId = $this->_contextId;

        $isEmailOptional = $this->getData('emailOptional');

        if ($isEmailOptional) {
            Schema::table('authors', function (Blueprint $table) {
                $table->string('email', 90)->nullable()->change();
            });
        } else {
            DB::table('authors')
                ->whereNull('email')
                ->update(['email' => '']);
            Schema::table('authors', function (Blueprint $table) {
                $table->string('email', 90)->change();
            });
        }

        $plugin->updateSetting($contextId, 'emailOptional', $isEmailOptional, 'bool');
        $plugin->updateSetting($contextId, 'familyNameRequired', $this->getData('familyNameRequired'), 'bool');
        $plugin->updateSetting($contextId, 'defaultCountry', $this->getData('defaultCountry'), 'bool');
        $plugin->updateSetting($contextId, 'authorUserGroupOnly', $this->getData('authorUserGroupOnly'), 'bool');
        $plugin->updateSetting($contextId, 'disableBio', $this->getData('disableBio'), 'bool');
        $plugin->updateSetting($contextId, 'disableUrl', $this->getData('disableUrl'), 'bool');
        $plugin->updateSetting($contextId, 'disablePreferredPublicName', $this->getData('disablePreferredPublicName'), 'bool');
        
        return parent::execute(...$functionArgs);
    }
}

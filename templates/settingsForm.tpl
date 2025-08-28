{**
 * plugins/generic/authorRequirements/templates/settingsForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Author requirements plugin settings
 *
 *}
<div id="authorRequirementsSettings">
<div id="description">{translate key="plugins.generic.authorRequirements.description"}</div>
<h3>{translate key="plugins.generic.authorRequirements.settings.title"}</h3>

<script>
    $(function() {ldelim}
        // Attach the form handler.
        $('#authorRequirementsSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
    {rdelim});
</script>

<form class="pkp_form" id="authorRequirementsSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
{csrf}

{fbvFormArea id="authorRequirementsSettingsForm"}
    {fbvFormSection list=true description="plugins.generic.authorRequirements.settings.description"}
        {fbvElement type="checkbox" id="emailOptional" value="1" checked=$emailOptional label="plugins.generic.authorRequirements.settings.emailOptional"}
        {fbvElement type="checkbox" id="familyNameRequired" value="1" checked=$familyNameRequired label="plugins.generic.authorRequirements.settings.familyNameRequired"}
        {fbvElement type="checkbox" id="defaultCountry" value="1" checked=$defaultCountry label="plugins.generic.authorRequirements.settings.defaultCountry"}
        {fbvElement type="checkbox" id="authorUserGroupOnly" value="1" checked=$authorUserGroupOnly label="plugins.generic.authorRequirements.settings.authorUserGroupOnly"}
        {fbvElement type="checkbox" id="disableBio" value="1" checked=$disableBio label="plugins.generic.authorRequirements.settings.disableBio"}
        {fbvElement type="checkbox" id="disableUrl" value="1" checked=$disableUrl label="plugins.generic.authorRequirements.settings.disableUrl"}
        {fbvElement type="checkbox" id="disablePreferredPublicName" value="1" checked=$disablePreferredPublicName label="plugins.generic.authorRequirements.settings.disablePreferredPublicName"}
    {/fbvFormSection}
{/fbvFormArea}

{fbvFormButtons id="authorRequirementsSettingsFormSubmit" submitText="common.save"}
</form>
</div>

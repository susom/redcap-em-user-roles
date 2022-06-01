# User Roles
This External Module will use a REDCap project as a template for User Roles that can be propagated to other
REDCap projects which do not have user roles created.  At Stanford, there are four default user roles created:
<b>Project Admins</b>, <b>Export w/o Identifiers</b>, <b>Data Entry</b>, <b>Read Only</b>

The REDCap template project id is a required system setting. Once this project id is selected, each time a user enteres
the User Rights page with no roles created, the EM will create the same roles as the templated project. If there is
already at least one role created, the templated roles will <b>not</b> be created.

# API Script user
This EM will also give SuperUsers the option to create an API Script user with an API Token on projects.  This script user
can be used by projects who use the API endpoint by software developed by the REDCap group so it is not tied to a particular
developer so the capability will not break if the developer is no longer active.

## Setup
Besides adding the template project ID to the system settings, an entry in the <b>redap_config</b> table is required.  To insert
this setting into the config table, use the following database insert statement:

<code>
INSERT INTO redcap_config <br>
    ( field_name, value ) <br>
    VALUES <br>
    ( 'user_rights_template_pid', <i>$templated_pid </i>) <br>
    ON DUPLICATE KEY UPDATE  value = <i>$templated_pid</i>;
</code>

Substitute your template project ID for <i>$templated_pid</i>.
<br>





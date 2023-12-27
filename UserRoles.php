<?php
namespace Stanford\UserRoles;

require_once "emLoggerTrait.php";

use REDCap;
use UserRights;
Test
class UserRoles extends \ExternalModules\AbstractExternalModule {

    use emLoggerTrait;

    public function __construct() {
		parent::__construct();
		// Other code to run when object is instantiated
	}


    function redcap_user_rights($project_id)
    {
        $msgAlign 	= "center";
        $msgId 		= "actionMsg";

        // Find the UserRole template project where the roles are stored
        $template_project_id = $this->getSystemSetting('template-project');
        if (empty($template_project_id)) {
            $this->emError("The template pid is empty.  User Roles cannot be created until a template project is created.");
            return;
        }

        // WARN THAT USERS ARE NOT ASSIGNED TO ROLES IF MORE THAN 3 USERS UNASSIGNED
        $project_users = REDCap::getUserRights();
        $assigned_users = array_filter($project_users, function($usr){
            return $usr["role_id"];
        });

        // Changing timeVisible from 2000 seconds to 10 seconds
        if(count($project_users) - count($assigned_users) >= 3){
            displayMsg("We recommend that users be assigned to roles. It is good security practice<br> to limit access of essential functionality to the users' defined role."
                , $msgId, $msgAlign, "yellow", "exclamation.png", 10);
        }

        // Get User Roles from this project.
        //  If any roles are already created, we won't create the templated roles.
        $roles = UserRights::getRoles($project_id);
        if(count($roles) == 0 && $template_project_id){
            $this->applyTemplateRoles($template_project_id, $project_id);
            $url = $_SERVER['REQUEST_URI']. "&rolesApplied=1";
            echo "<script type=\"text/javascript\">window.location.href=\"$url\";</script>";
        }

        if(isset($_REQUEST["rolesApplied"])){
            displayMsg("Template roles have been added to your project."
                ,$msgId, $msgAlign, "green", "tick.png", 10);
        }

        // Add the API Script user with create an API token
        if(isset($_REQUEST["add_api_user"]) && $this->getUser()->isSuperUser()){
            $this->addApiScript($project_id);
            return;
        }

        if(isset($_REQUEST["api_script_added"]) &&  $this->getUser()->isSuperUser()){
            displayMsg("API Script User added."
                ,$msgId, $msgAlign, "green", "tick.png", 10);
        }

        ?>
        <script>
            $(document).ready(function(){
                fixUserRights();
                callOutAddRoles();

                // Create observer that maintains the sync going forward
                var observer = new MutationObserver(function(mutations) {
                    fixUserRights();
                    callOutAddRoles();
                });

                // Attach observer to target
                var target = $("#user_rights_roles_table_parent")[0];
                observer.observe(target,{
                    childList:true
                });

                $(document).on('click', function(event) {
                    if (!$(event.target).closest('#<?php echo $msgId?>').length) {
                        $("#<?php echo $msgId ?>").fadeOut("slow" , function(){
                            $(this).remove();
                        });
                    }
                });

            });


            //UNHIDE ASSIGN TO ROLE BUTTON FOR UNASSIGNED USERS
            function callOutAddRoles(){
                //CLONE THE HIDDEN TOOL TIP FOR ASSIGN TO ROLE, ADD IT TO THE TABLE WHERE APPROPRIATE
                $("#table-user_rights_roles_table tr").each(function(){
                    var row = $(this);
                    if( row.find("td:eq(0):contains('—')").length ){
                        var username 	= row.find("td:eq(1) b").text();
                        var tooltip 	= $("#tooltipBtnAssignRole").clone();
                        tooltip.prop({id:"", class:"assignRole"});
                        tooltip.click(function(){
                            var thisoffset = $(this).offset();
                            $('#userClickTooltip').show().css("top","-5000px").css("left","-5000px");
                            $("#assignUserDropdownDiv").hide();
                            $("#tooltipHiddenUsername").val(username);
                            $("#assignUserDropdownDiv").show().css("top", thisoffset.top + 30 ).css("left",thisoffset.left);
                            return false;
                        });
                        $(this).find("td:eq(0)").empty().append(tooltip);
                    }
                });

                return;
            }

            //OVERIDE NEW USER ADD TABLE TO HIDE UNDESIRABLE "CUSTOM RIGHTS" FIELD
            function fixUserRights(){
                $("#addUsersRolesDiv div:contains('— OR —')").remove();
                var newuserclone = $("#new_username").closest("div").detach();

                var tempbox = $("<details><summary>Add user with custom rights</summary></details>");
                tempbox.append(newuserclone);
                $("#addUsersRolesDiv").append(tempbox);

                <?php
                if ( $this->getUser()->isSuperUser()  && !array_key_exists("api_script", $project_users) ) {
                ?>
                    var newbutton = $("<a href='<?php echo $_SERVER['REQUEST_URI'] . '&api_script_added=1' ?>' class='button apiadd'>Add api_script user to this project</a>");
                    $("#addUsersRolesDiv").append(newbutton);
                    newbutton.click(function(){
                        var redirect = $(this).attr("href");
                        $.get("<?php echo $_SERVER['REQUEST_URI'] . '&add_api_user=1' ?>",function(data){
                            location.href = redirect;
                        });
                        return false;
                    })
                <?php
                }
                ?>
            }

        </script>
        <style>
            /* OVER WRITE THE DEFAULT INLINE DISPLAY MESSAGE TO COME DOWN FROM THE TOP */
            #<?php echo $msgId ?> {
                max-width:initial !important;
                margin:0 auto !important;
                padding:22px 25px 25px !important;
                text-align:<?php echo $msgAlign ?>;

                position:fixed;
                width:80%;
                top:-1px;
                right:0;
                left:0;
                border-radius:0 0 8px 8px;
                display:none;
                z-index:99;
            }
            #addUsersRolesDiv{ padding:20px 10px !important; }
            #addUsersRolesDiv div:nth-child(2){
                margin-top:8px !important;
            }
            #addUsersRolesDiv details {
                margin-top:15px;
                font-weight:Bold;
                vertical-align: middle;
            }
            #addUsersRolesDiv .button {
                border:1px solid #333;
                border-radius:8px;
                padding:8px 12px;
                background:#eee;
                box-shadow:1px 1px 1px #333;
                color:#666;
                margin-top:-25px;
                display:inline-block;
                float:right;
            }
            #addUsersRolesDiv .button:hover {
                text-decoration:none;
                color:#000;
                border:1px solid #999;
                box-shadow:1px 1px 3px #999;
            }
        </style>
        <?php
    }

    private function applyTemplateRoles($source, $destination){
        $template_project_id 	= $source;
        $project_id 			= $destination;

        $this->query("CREATE TEMPORARY TABLE template_roles AS SELECT * FROM redcap_user_roles WHERE project_id = ?", [$template_project_id]);
        $this->query("UPDATE template_roles SET project_id = ?", [$project_id]);
        $this->query("ALTER TABLE template_roles CHANGE COLUMN role_id role_id INT(10) NULL;", []);
        $this->query("UPDATE template_roles SET role_id = NULL", []);
        $this->query("INSERT INTO redcap_user_roles SELECT * FROM template_roles;", []);
        $this->query("DROP TEMPORARY TABLE template_roles", []);

        return;
    }

    private function addApiScript($project_id){
        $username 	= 'api_script';
        $tok 		= substr( strtoupper(hash('sha256', "$username&" . microtime()) ), 0, 32 );
        $record = $this->query("INSERT INTO
					redcap_user_rights (project_id, username, data_export_tool, data_import_tool, api_export, api_import, record_create, api_token)
		 			VALUES
		 			(?, ?, 1,1,1,1,1,?)", [$project_id, $username, $tok]);

        REDCap::logEvent("Added api_script user", "$username created api_script user with api token", $record);
        return;
    }

}

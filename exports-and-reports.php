<?php
/*
Plugin Name: Exports and Reports
Plugin URI: http://scottkclark.com/wordpress/exports-and-reports/
Description: Define custom exports / reports for users by creating each export / report and defining the fields as well as custom MySQL queries to run.
Version: 0.3.2
Author: Scott Kingsley Clark
Author URI: http://scottkclark.com/
*/

global $wpdb;
define('EXPORTS_REPORTS_TBL',$wpdb->prefix.'exportsreports_');
define('EXPORTS_REPORTS_VERSION','032');
define('EXPORTS_REPORTS_URL',plugin_dir_url(__FILE__));
define('EXPORTS_REPORTS_DIR',plugin_dir_path(__FILE__));
define('EXPORTS_REPORTS_EXPORT_DIR',WP_CONTENT_DIR.'/exports');

add_action('admin_init','exports_reports_init');
add_action('admin_menu','exports_reports_menu');

function exports_reports_reset ()
{
    global $wpdb;
    // thx pods ;)
    $sql = file_get_contents(EXPORTS_REPORTS_DIR.'assets/dump.sql');
    $sql_explode = preg_split("/;\n/", str_replace('wp_', $wpdb->prefix, $sql));
    if(count($sql_explode)==1)
        $sql_explode = preg_split("/;\r/", str_replace('wp_', $wpdb->prefix, $sql));
    for ($i = 0, $z = count($sql_explode); $i < $z; $i++)
    {
        $wpdb->query($sql_explode[$i]);
    }
    delete_option('exports_reports_version');
    add_option('exports_reports_version',EXPORTS_REPORTS_VERSION);
    exports_reports_schedule_cleanup();
}
function exports_reports_init ()
{
    global $current_user,$wpdb;
    $capabilities = exports_reports_capabilities();
    // check version
    $version = get_option('exports_reports_version');
    if(empty($version))
    {
        exports_reports_reset();
    }
    elseif($version!=EXPORTS_REPORTS_VERSION)
    {
        $version = intval($version);
        if($version<32)
        {
            $wpdb->query("ALTER TABLE ".EXPORTS_REPORTS_TBL."groups ADD COLUMN `role_access` MEDIUMTEXT NOT NULL AFTER `disabled`");
            $wpdb->query("ALTER TABLE ".EXPORTS_REPORTS_TBL."reports ADD COLUMN `disabled` int(1) NOT NULL AFTER `group`");
            $wpdb->query("ALTER TABLE ".EXPORTS_REPORTS_TBL."reports ADD COLUMN `role_access` MEDIUMTEXT NOT NULL AFTER `disable_export`");
            $wpdb->query("ALTER TABLE ".EXPORTS_REPORTS_TBL."reports ADD COLUMN `weight` int(10) NOT NULL AFTER `role_access`");
            exports_reports_schedule_cleanup();
        }
        delete_option('exports_reports_version');
        add_option('exports_reports_version',EXPORTS_REPORTS_VERSION);
    }
    // thx gravity forms, great way of integration with members!
    if ( function_exists( 'members_get_capabilities' ) ){
        add_filter('members_get_capabilities', 'exports_reports_get_capabilities');
        if(current_user_can("exports_reports_full_access"))
            $current_user->remove_cap("exports_reports_full_access");
        $is_admin_with_no_permissions = current_user_can("administrator") && !exports_reports_current_user_can_any(exports_reports_capabilities());
        if($is_admin_with_no_permissions)
        {
            $role = get_role("administrator");
            foreach($capabilities as $cap)
            {
                $role->add_cap($cap);
            }
        }
    }
    else
    {
        $exports_reports_full_access = current_user_can("administrator") ? "exports_reports_full_access" : "";
        $exports_reports_full_access = apply_filters("exports_reports_full_access", $exports_reports_full_access);
        if(!empty($exports_reports_full_access))
            $current_user->add_cap($exports_reports_full_access);
    }
}
function exports_reports_menu ()
{
    global $wpdb;
    $has_full_access = current_user_can('exports_reports_full_access');
    if(!$has_full_access&&current_user_can('administrator'))
        $has_full_access = true;
    $min_cap = exports_reports_current_user_can_which(exports_reports_capabilities());
    if(empty($min_cap))
        $min_cap = 'exports_reports_full_access';
    add_menu_page('Reports', 'Reports', $has_full_access ? 'read' : $min_cap, 'exports-reports', null, EXPORTS_REPORTS_URL.'assets/icons/16.png');
    add_submenu_page('exports-reports', 'Manage Groups', 'Manage Groups', $has_full_access ? 'read' : 'exports_reports_settings', 'exports-reports', 'exports_reports_groups');
    add_submenu_page('exports-reports', 'Manage Reports', 'Manage Reports', $has_full_access ? 'read' : 'exports_reports_settings', 'exports-reports-reports', 'exports_reports_reports');
    $groups = $wpdb->get_results('SELECT id,name FROM '.EXPORTS_REPORTS_TBL.'groups WHERE disabled=0');
    if(!empty($groups))
    {
        foreach($groups as $group)
        {
            $reports = $wpdb->get_results('SELECT id,role_access FROM '.EXPORTS_REPORTS_TBL.'reports WHERE `group`='.$group->id.' ORDER BY weight,name LIMIT 1');
            if(@count($reports)>0)
            {
                foreach($reports as $report)
                {
                    if($has_full_access || current_user_can('exports_reports_view') || current_user_can('exports_reports_view_group_'.$group->id) || current_user_can('exports_reports_view_report_'.$report->id))
                    {
                        add_submenu_page('exports-reports', $group->name, $group->name, $has_full_access ? 'read' : 'exports_reports_view', 'exports-reports-group-'.$group->id, 'exports_reports_view');
                        break;
                    }
                    $roles = explode(',',$report->role_access);
                    if(empty($roles))
                    {
                        continue;
                    }
                    foreach($roles as $role)
                    {
                        if(current_user_can($role))
                        {
                            add_submenu_page('exports-reports', $group->name, $group->name, $has_full_access ? 'read' : 'exports_reports_view', 'exports-reports-group-'.$group->id, 'exports_reports_view');
                            break;
                        }
                    }
                }
            }
        }
    }
    add_submenu_page('exports-reports', 'Settings', 'Settings', $has_full_access ? 'read' : 'exports_reports_settings', 'exports-reports-settings', 'exports_reports_settings');
    add_submenu_page('exports-reports', 'About', 'About', $has_full_access ? 'read' : $min_cap, 'exports-reports-about', 'exports_reports_about');
}
function exports_reports_get_capabilities ($caps)
{
    return array_merge($caps,exports_reports_capabilities());
}
function exports_reports_capabilities ()
{
    return array('exports_reports_full_access','exports_reports_settings','exports_reports_view');
}
function exports_reports_current_user_can_any ($caps)
{
    if(!is_array($caps))
        return current_user_can($caps) || current_user_can("exports_reports_full_access");
    foreach($caps as $cap)
    {
        if(current_user_can($cap))
            return true;
    }
    return current_user_can("exports_reports_full_access");
}
function exports_reports_current_user_can_which ($caps)
{
    foreach($caps as $cap)
    {
        if(current_user_can($cap))
            return $cap;
    }
    return "";
}

function exports_reports_settings ()
{
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo EXPORTS_REPORTS_URL; ?>assets/icons/32.png);"><br /></div>
    <h2>Exports and Reports - Settings</h2>
<?php
if(isset($_POST['reset']))
{
    exports_reports_cleanup(true);
?>
	<div id="message" class="updated fade"><p>Your Exports directory has been cleaned up and all export files have been removed.</p></div>
<?php
    exports_reports_reset();
?>
	<div id="message" class="updated fade"><p>Your Settings have been reset.</p></div>
<?php
}
if(isset($_POST['clear']))
{
    exports_reports_cleanup(true);
?>
	<div id="message" class="updated fade"><p>Your Exports directory has been cleaned up and all export files have been removed.</p></div>
<?php
}
?>
    <div style="height:20px;"></div>
    <link  type="text/css" rel="stylesheet" href="<?php echo EXPORTS_REPORTS_URL; ?>assets/admin.css" />
    <form method="post" action="">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="clear">Clear Exports Directory</label></th>
                <td>
                    <input name="clear" type="submit" id="clear" value=" Clear Now " />
                    <span class="description">This will remove all files from your Exports directory - <?php echo WP_CONTENT_URL; ?>/exports/</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="reset">Reset Settings</label></th>
                <td>
                    <input name="reset" type="submit" id="reset" value=" Reset Now " />
                    <span class="description">This will clear all groups / reports and remove all files from your Exports directory too - <?php echo WP_CONTENT_URL; ?>/exports/</span>
                </td>
            </tr><!--
            <tr valign="top">
                <th scope="row"><label for=""></label></th>
                <td>
                    <input name="" type="text" id="" value="0" class="small-text" />
                    <span class="description"></span>
                </td>
            </tr>-->
        </table><!--
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="  Save Changes  " />
        </p>-->
    </form>
</div>
<?php
}
function exports_reports_groups ()
{
    global $wpdb;
    require_once EXPORTS_REPORTS_DIR.'wp-admin-ui/Admin.class.php';
    $columns = array('name','disabled'=>array('label'=>'Disabled','type'=>'bool'),'created'=>array('label'=>'Date Created','type'=>'datetime'),'updated'=>array('label'=>'Last Modified','type'=>'datetime'));
    $form_columns = $columns;
    $roles = array();
    $user_roles = get_option($wpdb->prefix.'user_roles');
    foreach($user_roles as $user_role=>$role_data)
    {
        $roles[$user_role] = $role_data['name'];
    }
    $form_columns['role_access'] = array('label'=>'WP Roles with Access','comments'=>'Add the exports_reports_full_access capability to a role for full access to reports, exports_reports_settings for only access to settings, exports_reports_view for access to view all reports, exports_reports_view_group_{ID} for access to view a group and all of the reports within, or exports_reports_view_report_{ID} for access to view a single report','type'=>'related','related'=>$roles,'related_multiple'=>true);
    $form_columns['created']['date_touch_on_create'] = true;
    $form_columns['created']['display'] = false;
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $admin = new WP_Admin_UI(array('css'=>EXPORTS_REPORTS_URL.'assets/admin.css','item'=>'Group','items'=>'Groups','table'=>EXPORTS_REPORTS_TBL.'groups','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>EXPORTS_REPORTS_URL.'assets/icons/32.png','duplicate'=>true));
    $admin->go();
}
function exports_reports_reports ()
{
    global $wpdb;
    require_once EXPORTS_REPORTS_DIR.'wp-admin-ui/Admin.class.php';
    $columns = array('name','group'=>array('label'=>'Group','type'=>'related','related'=>EXPORTS_REPORTS_TBL.'groups'),'disabled'=>array('label'=>'Disabled','type'=>'bool'),'created'=>array('label'=>'Date Created','type'=>'datetime'),'updated'=>array('label'=>'Last Modified','type'=>'datetime'));
    $columns['created']['filter'] = true;
    $columns['created']['filter_label'] = 'Lifespan';
    $columns['created']['date_ongoing'] = 'updated';
    $form_columns = $columns;
    $form_columns['disabled']['label'] = 'Disabled?';
    $form_columns['disable_export'] = array('label'=>'Disable Export?','type'=>'bool');
    $form_columns['created']['date_touch_on_create'] = true;
    $form_columns['created']['display'] = false;
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $roles = array();
    $user_roles = get_option($wpdb->prefix.'user_roles');
    foreach($user_roles as $user_role=>$role_data)
    {
        $roles[$user_role] = $role_data['name'];
    }
    $form_columns['role_access'] = array('label'=>'WP Roles with Access','type'=>'related','related'=>$roles,'related_multiple'=>true);
    $form_columns['sql_query'] = array('label'=>'SQL Query','type'=>'desc');
    $form_columns['field_data'] = array('label'=>'Fields (optional)','custom_input'=>'exports_reports_report_field','custom_save'=>'exports_reports_report_field_save');
    $admin = new WP_Admin_UI(array('reorder'=>'weight','order'=>'weight','order_dir'=>'ASC','css'=>EXPORTS_REPORTS_URL.'assets/admin.css','item'=>'Report','items'=>'Reports','table'=>EXPORTS_REPORTS_TBL.'reports','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>EXPORTS_REPORTS_URL.'assets/icons/32.png','duplicate'=>true));
    $admin->go();
}
function exports_reports_report_field ($column,$attributes,$obj)
{
    $field_data = @json_decode($obj->row[$column],true);
?>
<style type="text/css">
    .field_data { overflow:visible; }
    .field_data .sortable td div { width:160px; }
    .field_data .sortable td div.dragme {background:url(<?php echo EXPORTS_REPORTS_URL; ?>assets/icons/move.png)!important; width:16px; height:16px; margin-right:8px; cursor:pointer; margin:auto auto; }
</style>
<div class="field_data">
    <p><input type="button" class="button" value=" Add Field " onclick="field_add_row();" /></p>
    <table class="widefat">
        <tbody class="sortable">
<?php
    if(is_array($field_data)&&!empty($field_data))
    {
        $count = 0;
        foreach($field_data as $field)
        {
?>
            <tr>
                <td><div class="dragme"></div></td>
                <td><div>Field Name</div> <input type="text" name="field_name[<?php echo $count; ?>]" value="<?php echo $field['name']; ?>" class="medium-text" /><br /><br />
                    <div>Real Field (optional if using Alias)</div> <input type="text" name="field_real_name[<?php echo $count; ?>]" value="<?php echo $field['real_name']; ?>" class="medium-text" /><br /><br />
                    <div>Label (optional)</div> <input type="text" name="field_label[<?php echo $count; ?>]" value="<?php echo $field['label']; ?>" class="medium-text" /><br /><br />
                    <div>Filter Label (optional)</div> <input type="text" name="field_filter_label[<?php echo $count; ?>]" value="<?php echo $field['filter_label']; ?>" class="medium-text" /></td>
                <td><div>Data Type</div><select name="field_type[<?php echo $count; ?>]"><option value="text"<?php echo ($field['type']=='text'?' SELECTED':''); ?>>Text</option><option value="bool"<?php echo ($field['type']=='bool'?' SELECTED':''); ?>>Boolean (Checkbox)</option><option value="date"<?php echo ($field['type']=='date'?' SELECTED':''); ?>>Date</option><option value="date"<?php echo ($field['type']=='time'?' SELECTED':''); ?>>Time</option><option value="datetime"<?php echo ($field['type']=='datetime'?' SELECTED':''); ?>>Date + Time</option></select><br /><br />
                    <div>Hide from Report</div> Yes <input type="radio" name="field_hide_report[<?php echo $count; ?>]" value="1" class="medium-text"<?php echo ($field['hide_report']==1?' CHECKED':''); ?> />&nbsp;&nbsp; No<input type="radio" name="field_hide_report[<?php echo $count; ?>]" value="0" class="medium-text"<?php echo ($field['hide_report']!=1?' CHECKED':''); ?> /><br /><br />
                    <div>Hide from Export</div> Yes <input type="radio" name="field_hide_export[<?php echo $count; ?>]" value="1" class="medium-text"<?php echo ($field['hide_export']==1?' CHECKED':''); ?> />&nbsp;&nbsp; No<input type="radio" name="field_hide_export[<?php echo $count; ?>]" value="0" class="medium-text"<?php echo ($field['hide_export']!=1?' CHECKED':''); ?> /><br /><br />
                    <div>Filter using HAVING</div> Yes <input type="radio" name="field_group_related[<?php echo $count; ?>]" value="1" class="medium-text"<?php echo ($field['group_related']==1?' CHECKED':''); ?> />&nbsp;&nbsp; No<input type="radio" name="field_group_related[<?php echo $count; ?>]" value="0" class="medium-text"<?php echo ($field['group_related']!=1?' CHECKED':''); ?> /></td>
                <td>
                    <div>Display Function (optional)</div> <input type="text" name="field_custom_display[<?php echo $count; ?>]" value="<?php echo $field['custom_display']; ?>" class="medium-text" /><br /><br />
                    <div>Searchable</div> Yes <input type="radio" name="field_search[<?php echo $count; ?>]" value="0" class="medium-text"<?php echo ($field['search']!=1?' CHECKED':''); ?> />&nbsp;&nbsp; No<input type="radio" name="field_search[<?php echo $count; ?>]" value="1" class="medium-text"<?php echo ($field['search']==1?' CHECKED':''); ?> /><br /><br />
                    <div>Filterable (optional)</div> Yes <input type="radio" name="field_filter[<?php echo $count; ?>]" value="1" class="medium-text"<?php echo ($field['filter']==1?' CHECKED':''); ?> />&nbsp;&nbsp; No<input type="radio" name="field_filter[<?php echo $count; ?>]" value="0" class="medium-text"<?php echo ($field['filter']!=1?' CHECKED':''); ?> /><br /><br />
                    <div>Ongoing Date Field (optional)</div> <input type="text" name="field_filter_ongoing[<?php echo $count; ?>]" value="<?php echo $field['filter_ongoing']; ?>" class="medium-text" /></td>
                <td>[<a href="#" onclick="return field_remove_row(this);">remove</a>]</td>
            </tr>
<?php
            $count++;
        }
    }
    else
    {
?>
            <tr>
                <td><div class="dragme"></div></td>
                <td><div>Field Name</div> <input type="text" name="field_name[0]" value="" class="medium-text" /><br /><br />
                    <div>Real Field (optional if using Alias)</div> <input type="text" name="field_real_name[0]" value="" class="medium-text" /><br /><br />
                    <div>Label (optional)</div> <input type="text" name="field_label[0]" value="" class="medium-text" /><br /><br />
                    <div>Filter Label (optional)</div> <input type="text" name="field_filter_label[0]" value="" class="medium-text" /></td>
                <td><div>Data Type</div><select name="field_type[0]"><option value="text">Text</option><option value="bool">Boolean (Checkbox)</option><option value="date">Date</option><option value="time">Time</option><option value="datetime">Date + Time</option></select><br /><br />
                    <div>Hide from Report</div> Yes <input type="radio" name="field_hide_report0]" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_report[0]" value="0" class="medium-text" CHECKED /><br /><br />
                    <div>Hide from Export</div> Yes <input type="radio" name="field_hide_export[0]" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_export[0]" value="0" class="medium-text" CHECKED /><br /><br />
                    <div>Filter using HAVING</div> Yes <input type="radio" name="field_group_related[0]" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_group_related[0]" value="0" class="medium-text" CHECKED /></td>
                <td><div>Display Function (optional)</div> <input type="text" name="field_custom_display[0]" value="" class="medium-text" /><br /><br />
                    <div>Searchable</div> Yes <input type="radio" name="field_search[0]" value="0" class="medium-text" CHECKED />&nbsp;&nbsp; No<input type="radio" name="field_search[0]" value="1" class="medium-text" /><br /><br />
                    <div>Filterable (optional)</div> Yes <input type="radio" name="field_filter[0]" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_filter[0]" value="0" class="medium-text" CHECKED /><br /><br />
                    <div>Ongoing Date Field (optional)</div> <input type="text" name="field_filter_ongoing[0]" value="" class="medium-text" /></td>
                <td>[<a href="#" onclick="return field_remove_row(this);">remove</a>]</td>
            </tr>
<?php
    }
?>
        </tbody>
    </table>
    <p><input type="button" class="button" value=" Add Field " onclick="field_add_row();" /></p>
</div>
<input type="hidden" name="<?php echo $column; ?>" value="" />
<script type="text/javascript">
    function field_remove_row (it)
    {
        var conf = confirm('Are you sure you want to delete it?');
        if(conf)
            jQuery(it).parent().parent().remove();
        return false;
    }
    function field_add_row ()
    {
        var field_count = jQuery('.field_data tbody.sortable tr').length+1;
        var row = '<tr><td><div class="dragme"></div></td><td><div>Field Name</div> <input type="text" name="field_name['+field_count+']" value="" class="medium-text" /><br /><br /><div>Real Field (optional if using Alias)</div> <input type="text" name="field_real_name['+field_count+']" value="" class="medium-text" /><br /><br /><div>Label (optional)</div> <input type="text" name="field_label['+field_count+']" value="" class="medium-text" /><br /><br /><div>Filter Label (optional)</div> <input type="text" name="field_filter_label['+field_count+']" value="" class="medium-text" /></td><td><div>Data Type</div><select name="field_type['+field_count+']"><option value="text">Text</option><option value="bool">Boolean (Checkbox)</option><option value="date">Date</option><option value="time">Time</option><option value="datetime">Date + Time</option></select><br /><br /><div>Hide from Report</div> Yes <input type="radio" name="field_hide_report['+field_count+']" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_report['+field_count+']" value="0" class="medium-text" CHECKED /><br /><br /><div>Hide from Export</div> Yes <input type="radio" name="field_hide_export['+field_count+']" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_export['+field_count+']" value="0" class="medium-text" CHECKED /><br /><br /><div>Filter using HAVING</div> Yes <input type="radio" name="field_group_related['+field_count+']" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_group_related['+field_count+']" value="0" class="medium-text" CHECKED /></td><td><div>Display Function (optional)</div> <input type="text" name="field_custom_display['+field_count+']" value="" class="medium-text" /><br /><br /><div>Searchable</div> Yes <input type="radio" name="field_search['+field_count+']" value="0" class="medium-text" CHECKED />&nbsp;&nbsp; No<input type="radio" name="field_search['+field_count+']" value="1" class="medium-text" /><br /><br /><div>Filterable (optional)</div> Yes <input type="radio" name="field_filter['+field_count+']" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_filter['+field_count+']" value="0" class="medium-text" CHECKED /><br /><br /><div>Ongoing Date Field (optional)</div> <input type="text" name="field_filter_ongoing['+field_count+']" value="" class="medium-text" /></td><td>[<a href="#" onclick="return field_remove_row(this);">remove</a>]</td></tr>';
        jQuery('.field_data table').append(row);
    }
    jQuery('table.widefat tbody tr:even').addClass('alternate');
    jQuery(function(){
        jQuery(".sortable").sortable({axis: "y", handle: ".dragme", forcePlaceholderSize: true, forceHelperSize: true, placeholder: 'ui-state-highlight'});
    });
</script>
<?php
}
function exports_reports_report_field_save ($value,$column,$attributes,$obj)
{
    $value = array();
    if(isset($_POST['field_name']))
    {
        foreach($_POST['field_name'] as $key=>$field)
        {
            if(empty($field))
                continue;
            $value[] = array('name'=>$field,'real_name'=>$_POST['field_real_name'][$key],'label'=>$_POST['field_label'][$key],'filter_label'=>$_POST['field_filter_label'][$key],'hide_report'=>$_POST['field_hide_report'][$key],'hide_export'=>$_POST['field_hide_export'][$key],'group_related'=>$_POST['field_group_related'][$key],'custom_display'=>$_POST['field_custom_display'][$key],'type'=>$_POST['field_type'][$key],'search'=>$_POST['field_search'][$key],'filter'=>$_POST['field_filter'][$key],'filter_ongoing'=>$_POST['field_filter_ongoing'][$key]);
        }
    }
    return json_encode($value);
}
function exports_reports_view ()
{
    global $wpdb;
    $group_id = str_replace('exports-reports-group-','',$_GET['page']);
    $group = $wpdb->get_results('SELECT id,name,role_access FROM '.EXPORTS_REPORTS_TBL.'groups WHERE disabled=0 AND id='.$group_id);
    if(empty($group))
        return false;
    $reports = $wpdb->get_results('SELECT * FROM '.EXPORTS_REPORTS_TBL.'reports WHERE `group`='.$group_id.' ORDER BY weight,name');
    if(empty($reports))
        return false;
    $has_full_access = current_user_can('exports_reports_full_access');
        $has_full_access = true;
    if(!$has_full_access&&current_user_can('administrator'))
        $has_full_access = true;
    $selectable_reports = array();
    $current_report = false;
    foreach($reports as $report)
    {
        if($has_full_access || current_user_can('exports_reports_view') || current_user_can('exports_reports_view_group_'.$group_id) || current_user_can('exports_reports_view_report_'.$report->id))
        {
            if(false===$current_report)
                $current_report = $report->id;
            $selectable_reports[$report->id] = array('name'=>$report->name,'sql_query'=>$report->sql_query,'export'=>($report->disable_export==0?true:false),'field_data'=>$report->field_data);
            continue;
        }
        $roles = explode(',',$report->role_access);
        if(empty($roles))
        {
            continue;
        }
        foreach($roles as $role)
        {
            if(current_user_can($role))
            {
                if(false===$current_report)
                    $current_report = $report->id;
                $selectable_reports[$report->id] = array('name'=>$report->name,'sql_query'=>$report->sql_query,'export'=>($report->disable_export==0?true:false),'field_data'=>$report->field_data);
            }
        }
    }
    if(empty($selectable_reports))
        return false;
    if(isset($_GET['report'])&&isset($selectable_reports[$_GET['report']]))
        $current_report = $_GET['report'];
    require_once EXPORTS_REPORTS_DIR.'wp-admin-ui/Admin.class.php';
    $options = array('css'=>EXPORTS_REPORTS_URL.'assets/admin.css','readonly'=>true,'export'=>$selectable_reports[$current_report]['export'],'search'=>(strlen($selectable_reports[$current_report]['field_data'])>0?true:false),'sql'=>$selectable_reports[$current_report]['sql_query'],'item'=>$selectable_reports[$current_report]['name'],'items'=>$selectable_reports[$current_report]['name'],'icon'=>EXPORTS_REPORTS_URL.'assets/icons/32.png','heading'=>array('manage'=>'View Report:'));
    $field_data = @json_decode($selectable_reports[$current_report]['field_data'],true);
    if(is_array($field_data)&&!empty($field_data))
    {
        $options['columns'] = array();
        foreach($field_data as $field)
        {
            $options['columns'][$field['name']] = array();
            if(0<strlen($field['real_name']))
                $options['columns'][$field['name']]['real_name'] = $field['real_name'];
            if(0<strlen($field['label']))
                $options['columns'][$field['name']]['label'] = $field['label'];
            if(0<strlen($field['filter_label']))
                $options['columns'][$field['name']]['filter_label'] = $field['filter_label'];
            if(0<strlen($field['custom_display']))
                $options['columns'][$field['name']]['custom_display'] = $field['custom_display'];
            if(0<strlen($field['type']))
                $options['columns'][$field['name']]['type'] = $field['type'];
            if(1==$field['hide_report'])
                $options['columns'][$field['name']]['display'] = false;
            if(1==$field['hide_export'])
                $options['columns'][$field['name']]['export'] = false;
            if(1==$field['disable_search']||1==$field['hide_report'])
                $options['columns'][$field['name']]['search'] = false;
            if(1==$field['filter'])
                $options['columns'][$field['name']]['filter'] = true;
            if(1==$field['filter']&&0<strlen($field['filter_ongoing']))
                $options['columns'][$field['name']]['date_ongoing'] = $field['filter_ongoing'];
            if(1==$field['group_related'])
                $options['columns'][$field['name']]['group_related'] = true;
        }
    }
    $options['report_id'] = $current_report;
    $admin = new WP_Admin_UI($options);
    $admin->order = false;
    if(count($selectable_reports)>1)
    {
?>
<div style="background-color:#E7E7E7;border:1px solid #D7D7D7; padding:5px 15px;margin:15px 15px 0px 5px;">
    <strong style="padding-right:10px;">Exports and Reports:</strong>
    <label for="report" style="vertical-align:baseline;">Choose Report</label>
    <select id="report" onchange="document.location=this.value;">
<?php
foreach($selectable_reports as $report_id=>$report)
{
?>
        <option value="<?php echo $admin->var_update(array('report'=>$report_id)); ?>"<?php echo ($current_report==$report_id?' SELECTED':''); ?>><?php echo $report['name']; ?></option>
<?php
}
?>
    </select>
</div>
<?php
    }
    $admin->go();
}
function exports_reports_about ()
{
?>
<div class="wrap">
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo EXPORTS_REPORTS_URL; ?>assets/icons/32.png);"><br /></div>
    <h2>About the Exports and Reports plugin</h2>
    <div style="height:20px;"></div>
    <link  type="text/css" rel="stylesheet" href="<?php echo EXPORTS_REPORTS_URL; ?>assets/admin.css" />
    <table class="form-table about">
        <tr valign="top">
            <th scope="row">About the Plugin Author</th>
            <td><a href="http://www.scottkclark.com/">Scott Kingsley Clark</a> from <a href="http://skcdev.com/">SKC Development</a>
                <span class="description">Scott specializes in WordPress and Pods CMS Framework development using PHP, MySQL, and AJAX. Scott is also a developer on the <a href="http://podscms.org/">Pods CMS Framework</a> plugin and has a creative outlet in music with his <a href="http://www.softcharisma.com/">Soft Charisma</a></span></td>
        </tr>
        <tr valign="top">
            <th scope="row">Official Support</th>
            <td><a href="http://www.scottkclark.com/forums/exports-and-reports/">Exports and Reports - Support Forums</a></td>
        </tr>
        <tr valign="top">
            <th scope="row">Features</th>
            <td>
                <ul>
                    <li><strong>Administration</strong>
                        <ul>
                            <li>Create and Manage Groups</li>
                            <li>Create and Manage Reports</li>
                            <li>Limit which User Roles have access to a Group or Report</li>
                            <li>Ability to clear entire export directory (based on logged export files)</li>
                            <li>Daily Export Cleanup via wp_cron</li>
                            <li>Admin.class.php - A class for plugins to manage data using the WordPress UI appearance</li>
                        </ul>
                    </li>
                    <li><strong>Reporting</strong>
                        <ul>
                            <li>Filter by Date</li>
                            <li>Automatic Pagination</li>
                            <li>Show only the fields you want to show</li>
                            <li>Pre-display modification through custom defined function per field or row</li>
                        </ul>
                    </li>
                    <li><strong>Exporting</strong>
                        <ul>
                            <li>CSV - Comma-separated Values (w/ Excel support)</li>
                            <li>TSV - Tab-separated Values (w/ Excel support)</li>
                            <li>XML - XML 1.0 UTF-8 data</li>
                            <li>JSON - JSON for use in Javascript and PHP5+</li>
                            <li>Custom - Custom delimiter separated Values</li>
                        </ul>
                    </li>
                </ul>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Upcoming Features - Roadmap</th>
            <td>
                <dl>
                    <dt>0.4</dt>
                    <dd>
                        <ul>
                            <li>Pods CMS Framework integration</li>
                        </ul>
                    </dd>
                </dl>
            </td>
        </tr>
    </table>
    <div style="height:50px;"></div>
</div>
<?php
}

add_action('wp_admin_ui_post_export','exports_reports_log',10,2);
add_action('wp_admin_ui_post_remove_export','exports_reports_delete_log',10,2);
function exports_reports_log ($args,$obj)
{
    global $wpdb;
    $filename = $args[1];
    return $wpdb->insert(EXPORTS_REPORTS_TBL."log",array('report_id'=>$obj[0]->report_id,'filename'=>$filename,'created'=>date_i18n('Y-m-d H:i:s')),array('%d','%s','%s'));
}
function exports_reports_delete_log ($args,$obj)
{
    global $wpdb;
    $filename = $args[1];
    if(false!==$args[2])
        return $wpdb->query($wpdb->prepare("DELETE FROM ".EXPORTS_REPORTS_TBL."log WHERE `report_id`=%d AND `filename`=%s",array($obj[0]->report_id,$filename)));
    else
        return false;
}
function exports_reports_schedule_cleanup ()
{
    $schedules = _get_cron_array();
    $timestamp = false;
	$key = md5(serialize(array()));
    foreach($schedules as $ts=>$schedule)
    {
        if(isset($schedule['exports_reports_cleanup'])&&isset($schedule['exports_reports_cleanup'][$key]))
        {
            $timestamp = $ts;
            break;
        }
    }
    if(false!==$timestamp)
        wp_unschedule_event($timestamp,'exports_reports_cleanup',array());
    $timestamp = time();
    $recurrence = 'daily';
    return wp_schedule_event($timestamp,$recurrence,'exports_reports_cleanup',array());
}
function exports_reports_cleanup ($total=false)
{
    global $wpdb;
    $purge_age = 1; // day(s) in age to purge
    $where = '';
    if(false===$total)
        $where = " WHERE `created` < DATE_ADD(NOW(), INTERVAL -{$purge_age} DAY)";
    $cleanup = $wpdb->get_results("SELECT * FROM ".EXPORTS_REPORTS_TBL."log".$where);
    if(false!==$cleanup&&!empty($cleanup))
    {
        foreach($cleanup as $export)
        {
            @unlink(EXPORTS_REPORTS_EXPORT_DIR.'/'.$export->filename);
            $wpdb->query($wpdb->prepare("DELETE FROM ".EXPORTS_REPORTS_TBL."log WHERE `id`=%d",array($export->id)));
        }
        return true;
    }
    return false;
}
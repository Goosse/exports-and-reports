<?php
/*
Plugin Name: Exports and Reports
Plugin URI: http://www.scottkclark.com/
Description: Define custom exports / reports for users by creating each export / report and defining the fields as well as custom MySQL queries to run.
Version: 0.2
Author: Scott Kingsley Clark
Author URI: http://www.scottkclark.com/
*/

global $wpdb;
define('EXPORTS_REPORTS_TBL',$wpdb->prefix.'exportsreports_');
define('EXPORTS_REPORTS_VERSION','020');
define('EXPORTS_REPORTS_URL',WP_PLUGIN_URL.'/exports-and-reports');
define('EXPORTS_REPORTS_DIR',WP_PLUGIN_DIR.'/exports-and-reports');

add_action('admin_init','exports_reports_init');
add_action('admin_menu','exports_reports_menu');

function exports_reports_init ()
{
    global $current_user,$wpdb;
    $capabilities = exports_reports_capabilities();
    // check version
    $version = get_option('exports_reports_version');
    if(empty($version))
    {
        // thx pods ;)
        $sql = file_get_contents(EXPORTS_REPORTS_DIR.'/assets/dump.sql');
        $sql_explode = preg_split("/;\n/", str_replace('wp_', $wpdb->prefix, $sql));
        if(count($sql_explode)==1)
            $sql_explode = preg_split("/;\r/", str_replace('wp_', $wpdb->prefix, $sql));
        for ($i = 0, $z = count($sql_explode); $i < $z; $i++)
        {
            $wpdb->query($sql_explode[$i]);
        }
        delete_option('exports_reports_version');
        add_option('exports_reports_version',EXPORTS_REPORTS_VERSION);
    }
    elseif($version!=EXPORTS_REPORTS_VERSION)
    {
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
        $has_full_access = true;
    if(!$has_full_access&&current_user_can('administrator'))
        $has_full_access = true;
    $min_cap = exports_reports_current_user_can_which(exports_reports_capabilities());
    if(empty($min_cap))
        $min_cap = 'exports_reports_full_access';
    add_menu_page('Reports', 'Reports', $has_full_access ? 'read' : $min_cap, 'exports-reports', null, EXPORTS_REPORTS_URL.'/assets/icons/16.png');
    add_submenu_page('exports-reports', 'About', 'About', $has_full_access ? 'read' : $min_cap, 'exports-reports', 'exports_reports_about');
    add_submenu_page('exports-reports', 'Manage Groups', 'Manage Groups', $has_full_access ? 'read' : 'exports_reports_settings', 'exports-reports-groups', 'exports_reports_groups');
    add_submenu_page('exports-reports', 'Manage Reports', 'Manage Reports', $has_full_access ? 'read' : 'exports_reports_settings', 'exports-reports-reports', 'exports_reports_reports');
    $groups = $wpdb->get_results('SELECT id,name FROM '.EXPORTS_REPORTS_TBL.'groups WHERE disabled=0');
    if(!empty($groups))
    {
        foreach($groups as $group)
        {
            $reports = @count($wpdb->get_results('SELECT id FROM '.EXPORTS_REPORTS_TBL.'reports WHERE `group`='.$group->id.' LIMIT 1'));
            if($reports>0)
                add_submenu_page('exports-reports', $group->name, $group->name, $has_full_access ? 'read' : 'exports_reports_view', 'exports-reports-group-'.$group->id, 'exports_reports_view');
        }
    }
    //add_submenu_page('exports-reports', 'Settings', 'Settings', $has_full_access ? 'read' : 'exports_reports_settings', 'exports-reports-settings', 'exports_reports_settings');
}
function exports_reports_get_capabilities ($caps)
{
    return array_merge($caps,exports_reports_capabilities());
}
function exports_reports_capabilities ()
{
    return array('exports_reports_settings','exports_reports_view');
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
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo EXPORTS_REPORTS_URL; ?>/assets/icons/32.png);"><br /></div>
    <h2>Exports and Reports - Settings</h2>
    <div style="height:20px;"></div>
    <link  type="text/css" rel="stylesheet" href="<?php echo EXPORTS_REPORTS_URL; ?>/assets/admin.css" />
    <form method="post" action="">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label>Clear Exports Directory</label></th>
                <td>
                    <input name="clear" type="submit" id="clear" value=" Clear Now " />
                    <span class="description">This will remove all files from your Exports directory - <?php echo WP_CONTENT_URL; ?>/exports/</span>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for=""></label></th>
                <td>
                    <input name="" type="text" id="" value="0" class="small-text" />
                    <span class="description"></span>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="Submit" class="button-primary" value="  Save Changes  " />
        </p>
    </form>
</div>
<?php
}
function exports_reports_groups ()
{
    require_once EXPORTS_REPORTS_DIR.'/classes/Admin.class.php';
    $columns = array('name','created'=>array('label'=>'Date Created','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    $form_columns['created']['date_touch_on_create'] = true;
    $form_columns['created']['display'] = false;
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $admin = new WP_UI_Admin(array('css'=>EXPORTS_REPORTS_URL.'/assets/admin.css','item'=>'Group','items'=>'Groups','table'=>EXPORTS_REPORTS_TBL.'groups','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>EXPORTS_REPORTS_URL.'/assets/icons/32.png'));
    $admin->go();
}
function exports_reports_reports ()
{
    require_once EXPORTS_REPORTS_DIR.'/classes/Admin.class.php';
    $columns = array('name','created'=>array('label'=>'Date Created','type'=>'date'),'updated'=>array('label'=>'Last Modified','type'=>'date'));
    $form_columns = $columns;
    $form_columns['created']['date_touch_on_create'] = true;
    $form_columns['created']['display'] = false;
    $form_columns['updated']['date_touch'] = true;
    $form_columns['updated']['display'] = false;
    $form_columns['group'] = array('label'=>'Group','type'=>'related','related'=>EXPORTS_REPORTS_TBL.'groups');
    $form_columns['sql_query'] = array('label'=>'SQL Query','type'=>'desc');
    $form_columns['field_data'] = array('label'=>'Fields','custom_input'=>'exports_reports_report_field','custom_save'=>'exports_reports_report_field_save');
    $form_columns['disable_export'] = array('label'=>'Disable Export?','type'=>'bool');
    $admin = new WP_UI_Admin(array('css'=>EXPORTS_REPORTS_URL.'/assets/admin.css','item'=>'Report','items'=>'Reports','table'=>EXPORTS_REPORTS_TBL.'reports','columns'=>$columns,'form_columns'=>$form_columns,'icon'=>EXPORTS_REPORTS_URL.'/assets/icons/32.png'));
    $admin->go();
}
function exports_reports_report_field ($column,$attributes,$obj)
{
    $field_data = @json_decode($obj->row[$column],true);
?>
<style type="text/css">
    .field_data { overflow:visible; }
    .field_data .sortable td div { width:160px; }
    .field_data .sortable td div.dragme {background:url(<?php echo EXPORTS_REPORTS_URL; ?>/assets/icons/move.png)!important; width:16px; height:16px; margin-right:8px; cursor:pointer; margin:auto auto; }
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
                    <div>Label (optional)</div> <input type="text" name="field_label[<?php echo $count; ?>]" value="<?php echo $field['label']; ?>" class="medium-text" /></td>
                <td><div>Hide from Report</div> Yes <input type="radio" name="field_hide_report[<?php echo $count; ?>]" value="1" class="medium-text"<?php echo ($field['hide_report']==1?' CHECKED':''); ?> />&nbsp;&nbsp; No<input type="radio" name="field_hide_report[<?php echo $count; ?>]" value="0" class="medium-text"<?php echo ($field['hide_report']!=1?' CHECKED':''); ?> /><br /><br />
                    <div>Hide from Export</div> Yes <input type="radio" name="field_hide_export[<?php echo $count; ?>]" value="1" class="medium-text"<?php echo ($field['hide_export']==1?' CHECKED':''); ?> />&nbsp;&nbsp; No<input type="radio" name="field_hide_export[<?php echo $count; ?>]" value="0" class="medium-text"<?php echo ($field['hide_export']!=1?' CHECKED':''); ?> /></td>
                <td><div>Data Type</div><select name="field_type[<?php echo $count; ?>]"><option value="text"<?php echo ($field['type']=='text'?' SELECTED':''); ?>>Text</option><option value="bool"<?php echo ($field['type']=='bool'?' SELECTED':''); ?>>Boolean (Checkbox)</option><option value="date"<?php echo ($field['type']=='date'?' SELECTED':''); ?>>Date</option></select><br /><br />
                    <div>Display Function (optional)</div> <input type="text" name="field_custom_display[<?php echo $count; ?>]" value="<?php echo $field['custom_display']; ?>" class="medium-text" /></td>
                <td>[<a href="#" onclick="field_remove_row(this);">remove</a>]</td>
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
                    <div>Label (optional)</div> <input type="text" name="field_label[0]" value="" class="medium-text" /></td>
                <td><div>Hide from Report</div> Yes <input type="radio" name="field_hide_report0]" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_report[0]" value="0" class="medium-text" CHECKED /><br /><br />
                    <div>Hide from Export</div> Yes <input type="radio" name="field_hide_export[0]" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_export[0]" value="0" class="medium-text" CHECKED /></td>
                <td><div>Data Type</div><select name="field_type[0]"><option value="text">Text</option><option value="bool">Boolean (Checkbox)</option><option value="date">Date</option></select><br /><br />
                    <div>Display Function (optional)</div> <input type="text" name="field_custom_display[0]" value="" class="medium-text" /></td>
                <td>[<a href="#" onclick="field_remove_row(this);">remove</a>]</td>
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
    }
    function field_add_row ()
    {
        var field_count = jQuery('.field_data tbody.sortable tr').length+1;
        var row = '<tr><td><div class="dragme"></div></td><td><div>Field Name</div> <input type="text" name="field_name['+field_count+']" value="" class="medium-text" /><br /><br /><div>Label (optional)</div> <input type="text" name="field_label['+field_count+']" value="" class="medium-text" /></td><td><div>Hide from Report</div> Yes <input type="radio" name="field_hide_report0]" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_report['+field_count+']" value="0" class="medium-text" CHECKED /><br /><br /><div>Hide from Export</div> Yes <input type="radio" name="field_hide_export['+field_count+']" value="1" class="medium-text" />&nbsp;&nbsp; No<input type="radio" name="field_hide_export['+field_count+']" value="0" class="medium-text" CHECKED /></td><td><div>Data Type</div><select name="field_type['+field_count+']"><option value="text">Text</option><option value="bool">Boolean (Checkbox)</option><option value="date">Date</option></select><br /><br /><div>Display Function (optional)</div> <input type="text" name="field_custom_display['+field_count+']" value="" class="medium-text" /></td><td>[<a href="#" onclick="field_remove_row(this);">remove</a>]</td></tr>';
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
            $value[] = array('name'=>$field,'label'=>$_POST['field_label'][$key],'hide_report'=>$_POST['field_hide_report'][$key],'hide_export'=>$_POST['field_hide_export'][$key],'custom_display'=>$_POST['field_custom_display'][$key],'type'=>$_POST['field_type'][$key]);
        }
    }
    return json_encode($value);
}
function exports_reports_view ()
{
    global $wpdb;
    $group_id = str_replace('exports-reports-group-','',$_GET['page']);
    $group = $wpdb->get_results('SELECT id,name FROM '.EXPORTS_REPORTS_TBL.'groups WHERE disabled=0 AND id='.$group_id);
    if(empty($group))
        return false;
    $reports = $wpdb->get_results('SELECT * FROM '.EXPORTS_REPORTS_TBL.'reports WHERE `group`='.$group_id);
    if(empty($reports))
        return false;
    $selectable_reports = array();
    $current_report = false;
    foreach($reports as $report)
    {
        if($current_report===false)
            $current_report = $report->id;
        $selectable_reports[$report->id] = array('name'=>$report->name,'sql_query'=>$report->sql_query,'export'=>($report->disable_export==0?true:false),'field_data'=>$report->field_data);
    }
    if(isset($_GET['report'])&&isset($selectable_reports[$_GET['report']]))
        $current_report = $_GET['report'];
    require_once EXPORTS_REPORTS_DIR.'/classes/Admin.class.php';
    $options = array('css'=>EXPORTS_REPORTS_URL.'/assets/admin.css','readonly'=>true,'export'=>$selectable_reports[$current_report]['export'],'search'=>(strlen($selectable_reports[$current_report]['field_data'])>0?true:false),'sql'=>$selectable_reports[$current_report]['sql_query'],'item'=>$selectable_reports[$current_report]['name'],'items'=>$selectable_reports[$current_report]['name'],'icon'=>EXPORTS_REPORTS_URL.'/assets/icons/32.png','heading'=>array('manage'=>'View Report:'));
    $field_data = @json_decode($selectable_reports[$current_report]['field_data'],true);
    if(is_array($field_data)&&!empty($field_data))
    {
        $options['columns'] = array();
        foreach($field_data as $field)
        {
            $options['columns'][$field['name']] = array();
            if(0<strlen($field['label']))
                $options['columns'][$field['name']]['label'] = $field['label'];
            if(0<strlen($field['custom_display']))
                $options['columns'][$field['name']]['custom_display'] = $field['custom_display'];
            if(0<strlen($field['type']))
                $options['columns'][$field['name']]['type'] = $field['type'];
            if(1==$field['hide_report'])
                $options['columns'][$field['name']]['display'] = false;
            if(1==$field['hide_export'])
                $options['columns'][$field['name']]['export'] = false;
        }
    }
    $admin = new WP_UI_Admin($options);
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
    <div id="icon-edit-pages" class="icon32" style="background-position:0 0;background-image:url(<?php echo EXPORTS_REPORTS_URL; ?>/assets/icons/32.png);"><br /></div>
    <h2>About the Exports and Reports plugin</h2>
    <div style="height:20px;"></div>
    <link  type="text/css" rel="stylesheet" href="<?php echo EXPORTS_REPORTS_URL; ?>/assets/admin.css" />
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
                            <li>Admin.Class.php - A class for plugins to manage data using the WordPress UI appearance</li>
                        </ul>
                    </li>
                    <li><strong>Reporting</strong>
                        <ul>
                            <li>Automatic Pagination</li>
                            <li>Show only the fields you want to show</li>
                            <li>Pre-display modification through custom defined function per field or row</li>
                        </ul>
                    </li>
                    <li><strong>Exporting</strong>
                        <ul>
                            <li>CSV - Comma-separated Values</li>
                            <li>TAB - Tab-delimited Values</li>
                            <li>XML - XML 1.0 UTF-8 data</li>
                            <li>JSON - JSON for use in Javascript and PHP5+</li>
                        </ul>
                    </li>
                </ul>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">Upcoming Features - Roadmap</th>
            <td>
                <dl>
                    <dt>0.3</dt>
                    <dd>
                        <ul>
                            <li>Daily Export Cleanup via wp_cron</li>
                            <li>Pods CMS Framework integration</li>
                            <li>Limit which User Roles have access to a Group or Report</li>
                            <li>Ability to clear entire export directory</li>
                            <li>Filter by Date</li>
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
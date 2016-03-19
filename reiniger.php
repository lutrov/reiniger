<?php

/*
Plugin Name: Reiniger
Description: Clean up your Wordpress database by removing "post revision", "post draft", "post autodraft", "moderated comments", "spam comments". "trash comments", "orphan postmeta", "orphan commentmeta", "orphan relationships" and "transient option" entries. You can also optimise your existing database tables or delete any unused database tables without using specialist tools. Why this plugin name? Reiniger means "cleaner" in German.
Version: 1.8
Author: Ivan Lutrov
Author URI: http://lutrov.com/
*/

defined('ABSPATH') || die('Ahem.');

//
// This plugin is only used by admins, while in the admin dashboard.
//
if (is_admin() == false) {
	return;
}

//
// Set max execution time to 3 minutes.
//
ini_set('max_execution_time', 180);

//
// Convert bytes to human friendly file size.
//
function reiniger_human_friendly_size($value, $precision = 1) {
	$result = null;
	if (($value / 1024) < 1) {
		$result = $value . 'B';
	} elseif (($value / 1024 / 1024) < 1) {
		$result = number_format($value / 1024, $precision) . 'K';
	} else {
		$result = number_format($value / 1024 / 1024, $precision) . 'M';
	}
	return $result;
}

//
// Wordpress core system tables.
//
function reiniger_system_tables() {
	$result = array(
		'commentmeta',
		'comments',
		'links',
		'options',
		'postmeta',
		'posts',
		'terms',
		'term_relationships',
		'term_taxonomy',
		'usermeta',
		'users'
	);
	return $result;
}

//
// Guess plugin name based on the specified directory.
//
function reiniger_guess_plugin_name($dir) {
	static $plugins = null;
	if (function_exists('get_plugins') == false) {
		require_once(ABSPATH . 'wp-admin/includes/plugin.php');
	}
	if (count($plugins) == 0) {
		$plugins = get_plugins();
	}
	$result = $dir;
	foreach ($plugins as $key => $data) {
		if (substr($key, 0, strlen($dir) + 1) == $dir . '/') {
			$result = $data['Name'];
			break;
		}
	}
	return $result;
}

//
// Delete specified entries.
//
function reiniger_delete_entries($type) {
	global $wpdb;
	switch($type) {
		case 'revision':
			$sql = sprintf("DELETE FROM %s WHERE post_type = 'revision'", $wpdb->posts);
			$wpdb->query($sql);
			break;
		case 'draft':
			$sql = sprintf("DELETE FROM %s WHERE post_status = 'draft'", $wpdb->posts);
			$wpdb->query($sql);
			break;
		case 'autodraft':
			$sql = sprintf("DELETE FROM %s WHERE post_status = 'auto-draft'", $wpdb->posts);
			$wpdb->query($sql);
			break;
		case 'moderated':
			$sql = sprintf("DELETE FROM %s WHERE comment_approved = '0'", $wpdb->comments);
			$wpdb->query($sql);
			break;
		case 'spam':
			$sql = sprintf("DELETE FROM %s WHERE comment_approved = 'spam'", $wpdb->comments);
			$wpdb->query($sql);
			break;
		case 'trash':
			$sql = sprintf("DELETE FROM %s WHERE comment_approved = 'trash'", $wpdb->comments);
			$wpdb->query($sql);
			break;
		case 'postmeta':
			$sql = sprintf("DELETE pm FROM %s AS pm LEFT JOIN %s AS wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL", $wpdb->postmeta, $wpdb->posts);
			$wpdb->query($sql);
			break;
		case 'commentmeta':
			$sql = sprintf("DELETE FROM %s WHERE comment_id NOT IN (SELECT comment_id FROM %s)", $wpdb->commentmeta, $wpdb->comments);
			$wpdb->query($sql);
			break;
		case 'relationships':
			$sql = sprintf("DELETE FROM %s WHERE term_taxonomy_id = 1 AND object_id NOT IN (SELECT id FROM %s)", $wpdb->term_relationships, $wpdb->posts);
			$wpdb->query($sql);
			break;
		case 'transient':
			$sql = sprintf("DELETE FROM %s WHERE option_name LIKE '%%_transient_%%'", $wpdb->options);
			$wpdb->query($sql);
			break;
	}
}

//
// Count specified entries.
//
function reiniger_count_entries($type) {
	global $wpdb;
	switch($type) {
		case 'revision':
			$sql =  sprintf("SELECT COUNT(1) FROM %s WHERE post_type = 'revision'", $wpdb->posts);
			$count = $wpdb->get_var($sql);
			break;
		case 'draft':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE post_status = 'draft'", $wpdb->posts);
			$count = $wpdb->get_var($sql);
			break;
		case 'autodraft':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE post_status = 'auto-draft'", $wpdb->posts);
			$count = $wpdb->get_var($sql);
			break;
		case 'moderated':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE comment_approved = '0'", $wpdb->comments);
			$count = $wpdb->get_var($sql);
			break;
		case 'spam':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE comment_approved = 'spam'", $wpdb->comments);
			$count = $wpdb->get_var($sql);
			break;
		case 'trash':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE comment_approved = 'trash'", $wpdb->comments);
			$count = $wpdb->get_var($sql);
			break;
		case 'postmeta':
			$sql = sprintf("SELECT COUNT(1) FROM %s AS pm LEFT JOIN %s AS wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL", $wpdb->postmeta, $wpdb->posts);
			$count = $wpdb->get_var($sql);
			break;
		case 'commentmeta':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE comment_id NOT IN (SELECT comment_id FROM %s)", $wpdb->commentmeta, $wpdb->comments);
			$count = $wpdb->get_var($sql);
			break;
		case 'relationships':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE term_taxonomy_id = 1 AND object_id NOT IN (SELECT ID FROM %s)", $wpdb->term_relationships, $wpdb->posts);
			$count = $wpdb->get_var($sql);
			break;
		case 'transient':
			$sql = sprintf("SELECT COUNT(1) FROM %s WHERE option_name LIKE '%%_transient_%%'", $wpdb->options);
			$count = $wpdb->get_var($sql);
			break;
		default:
			$count = 0;
	}
	return $count;
}

//
// Delete specified table.
//
function reiniger_delete_table($table) {
	global $wpdb;
	$system_tables = reiniger_system_tables();
	if (in_array(str_replace($wpdb->prefix, null, $table), $system_tables) == false) {
		$sql = sprintf('DROP TABLE %s', $table);
		$result = $wpdb->query($sql);
	}
}


//
// Optimise all tables.
//
function reiniger_optimise_tables() {
	global $wpdb;
	$sql = sprintf('SHOW TABLE STATUS FROM %s', DB_NAME);
	$results = $wpdb->get_results($sql, ARRAY_A);
	foreach ($results as $row) {
		$sql = sprintf('REPAIR TABLE %s', $row['Name']);
		$wpdb->query($sql);
		$sql = sprintf('OPTIMIZE TABLE %s', $row['Name']);
		$wpdb->query($sql);
	}
}

//
// Screen and main processing.
//
function reiniger_process() {
	global $wpdb;
	if (current_user_can('manage_options') == false) {
		die('Access denied.');
	}
	printf("<div id=\"reiniger\" class=\"wrap\">\n");
	printf("<h1>Reiniger</h1>\n");
	switch (true) {
		case array_key_exists('reiniger_revision', $_POST):
			reiniger_delete_entries('revision');
			$message = 'All post revisions have been deleted.';
			break;
		case array_key_exists('reiniger_draft', $_POST):
			reiniger_delete_entries('draft');
			$message = 'All post drafts have been deleted.';
			break;
		case array_key_exists('reiniger_autodraft', $_POST):
			reiniger_delete_entries('autodraft');
			$message = 'All post autodrafts have been deleted.';
			break;
		case array_key_exists('reiniger_moderated', $_POST):
			reiniger_delete_entries('moderated');
			$message = 'All moderated comments have been deleted.';
			break;
		case array_key_exists('reiniger_spam', $_POST):
			reiniger_delete_entries('spam');
			$message = 'All spam comments have been deleted.';
			break;
		case array_key_exists('reiniger_trash', $_POST):
			reiniger_delete_entries('trash');
			$message = 'All trash comments have been deleted.';
			break;
		case array_key_exists('reiniger_postmeta', $_POST):
			reiniger_delete_entries('postmeta');
			$message = 'All orphan postmeta have been deleted.';
			break;
		case array_key_exists('reiniger_commentmeta', $_POST):
			reiniger_delete_entries('commentmeta');
			$message = 'All orphan commentmeta have been deleted.';
			break;
		case array_key_exists('reiniger_relationships', $_POST):
			reiniger_delete_entries('relationships');
			$message = 'All orphan relationships have been deleted.';
			break;
		case array_key_exists('reiniger_transient', $_POST):
			reiniger_delete_entries('transient');
			$message = 'All transient options have been deleted.';
			break;
		case array_key_exists('reiniger_all', $_POST):
			foreach (array('transient', 'revision', 'draft', 'autodraft', 'moderated', 'spam', 'trash', 'postmeta', 'commentmeta', 'relationships') as $type) {
				reiniger_delete_entries($type);
			}
			$message = 'All redundant data have been deleted.';
			break;
		case array_key_exists('reiniger_delete', $_POST):
			reiniger_delete_table($_POST['reiniger_delete']);
			$message = sprintf('Database table <em>%s</em> has been deleted.', $_POST['reiniger_delete']);
			break;
		case array_key_exists('reiniger_optimise_tables', $_POST):
			reiniger_optimise_tables();
			$message = 'All database tables have been optimised.';
			break;
		default:
			$message = null;
			break;
	}
	if (strlen($message) > 0) {
		printf("<div id=\"message\" class=\"updated fade\"><p><strong>%s</strong></p></div>\n", $message);
	}
	$counts = array();
	foreach (array('revision', 'draft', 'autodraft', 'moderated', 'spam', 'trash', 'postmeta', 'commentmeta', 'relationships', 'transient') as $type) {
		$counts[$type] = reiniger_count_entries($type);
	}
	printf("<p>Reiniger is installed and working correctly. <a href=\"#\" id=\"reiniger-help-toggle\">Help</a></p>\n");
	printf("<div id=\"reiniger-help\">\n");
	printf("<p>Reiniger cleans up your Wordpress database by removing <em>post revision</em>, <em>post draft</em>, <em>post autodraft</em>, <em>moderated comments</em>, <em>spam comments</em>. <em>trash comments</em>, <em>orphan postmeta</em>, <em>orphan commentmeta</em>, <em>orphan relationships</em> and <em>transient option</em> entries. It also allows you to optimise your existing database tables or to delete any unused database tables without using specialist tools.</p>");
	printf("</div>\n");
	printf("<p>There were %s entries found.</p>\n", array_sum($counts));
	printf("<table class=\"widefat\">");
	printf("<thead>\n");
	printf("<tr>\n");
	printf("<th scope=\"col\" width=\"80%%\">Type</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Count</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Action</th>\n");
	printf("</tr>\n");
	printf("</thead>\n");
	printf("<tbody id=\"the-list\">\n");
	printf("<tr class=\"%s\"><td class=\"column-name\">Post Revisions</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_revision\" value=\"revision\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", 'alternate', $counts['revision'], $counts['revision'] > 0 ? 'button-primary' : 'button', $counts['revision'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Post Drafts</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_draft\" value=\"draft\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", null, $counts['draft'], $counts['draft'] > 0 ? 'button-primary' : 'button', $counts['draft'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Post Autodrafts</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_autodraft\" value=\"autodraft\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", 'alternate', $counts['autodraft'], $counts['autodraft'] > 0 ? 'button-primary' : 'button', $counts['autodraft'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Moderated Comments</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_moderated\" value=\"moderated\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", null, $counts['moderated'], $counts['moderated'] > 0 ? 'button-primary' : 'button', $counts['moderated'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Spam Comments</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_spam\" value=\"spam\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", 'alternate', $counts['spam'], $counts['spam'] > 0 ? 'button-primary' : 'button', $counts['spam'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Trash Comments</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_trash\" value=\"trash\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", null, $counts['trash'], $counts['trash'] > 0 ? 'button-primary' : 'button', $counts['trash'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Orphan Postmeta</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_postmeta\" value=\"postmeta\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", 'alternate', $counts['postmeta'], $counts['postmeta'] > 0 ? 'button-primary' : 'button', $counts['postmeta'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Orphan Commentmeta</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_commentmeta\" value=\"commentmeta\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", null, $counts['commentmeta'], $counts['commentmeta'] > 0 ? 'button-primary' : 'button', $counts['commentmeta'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Orphan Relationships</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_relationships\" value=\"relationships\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", 'alternate', $counts['relationships'], $counts['relationships'] > 0 ? 'button-primary' : 'button', $counts['relationships'] > 0 ? null : 'disabled="disabled"');
	printf("<tr class=\"%s\"><td class=\"column-name\">Transient Options</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_transient\" value=\"transient\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", null, $counts['transient'], $counts['transient'] > 0 ? 'button-primary' : 'button', $counts['transient'] > 0 ? null : 'disabled="disabled"');
	printf("</tbody>\n");
	printf("</table>\n");
	$sql = sprintf('SHOW TABLE STATUS FROM %s', DB_NAME);
	$results = $wpdb->get_results($sql, ARRAY_A);
	printf("<p><form action=\"\" method=\"post\" class=\"all\"><input type=\"hidden\" name=\"reiniger_all\" value=\"all\" /><input type=\"submit\" class=\"button-primary\" value=\"Delete All\" /></form></p>\n");
	printf("<p>There were %s tables found.</p>\n", count($results));
	printf("<table class=\"widefat\">\n");
	printf("<thead>\n");
	printf("<tr><th scope=\"col\" width=\"80%%\">Table</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Size</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Action</th></tr>\n");
	printf("</thead>\n");
	$exec = false;
	if (function_exists('exec')) {
		$disabled = ini_get('disable_functions') . ',';
		if (strlen($disabled) > 0) {
			$exec = strpos($disabled, 'exec,') === false ? true : false;
		}
	}
	$system_tables = reiniger_system_tables();
	$class = 'alternate';
	$total = 0;
	$info = null;
	$c = 0;
	printf("<tbody id=\"the-list\">\n");
	foreach ($results as $row) {
		$size = ($row['Data_length'] + $row['Index_length']);
		if (in_array(str_replace($wpdb->prefix, null, $row['Name']), $system_tables)) {
			printf("<tr class=\"%s\"><td class=\"column-name\"><span class=\"%s\" title=\"This is a system table.\">%s</span></td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_delete\" value=\"%s\" /><input type=\"submit\" class=\"button\" value=\"Delete\" disabled=\"disabled\" /></form></td></tr>\n", $class, 'system', $row['Name'], reiniger_human_friendly_size($size), $row['Name']);
		} else {
			$style = 'button';
			$onclick = 'disabled="disabled"';
			$description = '<p class="description">Unable to determine if this table is referenced by an installed plugin.</p>';
			if ($exec) {
				$command = 'grep -rl "' . substr($row['Name'], strlen($wpdb->prefix)) . ' " ' . dirname(dirname(__FILE__));
				$matches = array();
				exec($command, $matches);
				if (count($matches) == 0) {
					$style = 'button-primary';
					$onclick = "onclick=\"return confirm('Are you sure? There is no undo for this.');\"";
					$description = null;
					$info = sprintf("%s\nDROP TABLE %s;\n", $info, $row['Name']);
					$c++;
				} else {
					$dir = strtok(ltrim(str_replace(dirname(dirname(__FILE__)), null, $matches[0]), '/'), '/');
					$guess = reiniger_guess_plugin_name($dir);
					$description = '<p class="description">This table seems to be referenced by the <code>"' . $guess . '"</code> plugin.</p>';
				}
			}
			printf("<tr class=\"%s\"><td class=\"column-name\"><a href=\"http://google.com/search?q=%s+wordpress&amp;pws=0\" title=\"Google lookup.\" target=\"_blank\">%s</a>%s</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_delete\" value=\"%s\" /><input type=\"submit\" class=\"%s\" value=\"Delete\" %s /></form></td></tr>\n", $class, substr($row['Name'], strlen($wpdb->prefix)), $row['Name'], $description, reiniger_human_friendly_size($size), $row['Name'], $style, $onclick);
		}
		$class = strlen($class) > 0 ? null : 'alternate';
		$total += $size;
	}
	printf("</tbody>\n");
	printf("<tfoot>\n");
	printf("<tr><th scope=\"col\" width=\"80%%\">Total</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">%s</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">&nbsp;</th></tr>\n", reiniger_human_friendly_size($total));
	printf("</tfoot>\n");
	printf("</table>\n");
	printf("<p><form action=\"\" method=\"post\" class=\"all\"><input type=\"hidden\" name=\"reiniger_optimise_tables\" value=\"optimise\" /><input type=\"submit\" class=\"button-primary\" value=\"Optimise All Tables\" /></form></p>\n");
	printf("<p class=\"usage\">Current memory usage is %s, which peaked at %s.%s</p>", reiniger_human_friendly_size(memory_get_usage()), reiniger_human_friendly_size(memory_get_peak_usage()), $c > 0 ? ' <a href="#" id="reiniger-debug-toggle">Debug Info</a>' : null);
	$info = trim($info);
	if (strlen($info) > 0) {
		printf('<div id="reiniger-debug"><p><em>It may be quicker to delete these %s tables using your favourite database tool instead:</em></p><pre>%s</pre></div>', $c, $info);
	}

	printf("</div>\n");
}

//
// Create settings link.
//
function reiniger_settings_link($links, $plugin) {
	if ($plugin == plugin_basename(__FILE__)) {
		$link = '<a href="options-general.php?page=' . dirname(plugin_basename(__FILE__)) . '/reiniger.php">' . __("Settings") . '</a>';
		array_unshift($links, $link);
	}
	return $links;
}
// add_filter('plugin_action_links', 'reiniger_settings_link', 10, 2);

//
// Add plugin to dashboard tools menu.
//
function reiniger_init() {
	add_management_page('Reiniger', 'Reiniger', 'manage_options', __FILE__, 'reiniger_process');
}
add_filter('admin_menu', 'reiniger_init');

//
// Add custom stylesheet to admin header.
//
function reiniger_custom_stylesheet() {
	if (strpos($_SERVER['QUERY_STRING'], 'page=reiniger') !== false) {
		printf("<style>\n");
		printf("#reiniger table td span.system {color:#222;font-weight:bold}\n");
		printf("#reiniger table td a {color:#222;text-decoration:none}\n");
		printf("#reiniger table td a:hover {text-decoration:underline}\n");
		printf("#reiniger-help-toggle {padding-left:6px;font-size:90%%;text-transform:uppercase}\n");
		printf("#reiniger-help {display:none}\n");
		printf("#reiniger-debug-toggle {padding-left:6px;font-size:90%%;text-transform:uppercase}\n");
		printf("#reiniger-debug {background:#fff;border:1px dotted #999;padding:5px 15px;margin:20px 0;line-height:1;display:none}\n");
		printf("#reiniger-debug pre {padding:0 40px}\n");
		printf("#reiniger form {background:inherit!important}\n");
		printf("#reiniger form.all {padding-left:0!important}\n");
		printf("#reiniger table td form {margin:0!important;padding:0!important}\n");
		printf("#reiniger p.usage {margin-top:-10px}\n");
		printf("</style>\n");
	}
}
add_filter('admin_head', 'reiniger_custom_stylesheet', 1);

//
// Add help show/hide toggle to admin footer.
//
function reiniger_admin_jquery() {
	echo "<script type=\"text/javascript\">\n";
	echo "jQuery(document).ready(function(){jQuery('#reiniger-help-toggle').click(function(e){e.preventDefault();if(jQuery('#reiniger-help').is(':hidden')){jQuery('#reiniger-help').show();}else{jQuery('#reiniger-help').hide();}});});\n";
	printf("jQuery(document).ready(function(){jQuery('#reiniger-debug-toggle').click(function(e){e.preventDefault();if(jQuery('#reiniger-debug').is(':hidden')){jQuery('#reiniger-debug').show();}else{jQuery('#reiniger-debug').hide();}});});\n");
	echo "</script>\n";
}
add_filter('admin_footer', 'reiniger_admin_jquery');

?>
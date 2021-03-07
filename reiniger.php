<?php

/*
Plugin Name: Reiniger
Version: 2.3
Description: Clean up your Wordpress database by removing "post revision", "post draft", "post autodraft", "moderated comments", "spam comments". "trash comments", "orphan postmeta", "orphan commentmeta", "orphan relationships" and "transient option" entries. You can also optimise your existing database tables or delete any unused database tables without using specialist tools. Why this plugin name? Reiniger means "cleaner" in German.
Plugin URI: https://github.com/lutrov/reiniger
Copyright: 2015, Ivan Lutrov
Author: Ivan Lutrov
Author URI: http://lutrov.com/

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
Street, Fifth Floor, Boston, MA 02110-1301, USA. Also add information on how to
contact you by electronic and paper mail.
*/

defined('ABSPATH') || die('Ahem.');

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
		$result = sprintf('%sB', $value);
	} elseif (($value / 1024 / 1024) < 1) {
		$result = sprintf('%sK', number_format($value / 1024, $precision));
	} else {
		$result = sprintf('%sM', number_format($value / 1024 / 1024, $precision));
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
// Delete specified entries.
//
function reiniger_delete_entries($type) {
	global $wpdb;
	switch($type) {
		case 'revision':
			$query = sprintf("DELETE FROM %s WHERE post_type = 'revision'", $wpdb->posts);
			$wpdb->query($query);
			break;
		case 'draft':
			$query = sprintf("DELETE FROM %s WHERE post_status = 'draft'", $wpdb->posts);
			$wpdb->query($query);
			break;
		case 'autodraft':
			$query = sprintf("DELETE FROM %s WHERE post_status = 'auto-draft'", $wpdb->posts);
			$wpdb->query($query);
			break;
		case 'moderated':
			$query = sprintf("DELETE FROM %s WHERE comment_approved = '0'", $wpdb->comments);
			$wpdb->query($query);
			break;
		case 'spam':
			$query = sprintf("DELETE FROM %s WHERE comment_approved = 'spam'", $wpdb->comments);
			$wpdb->query($query);
			break;
		case 'trash':
			$query = sprintf("DELETE FROM %s WHERE comment_approved = 'trash'", $wpdb->comments);
			$wpdb->query($query);
			break;
		case 'postmeta':
			$query = sprintf("DELETE pm FROM %s AS pm LEFT JOIN %s AS wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL", $wpdb->postmeta, $wpdb->posts);
			$wpdb->query($query);
			break;
		case 'commentmeta':
			$query = sprintf("DELETE FROM %s WHERE comment_id NOT IN (SELECT comment_id FROM %s)", $wpdb->commentmeta, $wpdb->comments);
			$wpdb->query($query);
			break;
		case 'relationships':
			$query = sprintf("DELETE FROM %s WHERE term_taxonomy_id = 1 AND object_id NOT IN (SELECT id FROM %s)", $wpdb->term_relationships, $wpdb->posts);
			$wpdb->query($query);
			break;
		case 'transient':
			$query = sprintf("DELETE FROM %s WHERE option_name LIKE '%%_transient_%%'", $wpdb->options);
			$wpdb->query($query);
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
			$query =  sprintf("SELECT COUNT(1) FROM %s WHERE post_type = 'revision'", $wpdb->posts);
			$count = $wpdb->get_var($query);
			break;
		case 'draft':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE post_status = 'draft'", $wpdb->posts);
			$count = $wpdb->get_var($query);
			break;
		case 'autodraft':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE post_status = 'auto-draft'", $wpdb->posts);
			$count = $wpdb->get_var($query);
			break;
		case 'moderated':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE comment_approved = '0'", $wpdb->comments);
			$count = $wpdb->get_var($query);
			break;
		case 'spam':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE comment_approved = 'spam'", $wpdb->comments);
			$count = $wpdb->get_var($query);
			break;
		case 'trash':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE comment_approved = 'trash'", $wpdb->comments);
			$count = $wpdb->get_var($query);
			break;
		case 'postmeta':
			$query = sprintf("SELECT COUNT(1) FROM %s AS pm LEFT JOIN %s AS wp ON wp.ID = pm.post_id WHERE wp.ID IS NULL", $wpdb->postmeta, $wpdb->posts);
			$count = $wpdb->get_var($query);
			break;
		case 'commentmeta':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE comment_id NOT IN (SELECT comment_id FROM %s)", $wpdb->commentmeta, $wpdb->comments);
			$count = $wpdb->get_var($query);
			break;
		case 'relationships':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE term_taxonomy_id = 1 AND object_id NOT IN (SELECT ID FROM %s)", $wpdb->term_relationships, $wpdb->posts);
			$count = $wpdb->get_var($query);
			break;
		case 'transient':
			$query = sprintf("SELECT COUNT(1) FROM %s WHERE option_name LIKE '%%_transient_%%'", $wpdb->options);
			$count = $wpdb->get_var($query);
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
		$query = sprintf('DROP TABLE %s', $table);
		$result = $wpdb->query($query);
	}
}


//
// Optimise all tables.
//
function reiniger_optimise_tables() {
	global $wpdb;
	$query = sprintf('SHOW TABLE STATUS FROM %s', DB_NAME);
	$results = $wpdb->get_results($query, ARRAY_A);
	foreach ($results as $row) {
		$query = sprintf('REPAIR TABLE %s', $row['Name']);
		$wpdb->query($query);
		$query = sprintf('OPTIMIZE TABLE %s', $row['Name']);
		$wpdb->query($query);
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
	echo sprintf("<div id=\"reiniger\" class=\"wrap\">\n");
	echo sprintf("<h1>Reiniger</h1>\n");
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
		echo sprintf("<div id=\"message\" class=\"updated fade\"><p><strong>%s</strong></p></div>\n", $message);
	}
	echo sprintf("<div class=\"reiniger-help\">\n");
	echo sprintf("<p>Reiniger cleans up your Wordpress database by removing <em>post revision</em>, <em>post draft</em>, <em>post autodraft</em>, <em>moderated comments</em>, <em>spam comments</em>. <em>trash comments</em>, <em>orphan postmeta</em>, <em>orphan commentmeta</em>, <em>orphan relationships</em> and <em>transient option</em> entries. It also allows you to optimise your existing or to delete any unused Wordpress database tables without using specialist tools.</p>");
	echo sprintf("</div>\n");
	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		reiniger_analyse_current_issues();
	} else {
		echo sprintf("<p><form action=\"\" method=\"post\"><input type=\"submit\" class=\"button-primary\" value=\"Analyse Current Issues\"></form></p>\n");
	}
	echo sprintf("</div>\n");
}

//
// Analyse current issues to report on.
//
function reiniger_analyse_current_issues() {
	global $wpdb;
	$counts = array();
	foreach (array('revision', 'draft', 'autodraft', 'moderated', 'spam', 'trash', 'postmeta', 'commentmeta', 'relationships', 'transient') as $type) {
		$counts[$type] = reiniger_count_entries($type);
	}
	echo sprintf("<p class=\"count\">There were %s entries found.</p>\n", array_sum($counts));
	echo sprintf("<table class=\"widefat\">");
	echo sprintf("<thead>\n");
	echo sprintf("<tr>\n");
	echo sprintf("<th scope=\"col\" width=\"80%%\">Type</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Count</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Action</th>\n");
	echo sprintf("</tr>\n");
	echo sprintf("</thead>\n");
	echo sprintf("<tbody id=\"the-list\">\n");
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Post Revisions</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_revision\" value=\"revision\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", 'alternate', $counts['revision'], $counts['revision'] > 0 ? 'button-primary' : 'button', $counts['revision'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Post Drafts</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_draft\" value=\"draft\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", null, $counts['draft'], $counts['draft'] > 0 ? 'button-primary' : 'button', $counts['draft'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Post Autodrafts</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_autodraft\" value=\"autodraft\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", 'alternate', $counts['autodraft'], $counts['autodraft'] > 0 ? 'button-primary' : 'button', $counts['autodraft'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Moderated Comments</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_moderated\" value=\"moderated\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", null, $counts['moderated'], $counts['moderated'] > 0 ? 'button-primary' : 'button', $counts['moderated'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Spam Comments</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_spam\" value=\"spam\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", 'alternate', $counts['spam'], $counts['spam'] > 0 ? 'button-primary' : 'button', $counts['spam'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Trash Comments</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_trash\" value=\"trash\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", null, $counts['trash'], $counts['trash'] > 0 ? 'button-primary' : 'button', $counts['trash'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Orphan Postmeta</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_postmeta\" value=\"postmeta\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", 'alternate', $counts['postmeta'], $counts['postmeta'] > 0 ? 'button-primary' : 'button', $counts['postmeta'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Orphan Commentmeta</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_commentmeta\" value=\"commentmeta\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", null, $counts['commentmeta'], $counts['commentmeta'] > 0 ? 'button-primary' : 'button', $counts['commentmeta'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Orphan Relationships</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_relationships\" value=\"relationships\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", 'alternate', $counts['relationships'], $counts['relationships'] > 0 ? 'button-primary' : 'button', $counts['relationships'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("<tr class=\"%s\"><td class=\"column-name\">Transient Options</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_transient\" value=\"transient\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", null, $counts['transient'], $counts['transient'] > 0 ? 'button-primary' : 'button', $counts['transient'] > 0 ? null : 'disabled="disabled"');
	echo sprintf("</tbody>\n");
	echo sprintf("</table>\n");
	$query = sprintf('SHOW TABLE STATUS FROM %s', DB_NAME);
	$results = $wpdb->get_results($query, ARRAY_A);
	echo sprintf("<p><form action=\"\" method=\"post\" class=\"all\"><input type=\"hidden\" name=\"reiniger_all\" value=\"all\"><input type=\"submit\" class=\"button-primary\" value=\"Delete All\"></form></p>\n");
	echo sprintf("<p class=\"count\">There were %s tables found.</p>\n", count($results));
	echo sprintf("<table class=\"widefat\">\n");
	echo sprintf("<thead>\n");
	echo sprintf("<tr><th scope=\"col\" width=\"80%%\">Table</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Size</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">Action</th></tr>\n");
	echo sprintf("</thead>\n");
	$exec = false;
	if (function_exists('exec')) {
		$disabled = sprintf('%s,', ini_get('disable_functions'));
		if (strlen($disabled) > 0) {
			$exec = strpos($disabled, 'exec,') === false ? true : false;
		}
	}
	$system_tables = reiniger_system_tables();
	$class = 'alternate';
	$total = 0;
	echo sprintf("<tbody id=\"the-list\">\n");
	foreach ($results as $row) {
		$size = ($row['Data_length'] + $row['Index_length']);
		if (in_array(str_replace($wpdb->prefix, null, $row['Name']), $system_tables)) {
			echo sprintf("<tr class=\"%s\"><td class=\"column-name\"><span class=\"%s\" title=\"This is a system table.\">%s</span></td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_delete\" value=\"%s\"><input type=\"submit\" class=\"button\" value=\"Delete\" disabled=\"disabled\"></form></td></tr>\n", $class, 'system', $row['Name'], reiniger_human_friendly_size($size), $row['Name']);
		} else {
			$style = 'button';
			$onclick = 'disabled="disabled"';
			$description = '<p class="description">Unable to determine if this table is referenced by an installed plugin.</p>';
			if ($exec) {
				$command = sprintf('grep -rl "%s" %s', substr($row['Name'], strlen($wpdb->prefix)), dirname(dirname(__FILE__)));
				$matches = array();
				exec($command, $matches);
				if (count($matches) == 0) {
					$style = 'button-primary';
					$onclick = "onclick=\"return confirm('Are you sure? There is no undo for this.');\"";
					$description = null;
				} else {
					$guess = ucwords(str_replace('-', ' ', strtok(ltrim(str_replace(dirname(dirname(__FILE__)), null, $matches[0]), '/'), '/')));
					$description = sprintf('<p class="description">This table seems to be referenced by the <code>%s</code> plugin.</p>', $guess);
				}
			}
			echo sprintf("<tr class=\"%s\"><td class=\"column-name\"><a href=\"http://google.com/search?q=%s+wordpress&amp;pws=0\" title=\"Google lookup.\" target=\"_blank\">%s</a>%s</td><td class=\"column-name num\">%s</td><td class=\"column-name num\"><form action=\"\" method=\"post\"><input type=\"hidden\" name=\"reiniger_delete\" value=\"%s\"><input type=\"submit\" class=\"%s\" value=\"Delete\" %s></form></td></tr>\n", $class, substr($row['Name'], strlen($wpdb->prefix)), $row['Name'], $description, reiniger_human_friendly_size($size), $row['Name'], $style, $onclick);
		}
		$class = strlen($class) > 0 ? null : 'alternate';
		$total += $size;
	}
	echo sprintf("</tbody>\n");
	echo sprintf("<tfoot>\n");
	echo sprintf("<tr><th scope=\"col\" width=\"80%%\">Total</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">%s</th><th scope=\"col\" class=\"manage-column num\" width=\"10%%\">&nbsp;</th></tr>\n", reiniger_human_friendly_size($total));
	echo sprintf("</tfoot>\n");
	echo sprintf("</table>\n");
	echo sprintf("<p><form action=\"\" method=\"post\" class=\"all\"><input type=\"hidden\" name=\"reiniger_optimise_tables\" value=\"optimise\"><input type=\"submit\" class=\"button-primary\" value=\"Optimise All\"></form></p>\n");
}

//
// Create settings link.
//
// add_filter('plugin_action_links', 'reiniger_settings_link', 10, 2);
function reiniger_settings_link($links, $plugin) {
	if ($plugin == plugin_basename(__FILE__)) {
		$link = sprintf('<a href="options-general.php?page=%s/reiniger.php">%s</a>', dirname(plugin_basename(__FILE__)), __('Settings'));
		array_unshift($links, $link);
	}
	return $links;
}

//
// Add plugin to dashboard tools menu.
//
add_filter('admin_menu', 'reiniger_init');
function reiniger_init() {
	add_management_page('Reiniger', 'Reiniger', 'manage_options', __FILE__, 'reiniger_process');
}

//
// Add custom stylesheet to admin header.
//
add_filter('admin_head', 'reiniger_custom_stylesheet', 90);
function reiniger_custom_stylesheet() {
	if (strpos($_SERVER['REQUEST_URI'], 'page=reiniger') <> false) {
		echo sprintf("<style>\n");
		echo sprintf("#reiniger p.count {margin-top:30px!important}\n");
		echo sprintf("#reiniger table td span.system {color:#222;font-weight:bold}\n");
		echo sprintf("#reiniger table td a {color:#222;text-decoration:none}\n");
		echo sprintf("#reiniger table td a:hover {text-decoration:underline}\n");
		echo sprintf("#reiniger-help {display:block}\n");
		echo sprintf("#reiniger form {background:inherit!important}\n");
		echo sprintf("#reiniger form.all {margin-bottom:20px!important;padding-left:0!important}\n");
		echo sprintf("#reiniger table td form {margin:0!important;padding:0!important}\n");
		echo sprintf("</style>\n");
	}
}

?>

<?php

/**
	* Live Cryptocurrency Prices Plugin
	* Author: Skryptec
	* Website: https://skryptec.net/
	* Copyright: © 2014 - 2019 Skryptec
	*
	* Display live cryptocurrency prices with the use of Shoppy.gg API.
*/

if(!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook('index_start', 'cryptocurrency');

function cryptocurrency_info() {
	global $lang;

	$lang->load('cryptocurrency');

	return [
		'name' => $lang->cryptocurrency,
		'description' => $lang->cryptocurrency_desc,
		'website' => 'https://skryptec.net/',
		'author' => 'Skryptec',
		'authorsite' => 'https://skryptec.net/',
		'version' => '1.0',
		'compatibility' => '18*',
		'codename'      => 'skryptec_cryptocurrency',
	];
}

function cryptocurrency_install() {
	global $db, $lang;

	$lang->load('cryptocurrency');

	$settinggroup = [
		'name' => 'cryptocurrency',
		'title' => $lang->cryptocurrency_settings,
		'disporder' => 1,
		'isdefault' => 0,
		'description' => $lang->cryptocurrency_settings_desc
	];

	$gid = $db->insert_query('settinggroups', $settinggroup);

	$ticker = json_decode(file_get_contents('https://shoppy.gg/api/v1/public/ticker'), true);

	$settings = [];
	foreach($ticker['ticker'] as $currency) {
		$settings['cryptocurrency_display_' .  $currency['name']] = [
			'title' => 'Display ' . $currency['coin'] . '?',
			'description' => '',
			'optionscode' => 'yesno',
			'disporder' => 2,
			'value' => 1
		];
	}

	$settings['cryptocurrency_enabled'] = [
		'title' => $lang->cryptocurrency_settings_enabled,
		'description' => $lang->cryptocurrency_settings_enabled_desc,
		'optionscode' => 'yesno',
		'disporder' => 1,
		'value' => 1
	];

	foreach($settings as $name => $setting) {
		$setting['name'] = $name;
		$setting['gid'] = $gid;

		$db->insert_query('settings', $setting);
	}

	rebuild_settings();

	$templates = [
		'cryptocurrency' => '<div class="tborder">
		<div class="thead">
			<strong>
				{$lang->cryptocurrency}
			</strong>
		</div>
		<div style="display: flex; flex-wrap: wrap">
			{$currencies}
		</div>
		<div class="tcat" style="text-align: center">
			<strong>{$lang->cryptocurrency_updated}: {$ticker[\'last_updated\']}</strong>
		</div>
	</div>
	<br />',
		'cryptocurrency_currency' => '<div style="flex: 0 1 50% ;flex-grow: 1;">
		<div class="trow1" style="text-align: center; padding: .75rem">
			<strong>{$currency[\'coin\']}</strong>
			<br />
			&dollar;{$currency[\'value\'][\'USD\']} USD
		</div>
	</div>'
	];

	foreach($templates as $title => $data) {		
		$db->insert_query('templates', array (
			'title' 		=> $db->escape_string($title),
			'template' 		=> $db->escape_string($data),
			'version' 		=> 1,
			'sid' 			=> -1,
			'dateline' 		=> TIME_NOW
		));
	}
}

function cryptocurrency_is_installed() {
	global $db;

	return ($db->num_rows($db->simple_select('settinggroups', '*', "name = 'cryptocurrency'")) > 0) ? true : false;
}

function cryptocurrency_activate() {
	require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";

	find_replace_templatesets('index', "#" . preg_quote('{$forums}') . "#i", '<div style="display: flex; flex-wrap: wrap">
	<div style="flex: 3.5">
		{$forums}
	</div>
	<div style="flex: 1; padding-left: 1.5rem">
		{$cryptocurrency}
	</div>
</div>');
}

function cryptocurrency_deactivate() {
	require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";

	find_replace_templatesets('index', "#" . preg_quote('<div style="display: flex; flex-wrap: wrap">
	<div style="flex: 3.5">
		{$forums}
	</div>
	<div style="flex: 1; padding-left: 1.5rem">
		{$cryptocurrency}
	</div>
</div>') . "#i", '{$forums}');
}

function cryptocurrency_uninstall() {
	global $db;

	$gid = (int)$db->fetch_field($db->simple_select('settinggroups', 'gid', "name='cryptocurrency'"), 'gid');

	$db->delete_query('settinggroups', "name='cryptocurrency'");
	$db->delete_query('settings', "gid=$gid");

	rebuild_settings();

	$db->delete_query("templates", " title IN ('cryptocurrency', 'cryptocurrency_currency')");
}

function cryptocurrency() {
	global $mybb, $lang, $templates, $cryptocurrency;

	if($mybb->settings['cryptocurrency_enabled'] == 1) {
		$lang->load('cryptocurrency');

		$ticker = json_decode(file_get_contents('https://shoppy.gg/api/v1/public/ticker'), true);

		$dateTime = (new DateTime($ticker['last_updated']))->modify('-2 hours');
		$ticker['last_updated'] = my_date('relative', $dateTime->getTimeStamp());

		foreach($ticker['ticker'] as $currency) {
			if($mybb->settings['cryptocurrency_display_' . $currency['name']] == 1) {
				eval("\$currencies .= \"".$templates->get("cryptocurrency_currency")."\";");
			}
		}
	
		eval("\$cryptocurrency = \"".$templates->get("cryptocurrency")."\";");
	}
}

#!/usr/bin/env php
<?php
declare(strict_types=1);
require(__DIR__ . '/_cli.php');

const DATA_FORMAT = "%-7s | %-20s | %-5s | %-7s | %-25s | %-15s | %-10s | %-10s | %-10s | %-10s | %-10s | %-10s | %-5s | %-10s\n";

$parser = new CommandLineParser();

$parser->addOption('user', (new Option('user'))->typeOfArrayOfString(validateIsUser()));
$parser->addOption('header', (new Option('header'))->withValueNone());
$parser->addOption('json', (new Option('json'))->withValueNone());
$parser->addOption('humanReadable', (new Option('human-readable'))->withValueNone());

$options = $parser->parse(stdClass::class);

if (!empty($options->errors)) {
	fail('FreshRSS error: ' . array_shift($options->errors) . "\n" . $options->usage);
}

$users = $options->user ?? 0 ? $options->user : listUsers();

sort($users);

$formatJson = isset($options->json);
$jsonOutput = [];
if ($formatJson) {
	unset($options->header);
	unset($options->humanReadable);
}

if ($options->humanReadable ?? 0) {
	printf(
		DATA_FORMAT,
		'default',
		'user',
		'admin',
		'enabled',
		'last user activity',
		'space used',
		'categories',
		'feeds',
		'reads',
		'unreads',
		'favourites',
		'tags',
		'lang',
		'email'
	);
}

foreach ($users as $username) {
	$username = cliInitUser($username);

	$catDAO = FreshRSS_Factory::createCategoryDao($username);
	$feedDAO = FreshRSS_Factory::createFeedDao($username);
	$entryDAO = FreshRSS_Factory::createEntryDao($username);
	$tagDAO = FreshRSS_Factory::createTagDao($username);
	$databaseDAO = FreshRSS_Factory::createDatabaseDAO($username);

	$nbEntries = $entryDAO->countUnreadRead();
	$nbFavorites = $entryDAO->countUnreadReadFavorites();
	$feedList = $feedDAO->listFeedsIds();

	$data = array(
		'default' => $username === FreshRSS_Context::systemConf()->default_user ? '*' : '',
		'user' => $username,
		'admin' => FreshRSS_Context::userConf()->is_admin ? '*' : '',
		'enabled' => FreshRSS_Context::userConf()->enabled ? '*' : '',
		'last_user_activity' => FreshRSS_UserDAO::mtime($username),
		'database_size' => $databaseDAO->size(),
		'categories' => $catDAO->count(),
		'feeds' => count($feedList),
		'reads' => (int)$nbEntries['read'],
		'unreads' => (int)$nbEntries['unread'],
		'favourites' => (int)$nbFavorites['all'],
		'tags' => $tagDAO->count(),
		'lang' => FreshRSS_Context::userConf()->language,
		'mail_login' => FreshRSS_Context::userConf()->mail_login,
	);
	if ($options->humanReadable ?? 0) {	//Human format
		$data['last_user_activity'] = date('c', $data['last_user_activity']);
		$data['database_size'] = format_bytes($data['database_size']);
	}

	if ($formatJson) {
		$data['default'] = !empty($data['default']);
		$data['admin'] = !empty($data['admin']);
		$data['enabled'] = !empty($data['enabled']);
		$data['last_user_activity'] = gmdate('Y-m-d\TH:i:s\Z', (int)$data['last_user_activity']);
		$jsonOutput[] = $data;
	} else {
		vprintf(DATA_FORMAT, $data);
	}
}

if ($formatJson) {
	echo json_encode($jsonOutput), "\n";
}

done();

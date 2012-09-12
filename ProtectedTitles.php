<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension.' );
}

$dir = dirname( __FILE__ ) . '/';

$wgActions = array_merge( $wgActions, array(
	'protect' => true,
	'unprotect' => true,
) );

$wgAPIModules = array_merge( $wgAPIModules, array(
	'protect' => 'ApiProtect',
	'protectedtitles' => 'ApiQueryProtectedTitles',
) );

$wgAutoloadClasses = array_merge( $wgAutoloadClasses, array(
	'ApiProtect' => $dir . 'includes/api/ApiProtect.php',
	'ApiQueryAllpages_Restrictions' => $dir . 'includes/api/ApiQueryAllpages_Restrictions.php',
	'ApiQueryProtectedTitles' => $dir . '/includes/api/ApiQueryProtectedTitles.php',
	'ProtectedTitlesHooks' => $dir . 'ProtectedTitles.hooks.php',
	'ProtectAction' => $dir . 'includes/actions/ProtectAction.php',
	'ProtectionForm' => $dir . 'includes/ProtectionForm.php',
	'SpecialProtectedpages' => $dir . 'includes/specials/SpecialProtectedpages.php',
	'SpecialProtectedtitles' => $dir . 'includes/specials/SpecialProtectedtitles.php',
	'TitleRestrictions' => $dir . 'includes/TitleRestrictions.php',
	'UnprotectAction' => $dir . 'includes/actions/ProtectAction.php',
) );

$wgExtensionMessagesFiles['ProtectedTitles'] = $dir . 'ProtectedTitles.i18n.php';
$wgExtensionMessagesFiles['ProtectedTitlesMagic'] = $dir . 'ProtectedTitles.i18n.magic.php';

$wgHooks['APIGetAllowedParams'][] = 'ApiQueryAllpages_Restrictions::onQueryAllpagesAllowedParamsAlter';
$wgHooks['APIGetAllowedParams'][] = 'ApiQueryInfo_Restrictions::onAPIGetAllowedParams';
$wgHooks['APIGetParamDescription'][] = 'ApiQueryAllpages_Restrictions::onQueryAllpagesParamDescriptionsAlter';
$wgHooks['APIGetParamDescription'][] = 'ApiQueryInfo_Restrictions::onAPIGetParamDescription';
$wgHooks['APIGetPossibleErrors'][] = 'ApiQueryAllpages_Restrictions::onQueryAllpagesPossibleErrorsAlter';
$wgHooks['APIQueryAllpages::RunAlter'][] = 'ApiQueryAllpages_Restrictions::onQueryAllpagesRunAlter';
$wgHooks['APIQueryGeneratorExtraData'][] = 'ApiQueryInfo_Restrictions::onAPIQueryGeneratorExtraData';
$wgHooks['APIQueryInfo::PublicProps'][] = 'ApiQueryInfo_Restrictions::onPublicProps';
$wgHooks['AbortMove'][] = 'ProtectedTitlesHooks::onAbortMove';
$wgHooks['CheckActionPermissions'][] = 'ProtectedTitlesHooks::onCheckActionPermissions';
$wgHooks['EditPage::showRestrictions'][] = 'ProtectedTitlesHooks::onEditPageShowRestrictions';
$wgHooks['EditPage::showTextboxAlter'][] = 'ProtectedTitlesHooks::onEditPageShowTextboxAlter';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'ProtectedTitlesHooks::onLoadExtensionSchemaUpdates';
$wgHooks['LogLine'][] = 'ProtectedTitlesHooks::onLogLine';
$wgHooks['MakeGlobalVariablesScript'][] = 'ProtectedTitlesHooks::onMakeGlobalVariablesScript';
$wgHooks['SkinTemplateNavigation'][] = 'ProtectedTitlesHooks::onSkinTemplateNavigation';
$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 'ProtectedTitlesHooks::onSkinTemplateOutputPageBeforeExec';

$wgLogTypes[] = 'protect';
$wgLogNames['protect'] = 'protectlogpage';
$wgLogHeaders['protect'] = 'protectlogtext';
$wgLogActions = array_merge( $wgLogActions, array(
	'protect/protect' => 'protectedarticle',
	'protect/modify' => 'modifiedarticleprotection',
	'protect/unprotect' => 'unprotectedarticle',
	'protect/move_prot' => 'movedarticleprotection',
) );

$wgResourceModules['mediawiki.legacy.protect'] = array(
	'scripts' => 'modules/js/protect.js',
	'remoteBasePath' => $GLOBALS['wgStylePath'],
	'localBasePath' => $GLOBALS['wgStyleDirectory'],
	'dependencies' => array(
		'mediawiki.legacy.wikibits',
		'jquery.byteLimit',
	),
	'position' => 'top',
);

$wgSpecialPageGroups = array_merge( $wgSpecialPageGroups, array(
	'Protectedpages' => 'maintenance',
	'Protectedtitles' => 'maintenance',
) );
$wgSpecialPages = array_merge( $wgSpecialPages, array(
	'Protectedpages' => 'SpecialProtectedpages',
	'Protectedtitles' => 'SpecialProtectedtitles',
) );


//// new globals

/**
 * Set of available actions that can be restricted via action=protect
 * You probably shouldn't change this.
 * Translated through restriction-* messages.
 * Title::getRestrictionTypes() will remove restrictions that are not
 * applicable to a specific title (create and upload)
 */
$wgRestrictionTypes = array( 'create', 'edit', 'move', 'upload' );

/**
 * Rights which can be required for each protection level (via action=protect)
 *
 * You can add a new protection level that requires a specific
 * permission by manipulating this array. The ordering of elements
 * dictates the order on the protection form's lists.
 *
 *   - '' will be ignored (i.e. unprotected)
 *   - 'sysop' is quietly rewritten to 'protect' for backwards compatibility
 */
$wgRestrictionLevels = array( '', 'autoconfirmed', 'sysop' );

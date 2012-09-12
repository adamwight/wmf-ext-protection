<?php

$messages = array();

$messages['en'] = array(
	'vector-action-protect'          => 'Protect',
	'vector-action-unprotect'        => 'Change protection',
	'protect'           => 'Protect',
	'protect_change'    => 'change',
	'protectthispage'   => 'Protect this page',
	'unprotect'         => 'Change protection',
	'unprotectthispage' => 'Change protection of this page',
	'protectedpage'     => 'Protected page',
	'protectedpagetext'    => 'This page has been protected to prevent editing.',
	'protectedinterface'   => 'This page provides interface text for the software, and is protected to prevent abuse.',
	'cascadeprotected'     => 'This page has been protected from editing, because it is included in the following {{PLURAL:$1|page, which is|pages, which are}} protected with the "cascading" option turned on:
	$2',
	'namespaceprotected'   => "You do not have permission to edit pages in the '''$1''' namespace.",
	'customcssprotected'   => "You do not have permission to edit this CSS page, because it contains another user's personal settings.",
	'customjsprotected'    => "You do not have permission to edit this JavaScript page, because it contains another user's personal settings.",
	'ns-specialprotected'  => 'Special pages cannot be edited.',
	'titleprotected'       => 'This title has been protected from creation by [[User:$1|$1]].
	The reason given is "\'\'$2\'\'".',
	'protectedpagewarning'             => "'''Warning: This page has been protected so that only users with administrator privileges can edit it.'''
	The latest log entry is provided below for reference:",
	'semiprotectedpagewarning'         => "'''Note:''' This page has been protected so that only registered users can edit it.
	The latest log entry is provided below for reference:",
	'cascadeprotectedwarning'          => "'''Warning:''' This page has been protected so that only users with administrator privileges can edit it, because it is included in the following cascade-protected {{PLURAL:$1|page|pages}}:",
	'titleprotectedwarning'            => "'''Warning: This page has been protected so that [[Special:ListGroupRights|specific rights]] are needed to create it.'''
	The latest log entry is provided below for reference:",
	'template-protected'               => '(protected)',
	'template-semiprotected'           => '(semi-protected)',
	'right-autoconfirmed'         => 'Edit semi-protected pages',
	'right-protect'               => 'Change protection levels and edit protected pages',
	'right-editprotected'         => 'Edit protected pages (without cascading protection)',
	'action-protect'              => 'change protection levels for this page',
	'protectedpages'                  => 'Protected pages',
	'protectedpages-indef'            => 'Indefinite protections only',
	'protectedpages-summary'          => '', # do not translate or duplicate this message to other languages
	'protectedpages-cascade'          => 'Cascading protections only',
	'protectedpagestext'              => 'The following pages are protected from moving or editing',
	'protectedpagesempty'             => 'No pages are currently protected with these parameters.',
	'protectedtitles'                 => 'Protected titles',
	'protectedtitles-summary'         => '', # do not translate or duplicate this message to other languages
	'protectedtitlestext'             => 'The following titles are protected from creation',
	'protectedtitlesempty'            => 'No titles are currently protected with these parameters.',
	'protectlogpage'              => 'Protection log',
	'protectlogtext'              => 'Below is a list of changes to page protections.
	See the [[Special:ProtectedPages|protected pages list]] for the list of currently operational page protections.',
	'protectedarticle'            => 'protected "[[$1]]"',
	'modifiedarticleprotection'   => 'changed protection level for "[[$1]]"',
	'unprotectedarticle'          => 'removed protection from "[[$1]]"',
	'movedarticleprotection'      => 'moved protection settings from "[[$2]]" to "[[$1]]"',
	'protect-title'               => 'Change protection level for "$1"',
	'protect-title-notallowed'    => 'View protection level of "$1"',
	'prot_1movedto2'              => '[[$1]] moved to [[$2]]',
	'protect-badnamespace-title'  => 'Non-protectable namespace',
	'protect-badnamespace-text'   => 'Pages in this namespace cannot be protected.',
	'protect-legend'              => 'Confirm protection',
	'protectcomment'              => 'Reason:',
	'protectexpiry'               => 'Expires:',
	'protect_expiry_invalid'      => 'Expiry time is invalid.',
	'protect_expiry_old'          => 'Expiry time is in the past.',
	'protect-unchain-permissions' => 'Unlock further protect options',
	'protect-text'                => "Here you may view and change the protection level for the page '''$1'''.",
	'protect-locked-blocked'      => "You cannot change protection levels while blocked.
	Here are the current settings for the page '''$1''':",
	'protect-locked-dblock'       => "Protection levels cannot be changed due to an active database lock.
	Here are the current settings for the page '''$1''':",
	'protect-locked-access'       => "Your account does not have permission to change page protection levels.
	Here are the current settings for the page '''$1''':",
	'protect-cascadeon'           => "This page is currently protected because it is included in the following {{PLURAL:$1|page, which has|pages, which have}} cascading protection turned on.
	You can change this page's protection level, but it will not affect the cascading protection.",
	'protect-default'             => 'Allow all users',
	'protect-fallback'            => 'Require "$1" permission',
	'protect-level-autoconfirmed' => 'Block new and unregistered users',
	'protect-level-sysop'         => 'Administrators only',
	'protect-summary-cascade'     => 'cascading',
	'protect-expiring'            => 'expires $1 (UTC)',
	'protect-expiring-local'      => 'expires $1',
	'protect-expiry-indefinite'   => 'indefinite',
	'protect-cascade'             => 'Protect pages included in this page (cascading protection)',
	'protect-cantedit'            => 'You cannot change the protection levels of this page, because you do not have permission to edit it.',
	'protect-othertime'           => 'Other time:',
	'protect-othertime-op'        => 'other time',
	'protect-existing-expiry'     => 'Existing expiry time: $3, $2',
	'protect-otherreason'         => 'Other/additional reason:',
	'protect-otherreason-op'      => 'Other reason',
	'protect-dropdown'            => '*Common protection reasons
	** Excessive vandalism
	** Excessive spamming
	** Counter-productive edit warring
	** High traffic page',
	'protect-edit-reasonlist'     => 'Edit protection reasons',
	'protect-expiry-options'      => '1 hour:1 hour,1 day:1 day,1 week:1 week,2 weeks:2 weeks,1 month:1 month,3 months:3 months,6 months:6 months,1 year:1 year,infinite:infinite',
	'restriction-type'            => 'Permission:',
	'restriction-level'           => 'Restriction level:',
	'minimum-size'                => 'Min size',
	'maximum-size'                => 'Max size:',
	'pagesize'                    => '(bytes)',

	# Restrictions (nouns)
	'restriction-edit'   => 'Edit',
	'restriction-move'   => 'Move',
	'restriction-create' => 'Create',
	'restriction-upload' => 'Upload',

	# Restriction levels
	'restriction-level-sysop'         => 'fully protected',
	'restriction-level-autoconfirmed' => 'semi protected',
	'restriction-level-all'           => 'any level',

	'cantmove-titleprotected'      => 'You cannot move a page to this location, because the new title has been protected from creation',
	'protectedpagemovewarning'     => "'''Warning:''' This page has been protected so that only users with administrator privileges can move it.
	The latest log entry is provided below for reference:",
	'semiprotectedpagemovewarning' => "'''Note:''' This page has been protected so that only registered users can move it.
	The latest log entry is provided below for reference:",
	'accesskey-ca-protect'                  => '=', # do not translate or duplicate this message to other languages
	'accesskey-ca-unprotect'                => '=', # do not translate or duplicate this message to other languages
	'tooltip-ca-viewsource'               => 'This page is protected.
	You can view its source',
	'tooltip-ca-protect'                  => 'Protect this page',
	'tooltip-ca-unprotect'                => 'Change protection of this page',
);

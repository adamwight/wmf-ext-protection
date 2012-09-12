<?php

class TitleRestrictions {
	var $title;

	var $mRestrictions = array();     // /< Array of groups allowed to edit this article
	var $mOldRestrictions = false;
	var $mCascadeRestriction;         ///< Cascade restrictions on this page to included templates and images?
	var $mCascadingRestrictions;      // Caching the results of getCascadeProtectionSources
	var $mRestrictionsExpiry = array(); ///< When do the restrictions on this page expire?
	var $mHasCascadingRestrictions;   ///< Are cascading restrictions in effect on this page?
	var $mCascadeSources;             ///< Where are the cascading restrictions coming from on this page?
	var $mRestrictionsLoaded = false; ///< Boolean for initialisation on demand
	var $mTitleProtection;            ///< Cached value for getTitleProtection (create protection)

	function __construct( Title $title ) {
		$this->title = $title;
	}

	/**
	 * Update the article's restriction field, and leave a log entry.
	 * This works for protection both existing and non-existing pages.
	 *
	 * @param $limit Array: set of restriction keys
	 * @param $reason String
	 * @param &$cascade Integer. Set to false if cascading protection isn't allowed.
	 * @param $expiry Array: per restriction type expiration
	 * @param $user User The user updating the restrictions
	 * @return bool true on success
	 */
	public function doUpdateRestrictions( array $limit, array $expiry, &$cascade, $reason, User $user ) {
		global $wgContLang;

		if ( wfReadOnly() ) {
			return Status::newFatal( 'readonlytext', wfReadOnlyReason() );
		}

		$restrictionTypes = $this->getRestrictionTypes();

		$id = $this->title->getArticleID();

		if ( !$cascade ) {
			$cascade = false;
		}

		// Take this opportunity to purge out expired restrictions
		TitleRestrictions::purgeExpiredRestrictions();

		# @todo FIXME: Same limitations as described in ProtectionForm.php (line 37);
		# we expect a single selection, but the schema allows otherwise.
		$isProtected = false;
		$protect = false;
		$changed = false;

		$dbw = wfGetDB( DB_MASTER );

		foreach ( $restrictionTypes as $action ) {
			if ( !isset( $expiry[$action] ) ) {
				$expiry[$action] = $dbw->getInfinity();
			}
			if ( !isset( $limit[$action] ) ) {
				$limit[$action] = '';
			} elseif ( $limit[$action] != '' ) {
				$protect = true;
			}

			# Get current restrictions on $action
			$current = implode( '', $this->getRestrictions( $action ) );
			if ( $current != '' ) {
				$isProtected = true;
			}

			if ( $limit[$action] != $current ) {
				$changed = true;
			} elseif ( $limit[$action] != '' ) {
				# Only check expiry change if the action is actually being
				# protected, since expiry does nothing on an not-protected
				# action.
				if ( $this->getRestrictionExpiry( $action ) != $expiry[$action] ) {
					$changed = true;
				}
			}
		}

		if ( !$changed && $protect && $this->areRestrictionsCascading() != $cascade ) {
			$changed = true;
		}

		# If nothing's changed, do nothing
		if ( !$changed ) {
			return Status::newGood();
		}

		if ( !$protect ) { # No protection at all means unprotection
			$revCommentMsg = 'unprotectedarticle';
			$logAction = 'unprotect';
		} elseif ( $isProtected ) {
			$revCommentMsg = 'modifiedarticleprotection';
			$logAction = 'modify';
		} else {
			$revCommentMsg = 'protectedarticle';
			$logAction = 'protect';
		}

		$encodedExpiry = array();
		$protectDescription = '';
		foreach ( $limit as $action => $restrictions ) {
			$encodedExpiry[$action] = $dbw->encodeExpiry( $expiry[$action] );
			if ( $restrictions != '' ) {
				$protectDescription .= $wgContLang->getDirMark() . "[$action=$restrictions] (";
				if ( $encodedExpiry[$action] != 'infinity' ) {
					$protectDescription .= wfMsgForContent( 'protect-expiring',
						$wgContLang->timeanddate( $expiry[$action], false, false ) ,
						$wgContLang->date( $expiry[$action], false, false ) ,
						$wgContLang->time( $expiry[$action], false, false ) );
				} else {
					$protectDescription .= wfMsgForContent( 'protect-expiry-indefinite' );
				}

				$protectDescription .= ') ';
			}
		}
		$protectDescription = trim( $protectDescription );

		if ( $id ) { # Protection of existing page
			if ( !wfRunHooks( 'ArticleProtect', array( &$this->title, &$user, $limit, $reason ) ) ) {
				return Status::newGood();
			}

			# Only restrictions with the 'protect' right can cascade...
			# Otherwise, people who cannot normally protect can "protect" pages via transclusion
			$editrestriction = isset( $limit['edit'] ) ? array( $limit['edit'] ) : $this->getRestrictions( 'edit' );

			# The schema allows multiple restrictions
			if ( !in_array( 'protect', $editrestriction ) && !in_array( 'sysop', $editrestriction ) ) {
				$cascade = false;
			}

			# Update restrictions table
			foreach ( $limit as $action => $restrictions ) {
				if ( $restrictions != '' ) {
					$dbw->replace( 'page_restrictions', array( array( 'pr_page', 'pr_type' ) ),
						array( 'pr_page' => $id,
							'pr_type' => $action,
							'pr_level' => $restrictions,
							'pr_cascade' => ( $cascade && $action == 'edit' ) ? 1 : 0,
							'pr_expiry' => $encodedExpiry[$action]
						),
						__METHOD__
					);
				} else {
					$dbw->delete( 'page_restrictions', array( 'pr_page' => $id,
						'pr_type' => $action ), __METHOD__ );
				}
			}

			# Prepare a null revision to be added to the history
			$editComment = $wgContLang->ucfirst( wfMsgForContent( $revCommentMsg, $this->title->getPrefixedText() ) );
			if ( $reason ) {
				$editComment .= ": $reason";
			}
			if ( $protectDescription ) {
				$editComment .= " ($protectDescription)";
			}
			if ( $cascade ) {
				$editComment .= ' [' . wfMsgForContent( 'protect-summary-cascade' ) . ']';
			}

			# Insert a null revision
			$nullRevision = Revision::newNullRevision( $dbw, $id, $editComment, true );
			$nullRevId = $nullRevision->insertOn( $dbw );

			$latest = $this->title->getLatestRevID();
			# Update page record
			$dbw->update( 'page',
				array( /* SET */
					'page_touched' => $dbw->timestamp(),
					'page_restrictions' => '',
					'page_latest' => $nullRevId
				), array( /* WHERE */
					'page_id' => $id
				), __METHOD__
			);

			wfRunHooks( 'NewRevisionFromEditComplete', array( $this->title, $nullRevision, $latest, $user ) );
			wfRunHooks( 'ArticleProtectComplete', array( &$this->title, &$user, $limit, $reason ) );
		} else { # Protection of non-existing page (also known as "title protection")
			# Cascade protection is meaningless in this case
			$cascade = false;

			if ( $limit['create'] != '' ) {
				$dbw->replace( 'protected_titles',
					array( array( 'pt_namespace', 'pt_title' ) ),
					array(
						'pt_namespace' => $this->title->getNamespace(),
						'pt_title' => $this->title->getDBkey(),
						'pt_create_perm' => $limit['create'],
						'pt_timestamp' => $dbw->encodeExpiry( wfTimestampNow() ),
						'pt_expiry' => $encodedExpiry['create'],
						'pt_user' => $user->getId(),
						'pt_reason' => $reason,
					), __METHOD__
				);
			} else {
				$dbw->delete( 'protected_titles',
					array(
						'pt_namespace' => $this->title->getNamespace(),
						'pt_title' => $this->title->getDBkey()
					), __METHOD__
				);
			}
		}

		$this->flushRestrictions();

		if ( $logAction == 'unprotect' ) {
			$logParams = array();
		} else {
			$logParams = array( $protectDescription, $cascade ? 'cascade' : '' );
		}

		# Update the protection log
		$log = new LogPage( 'protect' );
		$log->addEntry( $logAction, $this->title, trim( $reason ), $logParams, $user );

		return Status::newGood();
	}


 	/**
	 * Update the title protection status
	 *
	 * @deprecated in 1.19; will be removed in 1.20. Use WikiPage::doUpdateRestrictions() instead.
	 * @param $create_perm String Permission required for creation
	 * @param $reason String Reason for protection
	 * @param $expiry String Expiry timestamp
	 * @return boolean true
	 */
	public function updateTitleProtection( $create_perm, $reason, $expiry ) {
		wfDeprecated( __METHOD__, '1.19' );

		global $wgUser;

		$limit = array( 'create' => $create_perm );
		$expiry = array( 'create' => $expiry );

		$status = $this->doUpdateRestrictions( $limit, $expiry, false, $reason, $wgUser );

		return $status->isOK();
	}

	/**
	 * Remove any title protection due to page existing
	 */
	public function deleteTitleProtection() {
		$dbw = wfGetDB( DB_MASTER );

		$dbw->delete(
			'protected_titles',
			array( 'pt_namespace' => $this->title->getNamespace(), 'pt_title' => $this->title->getDBkey() ),
			__METHOD__
		);
		$this->mTitleProtection = false;
	}

	/**
	 * Is this page "semi-protected" - the *only* protection is autoconfirm?
	 *
	 * @param $action String Action to check (default: edit)
	 * @return Bool
	 */
	public function isSemiProtected( $action = 'edit' ) {
		if ( $this->title->exists() ) {
			$restrictions = $this->getRestrictions( $action );
			if ( count( $restrictions ) > 0 ) {
				foreach ( $restrictions as $restriction ) {
					if ( strtolower( $restriction ) != 'autoconfirmed' ) {
						return false;
					}
				}
			} else {
				# Not protected
				return false;
			}
			return true;
		} else {
			# If it doesn't exist, it can't be protected
			return false;
		}
	}

	/**
	 * Does the title correspond to a protected article?
	 *
	 * @param $action String the action the page is protected from,
	 * by default checks all actions.
	 * @return Bool
	 */
	public function isProtected( $action = '' ) {
		global $wgRestrictionLevels;

		$restrictionTypes = $this->getRestrictionTypes();

		# Special pages have inherent protection
		if( $this->title->isSpecialPage() ) {
			return true;
		}

		# Check regular protection levels
		foreach ( $restrictionTypes as $type ) {
			if ( $action == $type || $action == '' ) {
				$r = $this->getRestrictions( $type );
				foreach ( $wgRestrictionLevels as $level ) {
					if ( in_array( $level, $r ) && $level != '' ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Determines if $user is unable to edit this page because it has been protected
	 * by $wgNamespaceProtection.
	 *
	 * @param $user User object to check permissions
	 * @return Bool
	 */
	public function isNamespaceProtected( User $user ) {
		global $wgNamespaceProtection;

		if ( isset( $wgNamespaceProtection[$this->title->mNamespace] ) ) {
			foreach ( (array)$wgNamespaceProtection[$this->title->mNamespace] as $right ) {
				if ( $right != '' && !$user->isAllowed( $right ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Cascading protection: Return true if cascading restrictions apply to this page, false if not.
	 *
	 * @return Bool If the page is subject to cascading restrictions.
	 */
	public function isCascadeProtected() {
		list( $sources, /* $restrictions */ ) = $this->getCascadeProtectionSources( false );
		return ( $sources > 0 );
	}

	/**
	 * Cascading protection: Get the source of any cascading restrictions on this page.
	 *
	 * @param $getPages Bool Whether or not to retrieve the actual pages
	 *        that the restrictions have come from.
	 * @return Mixed Array of Title objects of the pages from which cascading restrictions
	 *     have come, false for none, or true if such restrictions exist, but $getPages
	 *     was not set.  The restriction array is an array of each type, each of which
	 *     contains a array of unique groups.
	 */
	public function getCascadeProtectionSources( $getPages = true ) {
		global $wgContLang;
		$pagerestrictions = array();

		if ( isset( $this->mCascadeSources ) && $getPages ) {
			return array( $this->mCascadeSources, $this->mCascadingRestrictions );
		} elseif ( isset( $this->mHasCascadingRestrictions ) && !$getPages ) {
			return array( $this->mHasCascadingRestrictions, $pagerestrictions );
		}

		wfProfileIn( __METHOD__ );

		$dbr = wfGetDB( DB_SLAVE );

		if ( $this->title->getNamespace() == NS_FILE ) {
			$tables = array( 'imagelinks', 'page_restrictions' );
			$where_clauses = array(
				'il_to' => $this->title->getDBkey(),
				'il_from=pr_page',
				'pr_cascade' => 1
			);
		} else {
			$tables = array( 'templatelinks', 'page_restrictions' );
			$where_clauses = array(
				'tl_namespace' => $this->title->getNamespace(),
				'tl_title' => $this->title->getDBkey(),
				'tl_from=pr_page',
				'pr_cascade' => 1
			);
		}

		if ( $getPages ) {
			$cols = array( 'pr_page', 'page_namespace', 'page_title',
						   'pr_expiry', 'pr_type', 'pr_level' );
			$where_clauses[] = 'page_id=pr_page';
			$tables[] = 'page';
		} else {
			$cols = array( 'pr_expiry' );
		}

		$res = $dbr->select( $tables, $cols, $where_clauses, __METHOD__ );

		$sources = $getPages ? array() : false;
		$now = wfTimestampNow();
		$purgeExpired = false;

		foreach ( $res as $row ) {
			$expiry = $wgContLang->formatExpiry( $row->pr_expiry, TS_MW );
			if ( $expiry > $now ) {
				if ( $getPages ) {
					$page_id = $row->pr_page;
					$page_ns = $row->page_namespace;
					$page_title = $row->page_title;
					$sources[$page_id] = Title::makeTitle( $page_ns, $page_title );
					# Add groups needed for each restriction type if its not already there
					# Make sure this restriction type still exists

					if ( !isset( $pagerestrictions[$row->pr_type] ) ) {
						$pagerestrictions[$row->pr_type] = array();
					}

					if ( isset( $pagerestrictions[$row->pr_type] ) &&
						 !in_array( $row->pr_level, $pagerestrictions[$row->pr_type] ) ) {
						$pagerestrictions[$row->pr_type][] = $row->pr_level;
					}
				} else {
					$sources = true;
				}
			} else {
				// Trigger lazy purge of expired restrictions from the db
				$purgeExpired = true;
			}
		}
		if ( $purgeExpired ) {
			TitleRestrictions::purgeExpiredRestrictions();
		}

		if ( $getPages ) {
			$this->mCascadeSources = $sources;
			$this->mCascadingRestrictions = $pagerestrictions;
		} else {
			$this->mHasCascadingRestrictions = $sources;
		}

		wfProfileOut( __METHOD__ );
		return array( $sources, $pagerestrictions );
	}

	/**
	 * Accessor/initialisation for mRestrictions
	 *
	 * @param $action String action that permission needs to be checked for
	 * @return Array of Strings the array of groups allowed to edit this article
	 */
	public function getRestrictions( $action ) {
		if ( !$this->mRestrictionsLoaded ) {
			$this->loadRestrictions();
		}
		return isset( $this->mRestrictions[$action] )
				? $this->mRestrictions[$action]
				: array();
	}

	/**
	 * Get the expiry time for the restriction against a given action
	 *
	 * @return String|Bool 14-char timestamp, or 'infinity' if the page is protected forever
	 * 	or not protected at all, or false if the action is not recognised.
	 */
	public function getRestrictionExpiry( $action ) {
		if ( !$this->mRestrictionsLoaded ) {
			$this->loadRestrictions();
		}
		return isset( $this->mRestrictionsExpiry[$action] ) ? $this->mRestrictionsExpiry[$action] : false;
	}

	/**
	 * Returns cascading restrictions for the current article
	 *
	 * @return Boolean
	 */
	function areRestrictionsCascading() {
		if ( !$this->mRestrictionsLoaded ) {
			$this->loadRestrictions();
		}

		return $this->mCascadeRestriction;
	}

	/**
	 * Loads a string into mRestrictions array
	 *
	 * @param $res Resource restrictions as an SQL result.
	 * @param $oldFashionedRestrictions String comma-separated list of page
	 *        restrictions from page table (pre 1.10)
	 */
	private function loadRestrictionsFromResultWrapper( $res, $oldFashionedRestrictions = null ) {
		$rows = array();

		foreach ( $res as $row ) {
			$rows[] = $row;
		}

		$this->loadRestrictionsFromRows( $rows, $oldFashionedRestrictions );
	}

	/**
	 * Compiles list of active page restrictions from both page table (pre 1.10)
	 * and page_restrictions table for this existing page.
	 * Public for usage by LiquidThreads.
	 *
	 * @param $rows array of db result objects
	 * @param $oldFashionedRestrictions string comma-separated list of page
	 *        restrictions from page table (pre 1.10)
	 */
	public function loadRestrictionsFromRows( $rows, $oldFashionedRestrictions = null ) {
		global $wgContLang;
		$dbr = wfGetDB( DB_SLAVE );

		$restrictionTypes = $this->getRestrictionTypes();

		foreach ( $restrictionTypes as $type ) {
			$this->mRestrictions[$type] = array();
			$this->mRestrictionsExpiry[$type] = $wgContLang->formatExpiry( '', TS_MW );
		}

		$this->mCascadeRestriction = false;

		# Backwards-compatibility: also load the restrictions from the page record (old format).

		if ( $oldFashionedRestrictions === null ) {
			$oldFashionedRestrictions = $dbr->selectField( 'page', 'page_restrictions',
				array( 'page_id' => $this->title->getArticleId() ), __METHOD__ );
		}

		if ( $oldFashionedRestrictions != '' ) {

			foreach ( explode( ':', trim( $oldFashionedRestrictions ) ) as $restrict ) {
				$temp = explode( '=', trim( $restrict ) );
				if ( count( $temp ) == 1 ) {
					// old old format should be treated as edit/move restriction
					$this->mRestrictions['edit'] = explode( ',', trim( $temp[0] ) );
					$this->mRestrictions['move'] = explode( ',', trim( $temp[0] ) );
				} else {
					$this->mRestrictions[$temp[0]] = explode( ',', trim( $temp[1] ) );
				}
			}

			$this->mOldRestrictions = true;

		}

		if ( count( $rows ) ) {
			# Current system - load second to make them override.
			$now = wfTimestampNow();
			$purgeExpired = false;

			# Cycle through all the restrictions.
			foreach ( $rows as $row ) {

				// Don't take care of restrictions types that aren't allowed
				if ( !in_array( $row->pr_type, $restrictionTypes ) )
					continue;

				// This code should be refactored, now that it's being used more generally,
				// But I don't really see any harm in leaving it in Block for now -werdna
				$expiry = $wgContLang->formatExpiry( $row->pr_expiry, TS_MW );

				// Only apply the restrictions if they haven't expired!
				if ( !$expiry || $expiry > $now ) {
					$this->mRestrictionsExpiry[$row->pr_type] = $expiry;
					$this->mRestrictions[$row->pr_type] = explode( ',', trim( $row->pr_level ) );

					$this->mCascadeRestriction |= $row->pr_cascade;
				} else {
					// Trigger a lazy purge of expired restrictions
					$purgeExpired = true;
				}
			}

			if ( $purgeExpired ) {
				TitleRestrictions::purgeExpiredRestrictions();
			}
		}

		$this->mRestrictionsLoaded = true;
	}

	/**
	 * Load restrictions from the page_restrictions table
	 *
	 * @param $oldFashionedRestrictions String comma-separated list of page
	 *        restrictions from page table (pre 1.10)
	 */
	public function loadRestrictions( $oldFashionedRestrictions = null ) {
		global $wgContLang;
		if ( !$this->mRestrictionsLoaded ) {
			if ( $this->title->exists() ) {
				$dbr = wfGetDB( DB_SLAVE );

				$res = $dbr->select(
					'page_restrictions',
					'*',
					array( 'pr_page' => $this->title->getArticleId() ),
					__METHOD__
				);

				$this->loadRestrictionsFromResultWrapper( $res, $oldFashionedRestrictions );
			} else {
				$title_protection = $this->getTitleProtection();

				if ( $title_protection ) {
					$now = wfTimestampNow();
					$expiry = $wgContLang->formatExpiry( $title_protection['pt_expiry'], TS_MW );

					if ( !$expiry || $expiry > $now ) {
						// Apply the restrictions
						$this->mRestrictionsExpiry['create'] = $expiry;
						$this->mRestrictions['create'] = explode( ',', trim( $title_protection['pt_create_perm'] ) );
					} else { // Get rid of the old restrictions
						TitleRestrictions::purgeExpiredRestrictions();
						$this->mTitleProtection = false;
					}
				} else {
					$this->mRestrictionsExpiry['create'] = $wgContLang->formatExpiry( '', TS_MW );
				}
				$this->mRestrictionsLoaded = true;
			}
		}
	}

	/**
	 * Flush the protection cache in this object and force reload from the database.
	 * This is used when updating protection from WikiPage::doUpdateRestrictions().
	 */
	public function flushRestrictions() {
		$this->mRestrictionsLoaded = false;
		$this->mTitleProtection = null;
	}

	/**
	 * Purge expired restrictions from the page_restrictions table
	 */
	static function purgeExpiredRestrictions() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'page_restrictions',
			array( 'pr_expiry < ' . $dbw->addQuotes( $dbw->timestamp() ) ),
			__METHOD__
		);

		$dbw->delete(
			'protected_titles',
			array( 'pt_expiry < ' . $dbw->addQuotes( $dbw->timestamp() ) ),
			__METHOD__
		);
	}

	/**
	 * Check against page_restrictions table requirements on this
	 * page. The user must possess all required rights for this
	 * action.
	 *
	 * @param $action String the action to check
	 * @param $user User user to check
	 * @param $errors Array list of current errors
	 * @param $doExpensiveQueries Boolean whether or not to perform expensive queries
	 * @param $short Boolean short circuit on first error
	 *
	 * @return Array list of errors
	 */
	function checkPageRestrictions( $action, $user, &$errors, $doExpensiveQueries, $short ) {
		foreach ( $this->getRestrictions( $action ) as $right ) {
			// Backwards compatibility, rewrite sysop -> protect
			if ( $right == 'sysop' ) {
				$right = 'protect';
			}
			if ( $right != '' && !$user->isAllowed( $right ) ) {
				// Users with 'editprotected' permission can edit protected pages
				// without cascading option turned on.
				if ( $action != 'edit' || !$user->isAllowed( 'editprotected' )
					|| $this->mCascadeRestriction )
				{
					$errors[] = array( 'protectedpagetext', $right );
				}
			}
		}
	}

	/**
	 * Check restrictions on cascading pages.
	 *
	 * @param $action String the action to check
	 * @param $user User to check
	 * @param $errors Array list of current errors
	 * @param $doExpensiveQueries Boolean whether or not to perform expensive queries
	 * @param $short Boolean short circuit on first error
	 *
	 * @return Array list of errors
	 */
	function checkCascadingSourcesRestrictions( $action, $user, &$errors, $doExpensiveQueries, $short ) {
		if ( $doExpensiveQueries && !$this->title->isCssJsSubpage() ) {
			# We /could/ use the protection level on the source page, but it's
			# fairly ugly as we have to establish a precedence hierarchy for pages
			# included by multiple cascade-protected pages. So just restrict
			# it to people with 'protect' permission, as they could remove the
			# protection anyway.
			list( $cascadingSources, $restrictions ) = $this->getCascadeProtectionSources();
			# Cascading protection depends on more than this page...
			# Several cascading protected pages may include this page...
			# Check each cascading level
			# This is only for protection restrictions, not for all actions
			if ( isset( $restrictions[$action] ) ) {
				foreach ( $restrictions[$action] as $right ) {
					$right = ( $right == 'sysop' ) ? 'protect' : $right;
					if ( $right != '' && !$user->isAllowed( $right ) ) {
						$pages = '';
						foreach ( $cascadingSources as $page )
							$pages .= '* [[:' . $page->getPrefixedText() . "]]\n";
						$errors[] = array( 'cascadeprotected', count( $cascadingSources ), $pages );
					}
				}
			}
		}
	}

	/**
	 * Is this title subject to title protection?
	 * Title protection is the one applied against creation of such title.
	 *
	 * @return Mixed An associative array representing any existent title
	 *   protection, or false if there's none.
	 */
	function getTitleProtection() {
		// Can't protect pages in special namespaces
		if ( $this->title->getNamespace() < 0 ) {
			return false;
		}

		// Can't protect pages that exist.
		if ( $this->title->exists() ) {
			return false;
		}

		if ( !isset( $this->mTitleProtection ) ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select( 'protected_titles', '*',
				array( 'pt_namespace' => $this->title->getNamespace(), 'pt_title' => $this->title->getDBkey() ),
				__METHOD__ );

			// fetchRow returns false if there are no rows.
			$this->mTitleProtection = $dbr->fetchRow( $res );
		}
		return $this->mTitleProtection;
	}

	/**
	 * Get a filtered list of all restriction types supported by this wiki.
	 * @param bool $exists True to get all restriction types that apply to
	 * titles that do exist, False for all restriction types that apply to
	 * titles that do not exist
	 * @return array
	 */
	public static function getFilteredRestrictionTypes( $exists = true ) {
		global $wgRestrictionTypes;
		$types = $wgRestrictionTypes;
		if ( $exists ) {
			# Remove the create restriction for existing titles
			$types = array_diff( $types, array( 'create' ) );
		} else {
			# Only the create and upload restrictions apply to non-existing titles
			$types = array_intersect( $types, array( 'create', 'upload' ) );
		}
		return $types;
	}

	/**
	 * Returns restriction types for the current Title
	 *
	 * @return array applicable restriction types
	 */
	public function getRestrictionTypes() {
		if ( $this->title->isSpecialPage() ) {
			return array();
		}

		$types = self::getFilteredRestrictionTypes( $this->title->exists() );

		if ( $this->title->getNamespace() != NS_FILE ) {
			# Remove the upload restriction for non-file titles
			$types = array_diff( $types, array( 'upload' ) );
		}

		wfRunHooks( 'TitleGetRestrictionTypes', array( $this->title, &$types ) );

		wfDebug( __METHOD__ . ': applicable restrictions to [[' .
			$this->title->getPrefixedText() . ']] are {' . implode( ',', $types ) . "}\n" );

		return $types;
	}

}
#
#diff --git a/includes/Title.php b/includes/Title.php
#index 3e7f489..6dd9600 100644
#--- a/includes/Title.php
#+++ b/includes/Title.php
#@@ -1731,14 +1722,6 @@ class Title {
# 			$errors[] = array( 'ns-specialprotected' );
# 		}
# 
#-		# Check $wgNamespaceProtection for restricted namespaces
#-		if ( $this->isNamespaceProtected( $user ) ) {
#-			$ns = $this->mNamespace == NS_MAIN ?
#-				wfMsg( 'nstab-main' ) : $this->getNsText();
#-			$errors[] = $this->mNamespace == NS_MEDIAWIKI ?
#-				array( 'protectedinterface' ) : array( 'namespaceprotected',  $ns );
#-		}
#-
# 		return $errors;
# 	}
# 
#@@ -1770,78 +1753,6 @@ class Title {
# 	}
# 
#-	/**
# 	 * Check action permissions not already checked in checkQuickPermissions
# 	 *
# 	 * @param $action String the action to check
#@@ -1855,24 +1766,7 @@ class Title {
# 	private function checkActionPermissions( $action, $user, $errors, $doExpensiveQueries, $short ) {
# 		global $wgDeleteRevisionsLimit, $wgLang;
# 
#-		if ( $action == 'protect' ) {
#-			if ( count( $this->getUserPermissionsErrorsInternal( 'edit', $user, $doExpensiveQueries, true ) ) ) {
#-				// If they can't edit, they shouldn't protect.
#-				$errors[] = array( 'protect-cantedit' );
#-			}
#-		} elseif ( $action == 'create' ) {
#-			$title_protection = $this->getTitleProtection();
#-			if( $title_protection ) {
#-				if( $title_protection['pt_create_perm'] == 'sysop' ) {
#-					$title_protection['pt_create_perm'] = 'protect'; // B/C
#-				}
#-				if( $title_protection['pt_create_perm'] == '' ||
#-					!$user->isAllowed( $title_protection['pt_create_perm'] ) ) 
#-				{
#-					$errors[] = array( 'titleprotected', User::whoIs( $title_protection['pt_user'] ), $title_protection['pt_reason'] );
#-				}
#-			}
#-		} elseif ( $action == 'move' ) {
#+		if ( $action == 'move' ) {
# 			// Check for immobile pages
# 			if ( !MWNamespace::isMovable( $this->mNamespace ) ) {
# 				// Specific message for this case
#@@ -2109,8 +2003,6 @@ class Title {
# 				'checkPermissionHooks',
# 				'checkSpecialsAndNSPermissions',
# 				'checkCSSandJSPermissions',
#-				'checkPageRestrictions',
#-				'checkCascadingSourcesRestrictions',
# 				'checkActionPermissions',
# 				'checkUserBlock'
# 			);
#@@ -2201,36 +2093,6 @@ class Title {
# 	}
# 
#-	/**
# 	 * Does this have subpages?  (Warning, usually requires an extra DB query.)
# 	 *
# 	 * @return Bool
#@@ -2954,12 +2816,6 @@ class Title {
# 			if ( !$this->isValidMoveTarget( $nt ) ) {
# 				$errors[] = array( 'articleexists' );
# 			}
#-		} else {
#-			$tp = $nt->getTitleProtection();
#-			$right = ( $tp['pt_create_perm'] == 'sysop' ) ? 'protect' : $tp['pt_create_perm'];
#-			if ( $tp and !$wgUser->isAllowed( $right ) ) {
#-				$errors[] = array( 'cantmove-titleprotected' );
#-			}
# 		}
# 		if ( empty( $errors ) ) {
# 			return true;
#@@ -3044,7 +2900,6 @@ class Title {
# 
# 		$dbw->begin(); # If $file was a LocalFile, its transaction would have closed our own.
# 		$pageid = $this->getArticleID( self::GAID_FOR_UPDATE );
#-		$protected = $this->isProtected();
# 
# 		// Do the actual move
# 		$err = $this->moveToInternal( $nt, $reason, $createRedirect );
#@@ -3079,31 +2934,6 @@ class Title {
# 
# 		$redirid = $this->getArticleID();
# 
#-		if ( $protected ) {
#-			# Protect the redirect title as the title used to be...
#-			$dbw->insertSelect( 'page_restrictions', 'page_restrictions',
#-				array(
#-					'pr_page'    => $redirid,
#-					'pr_type'    => 'pr_type',
#-					'pr_level'   => 'pr_level',
#-					'pr_cascade' => 'pr_cascade',
#-					'pr_user'    => 'pr_user',
#-					'pr_expiry'  => 'pr_expiry'
#-				),
#-				array( 'pr_page' => $pageid ),
#-				__METHOD__,
#-				array( 'IGNORE' )
#-			);
#-			# Update the protection log
#-			$log = new LogPage( 'protect' );
#-			$comment = wfMsgForContent( 'prot_1movedto2', $this->getPrefixedText(), $nt->getPrefixedText() );
#-			if ( $reason ) {
#-				$comment .= wfMsgForContent( 'colon-separator' ) . $reason;
#-			}
#-			// @todo FIXME: $params?
#-			$log->addEntry( 'move_prot', $nt, $comment, array( $this->getPrefixedText() ) );
#-		}
#-
# 		# Update watchlists
# 		$oldnamespace = $this->getNamespace() & ~1;
# 		$newnamespace = $nt->getNamespace() & ~1;
#}

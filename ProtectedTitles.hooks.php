<?php

class ProtectedTitlesHooks {
	static function onAbortMove( $title, $new_title, $user, $err, $reason ) {
		# Check $wgNamespaceProtection for restricted namespaces
		if ( $title_restrictions->isNamespaceProtected( $user ) ) {
			$ns = $title->getNamespace() == NS_MAIN ?
				wfMessage( 'nstab-main' )->setContext( $title->getContext() )->text() : $title->getNsText();
			$err = $title->getNamespace() == NS_MEDIAWIKI ?
				wfMessage( 'protectedinterface' )->setContext( $title->getContext() )->text() : wfMessage( 'namespaceprotected',  $ns )->setContext( $title->getContext() )->text();
		}

		if ( $errors ) {
			//XXX what is this flattened thing?
			$err = implode( ' ', $errors );
			return false;
		}

		return true;
	}

	static function onCheckActionPermissions( $title, $action, $user, &$errors, $doExpensiveQueries, $short ) {
		$title_restrictions = new TitleRestrictions( $title );

		$title_restrictions->checkPageRestrictions( $action, $user, $errors, $doExpensiveQueries, $short );
		$title_restrictions->checkCascadingSourcesRestrictions( $action, $user, $errors, $doExpensiveQueries, $short );

		if ( $action == 'protect' ) {
			if ( !$title->userCan( 'edit', $user, $doExpensiveQueries ) ) {
				// If they can't edit, they shouldn't protect.
				$errors[] = array( 'protect-cantedit' );
			}
		} elseif ( $action == 'create' ) {
			$title_protection = $title_restrictions->getTitleProtection();
			if( $title_protection ) {
				if( $title_protection['pt_create_perm'] == 'sysop' ) {
					$title_protection['pt_create_perm'] = 'protect'; // B/C
				}
				if( $title_protection['pt_create_perm'] == '' ||
					!$user->isAllowed( $title_protection['pt_create_perm'] ) ) 
				{
					$errors[] = array( 'titleprotected', User::whoIs( $title_protection['pt_user'] ), $title_protection['pt_reason'] );
				}
			}
		}
		return true;
	}

	static function onEditPageShowRestrictions( $editpage ) {
		$title = $editpage->getTitle();
		$title_restrictions = new TitleRestrictions( $title );
		if ( $title->getNamespace() != NS_MEDIAWIKI && $title_restrictions->isProtected( 'edit' ) ) {
			# Is the title semi-protected?
			if ( $title_restrictions->isSemiProtected() ) {
				$noticeMsg = 'semiprotectedpagewarning';
			} else {
				# Then it must be protected based on static groups (regular)
				$noticeMsg = 'protectedpagewarning';
			}
			LogEventsList::showLogExtract( $wgOut, 'protect', $title, '',
				array( 'lim' => 1, 'msgKey' => array( $noticeMsg ) ) );
		}
		if ( $title_restrictions->isCascadeProtected() ) {
			# Is this page under cascading protection from some source pages?
			list($cascadeSources, /* $restrictions */) = $title_restrictions->getCascadeProtectionSources();
			$notice = "<div class='mw-cascadeprotectedwarning'>\n$1\n";
			$cascadeSourcesCount = count( $cascadeSources );
			if ( $cascadeSourcesCount > 0 ) {
				# Explain, and list the titles responsible
				foreach( $cascadeSources as $page ) {
					$notice .= '* [[:' . $page->getPrefixedText() . "]]\n";
				}
			}
			$notice .= '</div>';
			$wgOut->wrapWikiMsg( $notice, array( 'cascadeprotectedwarning', $cascadeSourcesCount ) );
		}
		if ( !$title->exists() && $title_restrictions->getRestrictions( 'create' ) ) {
			LogEventsList::showLogExtract( $wgOut, 'protect', $title, '',
				array(  'lim' => 1,
					'showIfEmpty' => false,
					'msgKey' => array( 'titleprotectedwarning' ),
					'wrap' => "<div class=\"mw-titleprotectedwarning\">\n$1</div>" ) );
		}
//XXX else
		if ( $title->getNamespace() != NS_MEDIAWIKI && $title_restrictions->isProtected( 'edit' ) ) {
			# Is the title semi-protected?
			if ( $title_restrictions->isSemiProtected() ) {
				$classes[] = 'mw-textarea-sprotected';
			} else {
				# Then it must be protected based on static groups (regular)
				$classes[] = 'mw-textarea-protected';
			}
			# Is the title cascade-protected?
			if ( $title_restrictions->isCascadeProtected() ) {
				$classes[] = 'mw-textarea-cprotected';
			}
		}

		return true;
	}

	static function onEditPageShowTextboxAlter( $editpage, &$attribs ) {
		//XXX
		//if ( $editpage->wasDeletedSinceLastEdit() && $editpage->formtype == 'save' ) {
		//	return true;
		//}
		$title = $editpage->getTitle();
		$title_restrictions = new TitleRestrictions( $title );
		$classes = array(); // Textarea CSS
		if ( $title->getNamespace() != NS_MEDIAWIKI && $title_restrictions->isProtected( 'edit' ) ) {
			# Is the title semi-protected?
			if ( $title_restrictions->isSemiProtected() ) {
				$classes[] = 'mw-textarea-sprotected';
			} else {
				# Then it must be protected based on static groups (regular)
				$classes[] = 'mw-textarea-protected';
			}
			# Is the title cascade-protected?
			if ( $title_restrictions->isCascadeProtected() ) {
				$classes[] = 'mw-textarea-cprotected';
			}
		}

		if ( count( $classes ) ) {
			if ( isset( $attribs['class'] ) ) {
				$classes[] = $attribs['class'];
			}
			$attribs['class'] = implode( ' ', $classes );
		}

		return true;
	}

#	static function onLoadExtensionSchemaUpdates( $updater = null ) {
#		$base = dirname( __FILE__ );
#		if ( $updater === null ) {
#			global $wgDBtype, $wgExtNewTables;
#			if ( in_array( $wgDBtype, array( 'mysql', 'postgres', 'mssql', 'ibm_db2' ) ) ) {
#				$wgExtNewTables[] = array( 'protected_titles', "$base/sql/ProtectedTitles.$wgDBtype.sql" );
#			}
#			// XXX else throw
#		} else {
#			switch ( $updater->getDB()->getType() ) {
#			case 'mysql':
#				$schema_updates = array(
#					array( 'addField', 'page_restrictions', 'pr_id', 'patch-page_restrictions_sortkey.sql' ),
#					array( 'addTable', 'protected_titles', 'patch-protected_titles.sql' ),
#					array( 'checkBin', 'protected_titles', 'pt_title', 'patch-pt_title-encoding.sql', ),
#					//TODO remove page.page_restrictions + check migration
#				);
#				break;
#			case 'postgres':
#				$schema_updates = array(
#					array( 'addSequence', 'page_restrictions_pr_id_seq' ),
#					array( 'renameSequence', 'pr_id_val', 'page_restrictions_pr_id_seq' ),
#					array( 'addTable', 'page_restrictions', 'patch-page_restrictions.sql' ),
#					array( 'addTable', 'protected_titles', 'patch-protected_titles.sql' ),
#					array( 'addPgField', 'page_restrictions', 'pr_id', "INTEGER NOT NULL UNIQUE DEFAULT nextval('page_restrictions_pr_id_seq')" ),
#					array( 'changeFkeyDeferrable', 'page_restrictions', 'pr_page', 'page(page_id) ON DELETE CASCADE' ),
#					array( 'changeFkeyDeferrable', 'protected_titles', 'pt_user', 'mwuser(user_id) ON DELETE SET NULL' ),
#				);
#				break;
#			default:
#				// XXX throw
#				$schema_updates = array();
#			}
#			foreach ( $schema_updates as $change ) {
#				$updater->addExtensionUpdate( $change );
#			}
#		}
#		return true;
#	}
#
#	//MYSQL
#	/**
#	 * Adding page_restrictions table, obsoleting page.page_restrictions.
#	 * Migrating old restrictions to new table
#	 * -- Andrew Garrett, January 2007.
#	 */
#	protected function doRestrictionsUpdate() {
#		if ( $this->db->tableExists( 'page_restrictions', __METHOD__ ) ) {
#			$this->output( "...page_restrictions table already exists.\n" );
#			return;
#		}
#
#		$this->output( "Creating page_restrictions table..." );
#		$this->applyPatch( 'patch-page_restrictions.sql' );
#		$this->applyPatch( 'patch-page_restrictions_sortkey.sql' );
#		$this->output( "done.\n" );
#
#		$this->output( "Migrating old restrictions to new table...\n" );
#		$task = $this->maintenance->runChild( 'UpdateRestrictions' );
#		$task->execute();
#	}
#
#	//ORACLE
# 	/**
#	 * Fixed wrong PK, UK definition
#	 */
#	protected function doPageRestrictionsPKUKFix() {
#		$this->output( "Altering PAGE_RESTRICTIONS keys ... " );
#
#		$meta = $this->db->query( 'SELECT column_name FROM all_cons_columns WHERE owner = \''.strtoupper($this->db->getDBname()).'\' AND constraint_name = \'MW_PAGE_RESTRICTIONS_PK\' AND rownum = 1' );
#		$row = $meta->fetchRow();
#		if ( $row['column_name'] == 'PR_ID' ) {
#			$this->output( "seems to be up to date.\n" );
#			return;
#		}
#
#		$this->applyPatch( 'patch-page_restrictions_pkuk_fix.sql', false );
#		$this->output( "ok\n" );
#	}
#

	static function onLogLine( $type, $action, $title, $params, &$comment, &$revert, $timestamp ) {
		global $wgUser;
		// Show change protection link
		if ( $type == 'protect' && in_array( $action, array( 'modify', 'protect', 'unprotect' ) ) ) {
			$revert .= ' (' .
				Linker::link( $title,
					wfMsgExt( 'hist', array( 'escapenoentities' ) ),
					array(),
					array(
						'action' => 'history',
						'offset' => $timestamp
					)
				);
			if ( $wgUser->isAllowed( 'protect' ) ) {
				$revert .= wfMsgExt( 'pipe-separator', array( 'escapenoentities' ) ) .
					Linker::link( $title,
						wfMsgExt( 'protect_change', array( 'escapenoentities' ) ),
						array(), 
						array( 'action' => 'protect' ),
						'known' );
			}
			$revert .= ')';
		}
		return true;
	}

	static function onMakeGlobalVariablesScript( $vars, $page ) {
		$title_restrictions = new TitleRestrictions( $page->getTitle() );
		foreach ( $title_restrictions->getRestrictionTypes() as $type ) {
		    $vars['wgRestriction' . ucfirst( $type )] = $title_restrictions->getRestrictions( $type );
		}   
		return true;
	}

	static function onSkinTemplateNavigation( Skin $skin, array &$links ) {
		$title = $skin->getRelevantTitle(); // Display tabs for the relevant title rather than always the title itself
		$onPage = $title->equals($skin->getTitle());
		$skname = $skin->getSkinName();
		$title_restrictions = new TitleRestrictions( $title );
		$action = $skin->getRequest()->getVal( 'action', 'view' );

		if ( $title->getNamespace() !== NS_MEDIAWIKI && $title->quickUserCan( 'protect', $skin->getUser() ) ) {
			$mode = $title_restrictions->isProtected() ? 'unprotect' : 'protect';
			$links['actions'][$mode] = array(
				'class' => ( $onPage && $action == $mode ) ? 'selected' : false,
				'text' => wfMessageFallback( "$skname-action-$mode", $mode )->setContext( $skin->getContext() )->text(),
				'href' => $title->getLocalURL( "action=$mode" )
			);
		}
		return true;
	}

	static function onSkinTemplateOutputPageBeforeExec( $skin, &$template ) {
		$title_restrictions = new TitleRestrictions( $skin->getTitle() );
		$template->set( 'protect', count( $title_restrictions->isProtected() ) ? 'unprotect' : 'protect' );
		return true;
	}
}

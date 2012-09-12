<?php

class ApiQueryAllpages_Restrictions {
	static function onQueryAllpagesAllowedParamsAlter( $api, &$allowed ) {
		if ( $api->getModuleName() != 'allpages' ) {
			return true;
		}

		global $wgRestrictionLevels;

		$allowed['prtype'] = array(
			ApiBase::PARAM_TYPE => TitleRestrictions::getFilteredRestrictionTypes( true ),
			ApiBase::PARAM_ISMULTI => true
		);
		$allowed['prlevel'] = array(
			ApiBase::PARAM_TYPE => $wgRestrictionLevels,
			ApiBase::PARAM_ISMULTI => true
		);
		$allowed['prfiltercascade'] = array(
			ApiBase::PARAM_DFLT => 'all',
			ApiBase::PARAM_TYPE => array(
				'cascading',
				'noncascading',
				'all'
			),
		);
		$allowed['prexpiry'] = array(
			ApiBase::PARAM_TYPE => array(
				'indefinite',
				'definite',
				'all'
			),
			ApiBase::PARAM_DFLT => 'all'
		);

		return true;
	}

	static function onQueryAllpagesParamDescriptionsAlter( $api, &$descriptions ) {
		if ( $api->getModuleName() != 'allpages' ) {
			return true;
		}
		$p = $api->getModulePrefix();

		$descriptions['prtype'] = 'Limit to protected pages only';
		$descriptions['prlevel'] = "The protection level (must be used with {$p}prtype= parameter)";
		$descriptions['prfiltercascade'] = "Filter protections based on cascadingness (ignored when {$p}prtype isn't set)";
		$descriptions['prexpiry'] = array(
			'Which protection expiry to filter the page on',
			' indefinite - Get only pages with indefinite protection expiry',
			' definite - Get only pages with a definite (specific) protection expiry',
			' all - Get pages with any protections expiry'
		);

		return true;
	}

	static function onQueryAllpagesPossibleErrorsAlter( $api, &$errors ) {
		$errors[] = array( 'code' => 'params', 'info' => 'prlevel may not be used without prtype' );

		return true;
	}

	static function onQueryAllpagesRunAlter( $api, $db, $params ) {
		// Page protection filtering
		if ( count( $params['prtype'] ) || $params['prexpiry'] != 'all' ) {
			$api->addTables( 'page_restrictions' );
			$api->addWhere( 'page_id=pr_page' );
			$api->addWhere( 'pr_expiry>' . $db->addQuotes( $db->timestamp() ) );

			if ( count( $params['prtype'] ) ) {
				$api->addWhereFld( 'pr_type', $params['prtype'] );

				if ( isset( $params['prlevel'] ) ) {
					// Remove the empty string and '*' from the prlevel array
					$prlevel = array_diff( $params['prlevel'], array( '', '*' ) );

					if ( count( $prlevel ) ) {
						$api->addWhereFld( 'pr_level', $prlevel );
					}
				}
				if ( $params['prfiltercascade'] == 'cascading' ) {
					$api->addWhereFld( 'pr_cascade', 1 );
				} elseif ( $params['prfiltercascade'] == 'noncascading' ) {
					$api->addWhereFld( 'pr_cascade', 0 );
				}

				$api->addOption( 'DISTINCT' );
			}
			$forceNameTitleIndex = false;

			if ( $params['prexpiry'] == 'indefinite' ) {
				$api->addWhere( "pr_expiry = {$db->addQuotes( $db->getInfinity() )} OR pr_expiry IS NULL" );
			} elseif ( $params['prexpiry'] == 'definite' ) {
				$api->addWhere( "pr_expiry != {$db->addQuotes( $db->getInfinity() )}" );
			}

		} elseif ( isset( $params['prlevel'] ) ) {
			$api->dieUsage( 'prlevel may not be used without prtype', 'params' );
		}

		return true;
	}
}

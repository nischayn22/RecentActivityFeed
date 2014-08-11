<?php
/**
 * Implements Special:RecentActivityFeed
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

/**
 * A special page that lists last changes made to the wiki by certain users
 *
 * @ingroup SpecialPage
 */
class SpecialRecentActivityFeed extends ChangesListSpecialPage {

        protected $defaultParams = array();
	protected $customDesc;
	protected $additionalConds = array();

	public function __construct( $name = 'RecentActivityFeed', $restriction = '' ) {
		parent::__construct( $name, $restriction );
	}

	public function setAdditionalConds($conds){
	       $this->additionalConds = $conds;
	}

	public function setParams($params){
	       $this->defaultParams = $params;
	}

	public function setCustomDescription($description){
	       $this->customDesc = $description;	       
	}

	public function getPageTitle($subpage = false){
	       if ($this->mName != 'RecentActivityFeed')
	        return Title::newFromText( $this->mName );

		return parent::getPageTitle();
	}

	/**
	 * Returns the name that goes in the \<h1\> in the special page itself, and
	 * also the name that will be listed in Special:Specialpages
	 *
	 * Derived classes can override this, but usually it is easier to keep the
	 * default behavior.
	 *
	 * @return string
	 */
	function getDescription() {
		if( $this->customDesc ) {
		    return $this->customDesc;
		}
		return $this->msg( strtolower( $this->mName ) )->text();
	}

	/**
	 * Get a FormOptions object containing the default options
	 *
	 * @return FormOptions
	 */
	public function getDefaultOptions() {
		$opts = parent::getDefaultOptions();
		$user = $this->getUser();

		# Not adding these result in an error
		$opts->add( 'days', $user->getIntOption( 'rcdays' ) );
		$opts->add( 'limit', $user->getIntOption( 'rclimit' ) );
		$opts->add( 'from', '' );
		$opts->add( 'hideminor', false );
		$opts->add( 'hidebots', false );
		$opts->add( 'hideanons', false );
		$opts->add( 'hideliu', false );
		$opts->add( 'hidepatrolled', false );
		$opts->add( 'hidemyself', false );

		$opts->add( 'namespace', '', FormOptions::INTNULL );
		$opts->add( 'order', 'article' );

		return $opts;
	}

	/**
	 * Return the text to be displayed above the changes
	 *
	 * @param FormOptions $opts
	 * @return string XHTML
	 */
	public function doHeader( $opts ) {
		global $wgScript;

		$this->setTopText( $opts );

		$defaults = $opts->getAllValues();
		$nondefaults = $opts->getChangedValues();

		$panel = array();
		$panel[] = self::makeLegend( $this->getContext() );
		$panel[] = $this->optionsPanel( $defaults, $nondefaults );

		$panelString = implode( "\n", $panel );

		$this->getOutput()->addHTML(
			Xml::fieldset(
				$this->msg( 'recentactivityfeed-legend' )->text(),
				$panelString,
				array( 'class' => 'rcoptions' )
			)
		);

		$this->setBottomText( $opts );
	}

	/**
	 * Return an array of conditions depending of options set in $opts
	 *
	 * @param FormOptions $opts
	 * @return array
	 */
	public function buildMainQueryConds( FormOptions $opts ) {
		$dbr = $this->getDB();
		$conds = parent::buildMainQueryConds( $opts );

		// Calculate cutoff
		$cutoff_unixtime = time() - ( $opts['days'] * 86400 );
		$cutoff_unixtime = $cutoff_unixtime - ( $cutoff_unixtime % 86400 );
		$cutoff = $dbr->timestamp( $cutoff_unixtime );

		$fromValid = preg_match( '/^[0-9]{14}$/', $opts['from'] );
		if ( $fromValid && $opts['from'] > wfTimestamp( TS_MW, $cutoff ) ) {
			$cutoff = $dbr->timestamp( $opts['from'] );
		} else {
			$opts->reset( 'from' );
		}

		$conds[] = 'rc_timestamp >= ' . $dbr->addQuotes( $cutoff );

		return $conds;
	}

	public function doMainQuery( $conds, $opts ) {
	       $conds +=  $this->additionalConds;
		$tables = array( 'recentchanges' );
		$fields = RecentChange::selectFields();
		$query_options = array( 'ORDER BY' => 'rc_timestamp DESC' );
		$join_conds = array();

		ChangeTags::modifyDisplayQuery(
			$tables,
			$fields,
			$conds,
			$join_conds,
			$query_options,
			''
		);

		if ( !wfRunHooks( 'ChangesListSpecialPageQuery',
			array( $this->getName(), &$tables, &$fields, &$conds, &$query_options, &$join_conds, $opts ) )
		) {
			return false;
		}

		$dbr = $this->getDB();

		return $dbr->select(
			$tables,
			$fields,
			$conds,
			__METHOD__,
			$query_options,
			$join_conds
		);
	}


	/**
	 * Build and output the actual changes list.
	 *
	 * @param array $rows Database rows
	 * @param FormOptions $opts
	 */
	public function outputChangesList( $rows, $opts ) {
		$list = ChangesList::newFromContext( $this->getContext() );
		$list->initChangesListRows( $rows );

		$limit = $opts['limit'];
		$order = $opts['order'];

		$rclistOutput = $list->beginRecentChangesList();
		$ordered_rc = array();
		foreach ( $rows as $obj ) {
			if ( $limit == 0 ) {
				break;
			}
			$rc = RecentChange::newFromRow( $obj );
			if ($order == 'article'){
			   $ordered_rc[$rc->getTitle()->getFullText()][] = $rc;
			} else {
			   $ordered_rc[$rc->getPerformer()->getName()][] = $rc;
			}
		}

		foreach($ordered_rc as $key => $rc_list) {
			$heading = '';
			if ($order == 'user'){
			   $heading = Linker::userLink($rc_list[0]->mAttribs['rc_user'], $key);
			   $heading .= ' (';
			   $heading .= Linker::userTalkLink($rc_list[0]->mAttribs['rc_user'], $key);
			   $heading .= ' | ';
			   $heading .= Linker::link( SpecialPage::getTitleFor( 'Contributions', $key), 'contribs' );
			   $heading .= ') ';
			} else {
			  $list->insertArticleLink( $heading, $rc_list[0] );
			}
			$rclistOutput .= "<h4>$heading</h4>";
			foreach($rc_list as $rc) {
				$changeLine = $this->makeChangesLine( $list, $rc, $order );
 				if ( $changeLine !== false ) {
				   $rclistOutput .= "<div style=\"margin-left:20px;\">$changeLine </div>";
				   --$limit;
				}
			}
		}
		$rclistOutput .= $list->endRecentChangesList();

		if ( $rows->numRows() === 0 ) {
			$this->getOutput()->addHtml(
				'<div class="mw-changeslist-empty">' .
				$this->msg( 'recentchanges-noresult' )->parse() .
				'</div>'
			);
		} else {
			$this->getOutput()->addHTML( $rclistOutput );
		}
	}


	/**
	 * Creates the options panel.
	 *
	 * @param array $defaults
	 * @param array $nondefaults
	 * @return string
	 */
	function optionsPanel( $defaults, $nondefaults ) {
		global $wgScript;
		global $wgRCLinkLimits, $wgRCLinkDays;

		$options = $nondefaults + $defaults;
		$note = '';
		$msg = $this->msg( 'rclegend' );
		if ( !$msg->isDisabled() ) {
			$note .= '<div class="mw-rclegend">' . $msg->parse() . "</div>\n";
		}

		$lang = $this->getLanguage();
		$user = $this->getUser();
		if ( $options['from'] ) {
			$note .= $this->msg( 'rcnotefrom' )->numParams( $options['limit'] )->params(
				$lang->userTimeAndDate( $options['from'], $user ),
				$lang->userDate( $options['from'], $user ),
				$lang->userTime( $options['from'], $user ) )->parse() . '<br />';
		}

		# Sort data for display and make sure it's unique after we've added user data.
		$linkLimits = $wgRCLinkLimits;
		$linkLimits[] = $options['limit'];
		sort( $linkLimits );
		$linkLimits = array_unique( $linkLimits );

		$linkDays = $wgRCLinkDays;
		$linkDays[] = $options['days'];
		sort( $linkDays );
		$linkDays = array_unique( $linkDays );

		// limit links
		$cl = array();
		foreach ( $linkLimits as $value ) {
			$cl[] = $this->makeOptionsLink( $lang->formatNum( $value ),
				array( 'limit' => $value ), $nondefaults, $value == $options['limit'] );
		}
		$cl = $lang->pipeList( $cl );

		// day links, reset 'from' to none
		$dl = array();
		foreach ( $linkDays as $value ) {
			$dl[] = $this->makeOptionsLink( $lang->formatNum( $value ),
				array( 'days' => $value, 'from' => '' ), $nondefaults, $value == $options['days'] );
		}
		$dl = $lang->pipeList( $dl );

		// show/hide links
		$filters = array(
			'hideminor' => 'rcshowhideminor',
			'hidebots' => 'rcshowhidebots',
			'hideanons' => 'rcshowhideanons',
			'Hideliu' => 'rcshowhideliu',
			'hidepatrolled' => 'rcshowhidepatr',
			'hidemyself' => 'rcshowhidemine'
		);

		$showhide = array( 'show', 'hide' );

		foreach ( $this->getCustomFilters() as $key => $params ) {
			$filters[$key] = $params['msg'];
		}
		// Disable some if needed
		if ( !$user->useRCPatrol() ) {
			unset( $filters['hidepatrolled'] );
		}

		// show from this onward link
		$timestamp = wfTimestampNow();
		$now = $lang->userTimeAndDate( $timestamp, $user );
		$timenow = $lang->userTime( $timestamp, $user );
		$datenow = $lang->userDate( $timestamp, $user );
		// hack to use the same msg and save i18n efforts
		$links = array();
		$rclinks = $this->msg( 'rclinks' )->rawParams( $cl, $dl, $lang->pipeList( $links ) )
			->parse();

		$orderTypes = array('article', 'user');
		foreach ( $orderTypes as $value ) {
			$ol[] = $this->makeOptionsLink( 'by ' . $value,
				array( 'order' => $value ), $nondefaults, $value == $options['order'] );
		}

		$orderlinks = implode(' | ', $ol);
		$orderlinks = "Order activity $orderlinks";

		$form .= Html::namespaceSelector(
			array(
				'selected' => $options['namespace'],
				'all' => '',
				'label' => $this->msg( 'namespace' )->text()
			), array(
				'name' => 'namespace',
				'id' => 'namespace',
				'class' => 'namespaceselector',
			)
		) . '&#160;';
		$form .= Xml::submitButton( $this->msg( 'allpagessubmit' )->text() ) . "</p>\n";
		$form .= Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() );
		$hiddenFields = $nondefaults;
		unset( $hiddenFields['namespace'] );
		foreach ( $hiddenFields as $key => $value ) {
			$form .= Html::hidden( $key, $value );
		}
		foreach ( $this->defaultParams as $key => $value ) {
			$form .= Html::hidden( $key, $value );
		}
		$form = Xml::tags( 'form', array( 'action' => $wgScript ), $form );

		return "{$note}$rclinks $orderlinks <hr /> <br /> $form";
	}

	/**
	 * Makes change an option link which carries all the other options
	 *
	 * @param string $title Title
	 * @param array $override Options to override
	 * @param array $options Current options
	 * @param bool $active Whether to show the link in bold
	 * @return string
	 */
	function makeOptionsLink( $title, $override, $options, $active = false ) {
		$params = $override + $options + $this->defaultParams;

		// Bug 36524: false values have be converted to "0" otherwise
		// wfArrayToCgi() will omit it them.
		foreach ( $params as &$value ) {
			if ( $value === false ) {
				$value = '0';
			}
		}
		unset( $value );

		$text = htmlspecialchars( $title );
		if ( $active ) {
			$text = '<strong>' . $text . '</strong>';
		}

		return Linker::linkKnown( $this->getPageTitle(), $text, array(), $params );
	}

	function makeChangesLine($list, $rc, $order){
		$s = '';
		$date = $list->getLanguage()->userDate( $rc_timestamp, $list->getUser() );
		if ( $rc->mAttribs['rc_log_type'] ) {
			$logtitle = SpecialPage::getTitleFor( 'Log', $rc->mAttribs['rc_log_type'] );
			$list->insertLog( $s, $logtitle, $rc->mAttribs['rc_log_type'] );
		// Log entries (old format) or log targets, and special pages
		} elseif ( $rc->mAttribs['rc_namespace'] == NS_SPECIAL ) {
			list( $name, $subpage ) = SpecialPageFactory::resolveAlias( $rc->mAttribs['rc_title'] );
			if ( $name == 'Log' ) {
				$list->insertLog( $s, $rc->getTitle(), $subpage );
			}
		// Regular entries
		} else {
			$list->insertDiffHist( $s, $rc, $unpatrolled );
			# M, N, b and ! (minor, new, bot and unpatrolled)
			$s .= $list->recentChangesFlags(
				array(
					'newpage' => $rc->mAttribs['rc_type'] == RC_NEW,
					'minor' => $rc->mAttribs['rc_minor'],
					'unpatrolled' => $unpatrolled,
					'bot' => $rc->mAttribs['rc_bot']
				),
				''
			);
		}
		$s .= $date;
		$list->insertTimestamp( $s, $rc );
		$cd = $list->formatCharacterDifference( $rc );
		if ( $cd !== '' ) {
			$s .= $cd . '  <span class="mw-changeslist-separator">. .</span> ';
		}
		if ($order != 'article' &&  $rc->mAttribs['rc_type'] != RC_LOG )
		   $list->insertArticleLink( $s, $rc, $unpatrolled, $watched );
		if ( $rc->mAttribs['rc_type'] == RC_LOG ) {
			$s .= $list->insertLogEntry( $rc );
		} else {
			# User tool links
			if ($order != 'user')
			   $list->insertUserRelatedLinks( $s, $rc );
			# LTR/RTL direction mark
			$s .= $list->getLanguage()->getDirMark();
			$s .= $list->insertComment( $rc );
		}
		return "$s <br/>";
	}

}
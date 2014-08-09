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
		$panel[] = '<hr />';

		$panelString = implode( "\n", $panel );

		$this->getOutput()->addHTML(
			Xml::fieldset(
				$this->msg( 'recentchanges-legend' )->text(),
				$panelString,
				array( 'class' => 'rcoptions' )
			)
		);

		$this->setBottomText( $opts );
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

		$rclistOutput = $list->beginRecentChangesList();
		foreach ( $rows as $obj ) {
			if ( $limit == 0 ) {
				break;
			}
			$rc = RecentChange::newFromRow( $obj );
			$changeLine = $list->recentChangesLine( $rc );
			if ( $changeLine !== false ) {
				$rclistOutput .= $changeLine;
				--$limit;
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
	 * Creates the options panel.
	 *
	 * @param array $defaults
	 * @param array $nondefaults
	 * @return string
	 */
	function optionsPanel( $defaults, $nondefaults ) {
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
			'hideliu' => 'rcshowhideliu',
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
		return "{$note}$rclinks<br />";
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

}
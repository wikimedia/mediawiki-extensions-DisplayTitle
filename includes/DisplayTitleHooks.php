<?php

use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;

class DisplayTitleHooks {

	/**
	 * Implements ParserFirstCallInit hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @since 1.0
	 * @param Parser &$parser the Parser object
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'getdisplaytitle',
			'DisplayTitleHooks::getdisplaytitleParserFunction' );
	}

	/**
	 * Handle #getdisplaytitle parser function.
	 *
	 * @since 1.0
	 * @param Parser $parser the Parser object
	 * @param string $pagename the name of the page
	 * @return string the displaytitle of the page; defaults to pagename if
	 * displaytitle is not set
	 */
	public static function getdisplaytitleParserFunction( Parser $parser,
		$pagename ) {
		$title = Title::newFromText( $pagename );
		if ( !is_null( $title ) ) {
			self::getDisplayTitle( $title, $pagename );
		}
		return $pagename;
	}

	/**
	 * Implements PersonalUrls hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/PersonalUrls
	 * Handle links. Implements HtmlPageLinkRendererBegin hook of LinkRenderer class.
	 *
	 * @since 1.5
	 * @param array &$personal_urls the array of URLs set up so far
	 * @param Title $title the Title object of the current article
	 * @param SkinTemplate $skin SkinTemplate object providing context
	 */
	public static function onPersonalUrls( array &$personal_urls, Title $title,
		SkinTemplate $skin ) {
		if ( $skin->getUser()->isLoggedIn() &&
			isset( $personal_urls['userpage'] ) ) {
			$pagename = $personal_urls['userpage']['text'];
			$title = $skin->getUser()->getUserPage();
			self::getDisplayTitle( $title, $pagename );
			$personal_urls['userpage']['text'] = $pagename;
		}
	}

	/**
	 * Implements HtmlPageLinkRendererBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/HtmlPageLinkRendererBegin
	 * Handle links to other pages.
	 *
	 * @since 1.4
	 * @param LinkRenderer $linkRenderer the LinkRenderer object
	 * @param LinkTarget $target the LinkTarget that the link is pointing to
	 * @param string|HtmlArmor &$text the contents that the <a> tag should have
	 * @param array &$extraAttribs the HTML attributes that the <a> tag should have
	 * @param string &$query the query string to add to the generated URL
	 * @param string &$ret the value to return if the hook returns false
	 */
	public static function onHtmlPageLinkRendererBegin(
		LinkRenderer $linkRenderer, LinkTarget $target, &$text, &$extraAttribs,
		&$query, &$ret ) {
		$title = Title::newFromLinkTarget( $target );
		self::handleLink( $title, $text, true );
	}

	/**
	 * Implements SelfLinkBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/SelfLinkBegin
	 * Handle self links.
	 *
	 * @since 1.3
	 * @param Title $nt the Title object of the page
	 * @param string &$html the HTML of the link text
	 * @param string &$trail Text after link
	 * @param string &$prefix Text before link
	 * @param string &$ret the value to return if the hook returns false
	 */
	public static function onSelfLinkBegin( Title $nt, &$html, &$trail,
		&$prefix, &$ret ) {
		self::handleLink( $nt, $html, false );
	}

	/**
	 * Helper function. Determines link text for self-links and standard links.
	 *
	 * Handle links. If a link is customized by a user (e. g. [[Target|Text]])
	 * it should remain intact. Let us assume a link is not customized if its
	 * html is the prefixed or (to support Semantic MediaWiki queries)
	 * non-prefixed title of the target page.
	 *
	 * @since 1.3
	 * @param Title $target the Title object that the link is pointing to
	 * @param string|HtmlArmor &$html the HTML of the link text
	 * @param boolean $wrap whether to wrap result in HtmlArmor
	 */
	private static function handleLink( Title $target, &$html, $wrap ) {
		if ( isset( $html ) ) {
			$title = null;
			$text = null;
			if ( is_string( $html ) ) {
				$text = $html;
			} elseif ( is_int( $html ) ) {
				$text = (string)$html;
			} elseif ( $html instanceof HtmlArmor ) {
				$text = HtmlArmor::getHtml( $html );
			}
			if ( !is_null( $text ) ) {
				$title = Title::newFromText( $text );
				if ( !is_null( $title ) ) {
					if ( $target->getSubjectNsText() === '' ) {
						if ( $text === $target->getText() ) {
							self::getDisplayTitle( $target, $html, $wrap );
						}
					} else {
						if ( $title->getText() === $target->getText() &&
							$title->getSubjectNsText() === $target->getSubjectNsText() ) {
							self::getDisplayTitle( $target, $html, $wrap );
						}
					}
				}
			}
		} else {
			self::getDisplayTitle( $target, $html, $wrap );
		}
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Display subtitle if requested
	 *
	 * @since 1.0
	 * @param OutputPage &$out the OutputPage object
	 * @param Skin &$sk the Skin object
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$sk ) {
		if ( $GLOBALS['wgDisplayTitleHideSubtitle'] ) {
			return;
		}
		$title = $out->getTitle();
		if ( !$title->isTalkPage() ) {
			$found = self::getDisplayTitle( $title, $displaytitle );
		} elseif ( $title->getSubjectPage()->exists() ) {
			$found = self::getDisplayTitle( $title->getSubjectPage(), $displaytitle );
		} else {
			$found = false;
		}
		if ( $found ) {
			$subtitle = $title->getPrefixedText();
			$old_subtitle = $out->getSubtitle();
			if ( $old_subtitle !== '' ) {
				$subtitle .= ' / ' . $old_subtitle;
			}
			$out->setSubtitle( $subtitle );
		}
	}

	/**
	 * Implements ParserBeforeStrip hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserBeforeStrip
	 * Handle talk page title.
	 *
	 * @since 1.0
	 * @param Parser &$parser the Parser object
	 * @param string &$text the text
	 * @param StripState &$strip_state the strip state
	 */
	public static function onParserBeforeStrip( Parser &$parser, &$text,
		&$strip_state ) {
		$title = $parser->getTitle();
		if ( $title->isTalkPage() && $title->getSubjectPage()->exists() ) {
			$found = self::getDisplayTitle( $title->getSubjectPage(), $displaytitle );
			if ( $found ) {
				$displaytitle = wfMessage( 'displaytitle-talkpagetitle',
					$displaytitle )->plain();
				$parser->mOutput->setTitleText( $displaytitle );
			}
		}
	}

	/**
	 * Implements ScribuntoExternalLibraries hook.
	 * See https://www.mediawiki.org/wiki/Extension:Scribunto#Other_pages
	 * Handle Scribunto integration
	 *
	 * @since 1.2
	 * @param string $engine engine in use
	 * @param array &$extraLibraries list of registered libraries
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.displaytitle'] = 'DisplayTitleLuaLibrary';
		}
	}

	/**
	 * Get displaytitle page property text.
	 *
	 * @since 1.0
	 * @param Title $title the Title object for the page
	 * @param string &$displaytitle to return the display title, if set
	 * @param boolean $wrap whether to wrap result in HtmlArmor
	 * @return boolean true if the page has a displaytitle page property that is
	 * different from the prefixed page name, false otherwise
	 */
	private static function getDisplayTitle( Title $title, &$displaytitle,
		$wrap = false ) {
		$title = $title->createFragmentTarget( '' );
		$originalPageName = $title->getPrefixedText();
		$wikipage = new WikiPage( $title );
		$redirect = false;
		$redirectTarget = $wikipage->getRedirectTarget();
		if ( !is_null( $redirectTarget ) ) {
			$redirect = true;
			$title = $redirectTarget;
		}
		$id = $title->getArticleID();
		$values = PageProps::getInstance()->getProperties( $title, 'displaytitle' );
		if ( array_key_exists( $id, $values ) ) {
			$value = $values[$id];
			if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' &&
				$value !== $originalPageName ) {
				$displaytitle = $value;
				if ( $wrap ) {
					$displaytitle = new HtmlArmor( $displaytitle );
				}
				return true;
			}
		} elseif ( $redirect ) {
			$displaytitle = $title->getPrefixedText();
			if ( $wrap ) {
				$displaytitle = new HtmlArmor( $displaytitle );
			}
			return true;
		}
		return false;
	}
}

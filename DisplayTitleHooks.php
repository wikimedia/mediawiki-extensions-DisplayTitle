<?php
/*
 * Copyright (c) 2016 The MITRE Corporation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

class DisplayTitleHooks {

	/**
	 * Implements ParserFirstCallInit hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @since 1.0
	 * @param Parser &$parser the Parser object
	 * @return bool continue checking hooks
	 */
	public static function onParserFirstCallInit( &$parser ) {
		$parser->setFunctionHook( 'getdisplaytitle',
			'DisplayTitleHooks::getdisplaytitleParserFunction' );
		return true;
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
	 * Implements LinkBegin hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/LinkBegin
	 * Handle links. Implements LinkBegin hook of Linker class.
	 * If a link is customized by a user (e. g. [[Target|Text]]) it should
	 * remain intact. Let us assume a link is not customized if its html is
	 * the prefixed or (to support Semantic MediaWiki queries) non-prefixed title
	 * of the target page.
	 *
	 * @since 1.0
	 * @param string $dummy no longer used
	 * @param Title $target the Title object that the link is pointing to
	 * @param string &$html the HTML of the link text
	 * @param array &$customAttribs HTML attributes
	 * @param string &$query query string
	 * @param array &$options options
	 * @param string &$ret the value to return if the hook returns false
	 * @return bool continue checking hooks
	 */
	public static function onLinkBegin( $dummy, Title $target, &$html,
		&$customAttribs, &$query, &$options, &$ret ) {
		if ( isset( $html ) && is_string( $html ) ) {
			$title = Title::newFromText( $html );
			if ( !is_null( $title ) &&
				$title->getText() === $target->getText() &&
				( $title->getSubjectNsText() === $target->getSubjectNsText() ||
				$title->getSubjectNsText() === '' ) ) {
				self::getDisplayTitle( $target, $html );
			}
		} else {
			self::getDisplayTitle( $target, $html );
		}
		return true;
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
	 * @return bool continue checking hooks
	 */
	public static function onSelfLinkBegin( Title $nt, &$html, &$trail,
		&$prefix, &$ret ) {
		self::getDisplayTitle( $nt, $html );
		return true;
	}

	/**
	 * Implements BeforePageDisplay hook.
	 * See https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * Display subtitle if requested
	 *
	 * @since 1.0
	 * @param OutputPage &$out the OutputPage object
	 * @param Skin &$sk the Skin object
	 * @return bool continue checking hooks
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$sk ) {
		if ( $GLOBALS['wgDisplayTitleHideSubtitle'] ) {
			return true;
		}
		$title = $out->getTitle();
		if ( !$title->isTalkPage() ) {
			$found = self::getDisplayTitle( $title, $displaytitle );
		} elseif ( $title->getSubjectPage()->exists() ) {
			$found = self::getDisplayTitle( $title->getSubjectPage(), $displaytitle );
		}
		if ( $found ) {
			$subtitle = $title->getPrefixedText();
			$old_subtitle = $out->getSubtitle();
			if ( $old_subtitle !== '' ) {
				$subtitle .= ' / ' . $old_subtitle;
			}
			$out->setSubtitle( $subtitle );
		}
		return true;
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
	 * @return bool continue checking hooks
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
		return true;
	}

	/**
	 * Implements ScribuntoExternalLibraries hook.
	 * See https://www.mediawiki.org/wiki/Extension:Scribunto#Other_pages
	 * Handle Scribunto integration
	 *
	 * @since 1.2
	 * @param string $engine engine in use
	 * @param array &$extraLibraries list of registered libraries
	 * @return bool continue checking hooks
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		if ( $engine === 'lua' ) {
			$extraLibraries['mw.ext.displaytitle'] = 'DisplayTitleLuaLibrary';
		}
		return true;
	}

	/**
	 * Get displaytitle page property text.
	 *
	 * @since 1.0
	 * @param Title $title the Title object for the page
	 * @param string &$displaytitle to return the display title, if set
	 * @return boolean true if the page has a displaytitle page property that is
	 * different from the prefixed page name, false otherwise
	 */
	private static function getDisplayTitle( Title $title, &$displaytitle ) {
		$pagetitle = $title->getPrefixedText();
		// remove fragment
		$title = Title::newFromText( $pagetitle );
		if ( $title instanceof Title ) {
			$values = PageProps::getInstance()->getProperties( $title, 'displaytitle' );
			$id = $title->getArticleID();
			if ( array_key_exists( $id, $values ) ) {
				$value = $values[$id];
				if ( trim( str_replace( '&#160;', '', strip_tags( $value ) ) ) !== '' &&
					$value !== $pagetitle ) {
					$displaytitle = $value;
					return true;
				}
			}
		}
		return false;
	}
}
